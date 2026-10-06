<?php
namespace App\Jobs;
use App\Models\ApiSyncRun;
use App\Services\MaterialStockService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
class SyncApiIntegration implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public int $timeout = 1800;
    public function __construct(public int $runId) {}
    public function handle(): void
    {
        set_time_limit(0);
        $run = ApiSyncRun::with('integration')->findOrFail($this->runId);
        $integration = $run->integration;
        $run->update(['status' => 'running', 'started_at' => now()]);
        if (! $integration->status) {
            $this->failRun($run, $integration, 'The API integration is inactive.');
            return;
        }
        $environmentProfile = $integration::currentEnvironmentProfile();
        if (! $environmentProfile || blank($environmentProfile->base_url)) {
            $this->failRun($run, $integration, 'No base URL is configured in the database for the '.$integration::currentEnvironmentCode().' environment.');
            return;
        }
        // The environment host and this integration's endpoint path both come from the database.
        $url = rtrim($environmentProfile->base_url, '/').($integration->endpoint_path ?: '');
        $maxAttempts = max(1, (int) $integration->retry_count + 1);
        if ($integration->code === 'stock' || $integration->tbl_name === 'tbl_material_stocks') {
            try { $this->syncStockByMaterial($run, $integration, $url, (bool) $environmentProfile->verify_ssl, $maxAttempts); } catch (Throwable $exception) { $this->failRun($run, $integration, $exception->getMessage()); }
            return;
        }
        if ($integration->tbl_name === 'tbl_vendors') {
            try { $this->syncVendorsByMaterial($run, $integration, $url, (bool) $environmentProfile->verify_ssl, $maxAttempts); } catch (Throwable $exception) { $this->failRun($run, $integration, $exception->getMessage()); }
            return;
        }
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $started = microtime(true);
            try {
                $response = $this->request($integration, $url, (bool) $environmentProfile->verify_ssl);
                $duration = (int) round((microtime(true) - $started) * 1000);
                if (! $response->successful()) {
                    throw new RuntimeException("API returned HTTP {$response->status()}.");
                }
                $httpStatus = $response->status();
                $decoded = $response->json();
                unset($response);
                if (! is_array($decoded)) throw new RuntimeException('API response is not valid JSON.');
                $records = data_get($decoded, 'd.results') ?? data_get($decoded, 'value') ?? $decoded;
                if (! is_array($records)) $records = [$records];
                unset($decoded);
                DB::table('api_sync_attempts')->insert($this->attemptRow($run, $attempt, $url, $integration->http_method, 'success', $duration, $httpStatus));
                $saved = filled($integration->tbl_name)
                    ? $this->importRecords($integration, $records)
                    : $this->storePayloads($run->id, $integration->id, $records);
                $run->update(['status' => 'success', 'completed_at' => now(), 'http_status' => $httpStatus, 'records_received' => count($records), 'records_saved' => $saved, 'error_message' => null]);
                $integration->forceFill(['last_success_at' => now(), 'last_error' => null])->save();
                return;
            } catch (Throwable $exception) {
                $duration = (int) round((microtime(true) - $started) * 1000);
                DB::table('api_sync_attempts')->insert($this->attemptRow($run, $attempt, $url, $integration->http_method, 'failed', $duration, null, $exception));
                if ($attempt < $maxAttempts && $integration->retry_delay_seconds > 0) sleep((int) $integration->retry_delay_seconds);
                if ($attempt === $maxAttempts) $this->failRun($run, $integration, $exception->getMessage());
            }
        }
    }
    private function request($integration, string $url, bool $verifySsl): Response
    {
        $request = Http::connectTimeout(15)->timeout((int) $integration->timeout_seconds)->withOptions(['verify' => $verifySsl])->acceptJson();
        $credential = config('api-integrations.credentials.'.$integration->credential_env_key);
        if (in_array($integration->auth_type, ['bearer', 'api_key'], true) && blank($credential)) throw new RuntimeException("Credential {$integration->credential_env_key} is not configured.");
        if ($integration->auth_type === 'bearer') $request = $request->withToken($credential);
        if ($integration->auth_type === 'api_key') $request = $request->withHeaders(['X-API-Key' => $credential]);
        if ($integration->auth_type === 'basic') {
            $username = config('api-integrations.credentials.'.$integration->username_env_key);
            $password = config('api-integrations.credentials.'.$integration->password_env_key);
            if (blank($username) || blank($password)) throw new RuntimeException('API_AUTH_USERNAME and API_AUTH_PASSWORD must be configured.');
            $request = $request->withBasicAuth($username, $password);
        }
        return $request->send($integration->http_method, $url);
    }
    private function storePayloads(int $runId, int $integrationId, array $records): int
    {
        $rows = [];
        foreach ($records as $record) {
            $payload = is_array($record) ? $record : ['value' => $record];
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $rows[] = [
                'api_sync_run_id' => $runId,
                'api_integration_id' => $integrationId,
                'external_key' => $payload['id'] ?? $payload['Matnr'] ?? $payload['MATNR'] ?? null,
                'payload' => $json,
                'payload_hash' => hash('sha256', $json),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        return collect($rows)->unique('payload_hash')->chunk(250)->sum(function ($chunk) {
            DB::table('api_sync_payloads')->upsert(
                $chunk->all(),
                ['api_integration_id', 'payload_hash'],
                ['api_sync_run_id', 'external_key', 'payload', 'status', 'updated_at']
            );
            return $chunk->count();
        });
    }
    private function importRecords($integration, array $records): int
    {
        $table = $integration->tbl_name;
        if ($table === MaterialStockService::MATERIAL_TABLE) {
            return app(MaterialStockService::class)->importMaterials($records);
        }
        if (! preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new RuntimeException('The destination table name is invalid.');
        $columns = DB::connection()->getSchemaBuilder()->getColumnListing($table);
        if (! $columns) throw new RuntimeException("Destination table {$table} does not exist.");
        $columnMap = collect($columns)->mapWithKeys(fn ($column) => [strtolower($column) => $column]);
        $rowsByShape = [];
        foreach ($records as $record) {
            if (! is_array($record)) continue;
            $row = [];
            foreach ($record as $key => $value) {
                $column = $columnMap->get(strtolower((string) $key));
                if ($column && $column !== 'id' && ! is_array($value) && ! is_object($value)) $row[$column] = $value;
            }
            if (! $row) continue;
            if (in_array('status', $columns, true) && ! array_key_exists('status', $row)) $row['status'] = 1;
            if (in_array('created_at', $columns, true) && ! array_key_exists('created_at', $row)) $row['created_at'] = now();
            if (in_array('updated_at', $columns, true)) $row['updated_at'] = now();
            ksort($row);
            $rowsByShape[implode('|', array_keys($row))][] = $row;
        }
        return collect($rowsByShape)->sum(function ($rows) use ($table) {
            $columns = array_keys($rows[0]);
            $identity = $this->identityColumns($table, $columns);
            $identityColumns = $identity['columns'];
            $updates = array_values(array_diff($columns, $identityColumns, ['created_at']));

            return collect($rows)->unique(fn ($row) => implode('|', array_map(
                fn ($column) => (string) ($row[$column] ?? ''), $identityColumns
            )))->chunk(500)->sum(function ($chunk) use ($table, $identity, $identityColumns, $updates) {
                if ($identity['enforced']) {
                    DB::table($table)->upsert($chunk->all(), $identityColumns, $updates);
                } else {
                    DB::transaction(function () use ($chunk, $table, $identityColumns, $updates) {
                        foreach ($chunk as $row) {
                            $match = array_intersect_key($row, array_flip($identityColumns));
                            $values = array_intersect_key($row, array_flip($updates));
                            DB::table($table)->updateOrInsert($match, $values);
                        }
                    });
                }
                return $chunk->count();
            });
        });
    }

    private function identityColumns(string $table, array $shape): array
    {
        $database = DB::connection()->getDatabaseName();
        $indexes = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)->where('TABLE_NAME', $table)
            ->where('NON_UNIQUE', 0)->where('INDEX_NAME', '<>', 'PRIMARY')
            ->orderBy('INDEX_NAME')->orderBy('SEQ_IN_INDEX')->get()
            ->groupBy('INDEX_NAME')->map(fn ($rows) => $rows->pluck('COLUMN_NAME')->all())
            ->filter(fn ($columns) => ! array_diff($columns, $shape))->sortBy(fn ($columns) => count($columns));
        if ($indexes->isNotEmpty()) return ['columns' => array_values($indexes->first()), 'enforced' => true];

        foreach ([
            ['purchase_order', 'purchase_order_item'], ['matnr', 'plant_code'],
            ['material_code', 'plant_code'], ['external_key'], ['uuid'], ['code'], ['company_code'],
        ] as $candidate) {
            if (! array_diff($candidate, $shape)) return ['columns' => $candidate, 'enforced' => false];
        }

        $fallback = array_values(array_diff($shape, ['created_at', 'updated_at', 'synced_at']));
        if (! $fallback) throw new RuntimeException("No stable identity columns are available for {$table}.");
        return ['columns' => $fallback, 'enforced' => false];
    }
    private function syncStockByMaterial(ApiSyncRun $run, $integration, string $endpoint, bool $verifySsl, int $maxAttempts): void
    {
        $materials = app(MaterialStockService::class)->materialCodes();
        $received = 0;
        $saved = 0;
        $failures = [];
        $started = microtime(true);
        foreach ($materials as $materialCode) {
            $escapedCode = str_replace("'", "''", (string) $materialCode);
            $url = explode('?', $endpoint, 2)[0].'?'.http_build_query(array_replace($this->endpointQuery($endpoint), [
                '$filter' => "Material_Code eq '{$escapedCode}'", '$format' => 'json',
            ]), '', '&', PHP_QUERY_RFC3986);
            $materialError = null;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $response = $this->request($integration, $url, $verifySsl);
                    
                    if (! $response->successful()) throw new RuntimeException("HTTP {$response->status()}");
                    $decoded = $response->json();
                    unset($response);
                    if (! is_array($decoded)) throw new RuntimeException('Invalid JSON response');
                    $records = data_get($decoded, 'd.results') ?? data_get($decoded, 'value');
                    if (! is_array($records) || ! array_is_list($records)) throw new RuntimeException('Invalid stock records.');
                    $records = $this->remainingODataPages($integration, $url, $verifySsl, $decoded, $records);
                    unset($decoded);
                    $saved += app(MaterialStockService::class)->importStocks($records, (string) $materialCode);
                    $run->update(['records_received' => $received + count($records), 'records_saved' => $saved]);
                    $received += count($records);
                    $materialError = null;
                    break;
                } catch (Throwable $exception) {
                    $materialError = $exception->getMessage();
                    if ($attempt < $maxAttempts && $integration->retry_delay_seconds > 0) sleep((int) $integration->retry_delay_seconds);
                }
            }
            if ($materialError !== null) $failures[] = $materialCode.': '.$materialError;
        }
        $duration = (int) round((microtime(true) - $started) * 1000);
        $error = $failures ? count($failures).' material(s) failed. '.implode(' | ', array_slice($failures, 0, 10)) : null;
        DB::table('api_sync_attempts')->insert($this->attemptRow(
            $run,
            1,
            $endpoint.'?$filter=Material_Code (dynamic from vw_material_stock_combined)',
            $integration->http_method,
            $error ? 'failed' : 'success',
            $duration,
            $error ? null : 200,
            $error ? new RuntimeException($error) : null
        ));
        $run->update([
            'status' => $error ? 'failed' : 'success',
            'completed_at' => now(),
            'http_status' => $error ? null : 200,
            'records_received' => $received,
            'records_saved' => $saved,
            'error_message' => $error,
        ]);
        $integration->forceFill($error
            ? ['last_failure_at' => now(), 'last_error' => $error]
            : ['last_success_at' => now(), 'last_error' => null])->save();
    }
    private function syncVendorsByMaterial(ApiSyncRun $run, $integration, string $endpoint, bool $verifySsl, int $maxAttempts): void
    {
        $this->assertVendorIndex();
        $materials = app(MaterialStockService::class)->materialCodes(false)->values();
        if ($materials->isEmpty()) throw new RuntimeException('No materials found.');
        $received = 0;
        $saved = 0;
        $processed = 0;
        $failures = [];
        $consecutiveFailures = 0;
        $attemptLimit = min(2, max(1, $maxAttempts));
        $started = microtime(true);
        $run->update(['materials_total' => $materials->count(), 'materials_processed' => 0]);

        for ($offset = 0; $offset < $materials->count();) {
            $batchSize = min(8, 10 - $consecutiveFailures, $materials->count() - $offset);
            $batch = $materials->slice($offset, $batchSize)->values();
            $offset += $batch->count();
            $pending = $batch->mapWithKeys(fn ($code) => [(string) $code => $this->vendorUrl($endpoint, (string) $code)])->all();
            $outcomes = [];

            for ($attempt = 1; $attempt <= $attemptLimit && $pending; $attempt++) {
                $responses = Http::pool(function (Pool $pool) use ($pending, $integration, $verifySsl) {
                    foreach ($pending as $materialCode => $url) {
                        $this->addVendorPoolRequest($pool, $materialCode, $integration, $url, $verifySsl);
                    }
                }, 8);

                $retry = [];
                foreach ($pending as $materialCode => $url) {
                    try {
                        $response = $responses[$materialCode] ?? null;
                        if ($response instanceof Throwable) throw $response;
                        if (! $response instanceof Response) throw new RuntimeException('No API response received.');
                        if (! $response->successful()) throw new RuntimeException("HTTP {$response->status()}");
                        $decoded = $response->json();
                        if (! is_array($decoded)) throw new RuntimeException('Invalid JSON response');
                        $records = data_get($decoded, 'd.results') ?? data_get($decoded, 'value');
                        if (! is_array($records) || ! array_is_list($records)) throw new RuntimeException('Invalid vendor records.');
                        $records = $this->remainingODataPages($integration, $url, $verifySsl, $decoded, $records);
                        $rows = $this->vendorRows($materialCode, $records);
                        DB::transaction(function () use ($rows, &$saved) {
                            foreach (array_chunk($rows, 500) as $chunk) {
                                DB::table('tbl_vendors')->upsert($chunk, ['purchase_order', 'purchase_order_item'], [
                                    'material_code', 'vendor_code', 'vendor_name', 'delivery_date', 'short_text',
                                    'company_code', 'plant_code', 'mt_quantity', 'base_unit', 'quantity',
                                    'purchase_order_type', 'release_state', 'status', 'synced_at', 'source_payload', 'updated_at',
                                ]);
                            }
                            $saved += count($rows);
                        });
                        $received += count($records);
                        $outcomes[$materialCode] = null;
                    } catch (Throwable $exception) {
                        if ($attempt < $attemptLimit) {
                            $retry[$materialCode] = $url;
                        } else {
                            $outcomes[$materialCode] = $exception->getMessage();
                        }
                    }
                }
                $pending = $retry;
                if ($pending && $attempt < $attemptLimit) sleep(min(2, max(0, (int) $integration->retry_delay_seconds)));
            }

            foreach ($batch as $materialCode) {
                $error = $outcomes[(string) $materialCode] ?? null;
                if ($error === null) {
                    $consecutiveFailures = 0;
                } else {
                    $consecutiveFailures++;
                    $failures[] = $materialCode.': '.$error;
                }
                $processed++;
                if ($consecutiveFailures >= 10) break;
            }
            $run->update([
                'materials_processed' => $processed,
                'records_received' => $received,
                'records_saved' => $saved,
            ]);
            if ($consecutiveFailures >= 10) {
                $failures[] = 'Synchronization stopped after 10 consecutive material failures.';
                break;
            }
        }
        $duration = (int) round((microtime(true) - $started) * 1000);
        $error = $failures ? count($failures).' material(s) failed. '.implode(' | ', array_slice($failures, 0, 10)) : null;
        DB::table('api_sync_attempts')->insert($this->attemptRow(
            $run,
            1,
            $endpoint.'?Material={vw_material_stock_combined.matnr}',
            $integration->http_method,
            $error ? 'failed' : 'success',
            $duration,
            $error ? null : 200,
            $error ? new RuntimeException($error) : null
        ));
        $run->update([
            'status' => $error ? 'failed' : 'success',
            'completed_at' => now(),
            'http_status' => $error ? null : 200,
            'records_received' => $received,
            'records_saved' => $saved,
            'materials_processed' => $processed,
            'error_message' => $error,
        ]);
        $integration->forceFill($error
            ? ['last_failure_at' => now(), 'last_error' => $error]
            : ['last_success_at' => now(), 'last_error' => null])->save();
    }

    private function vendorUrl(string $endpoint, string $materialCode): string
    {
        return explode('?', $endpoint, 2)[0].'?'.http_build_query(array_replace($this->endpointQuery($endpoint), [
            '$filter' => "Material eq '".str_replace("'", "''", $materialCode)."'",
            '$format' => 'json',
        ]), '', '&', PHP_QUERY_RFC3986);
    }

    private function addVendorPoolRequest(Pool $pool, string $key, $integration, string $url, bool $verifySsl): void
    {
        $request = $pool->as($key)->connectTimeout(10)->timeout(15)
            ->withOptions(['verify' => $verifySsl])->acceptJson();
        $credential = config('api-integrations.credentials.'.$integration->credential_env_key);
        if (in_array($integration->auth_type, ['bearer', 'api_key'], true) && blank($credential)) {
            throw new RuntimeException("Credential {$integration->credential_env_key} is not configured.");
        }
        if ($integration->auth_type === 'bearer') $request = $request->withToken($credential);
        if ($integration->auth_type === 'api_key') $request = $request->withHeaders(['X-API-Key' => $credential]);
        if ($integration->auth_type === 'basic') {
            $username = config('api-integrations.credentials.'.$integration->username_env_key);
            $password = config('api-integrations.credentials.'.$integration->password_env_key);
            if (blank($username) || blank($password)) throw new RuntimeException('API authentication is not configured.');
            $request = $request->withBasicAuth($username, $password);
        }
        $request->send($integration->http_method, $url);
    }
    private function vendorRows(string $requestedMaterialCode, array $records): array
    {
        $timestamp = now()->toDateTimeString();
        $rows = [];
        foreach ($records as $record) {
            if (! is_array($record)) continue;
            $vendorCode = $this->sapValue($record, ['Vendor', 'VendorCode', 'Lifnr', 'Supplier', 'SupplierCode']);
            $purchaseOrder = $this->sapValue($record, ['Purchaseorder', 'PurchaseOrder', 'PurchasingDocument', 'Ebeln']);
            $purchaseOrderItem = $this->sapValue($record, ['Purchaseorderitem', 'PurchaseOrderItem', 'PurchasingDocumentItem', 'Ebelp']);
            if (blank($purchaseOrder) || blank($purchaseOrderItem)) throw new RuntimeException('Missing purchase order or item.');
            $actualMaterial = trim((string) $this->sapValue($record, ['Material', 'MaterialCode', 'Matnr']));
            if ($actualMaterial === '' || $actualMaterial !== trim($requestedMaterialCode)) throw new RuntimeException('SAP material filter mismatch for '.$requestedMaterialCode);
            $rows[] = [
                'material_code' => $actualMaterial,
                'vendor_code' => blank($vendorCode) ? null : trim((string) $vendorCode),
                'vendor_name' => $this->sapValue($record, ['VendorName', 'VendorDescription', 'Name1', 'SupplierName']),
                'purchase_order' => (string) $purchaseOrder,
                'purchase_order_item' => (string) $purchaseOrderItem,
                'delivery_date' => $this->sapDate($this->sapValue($record, ['Deliverydate', 'DeliveryDate'])),
                'short_text' => $this->sapValue($record, ['Shorttext', 'ShortText']),
                'company_code' => $this->sapValue($record, ['Companycode', 'CompanyCode']),
                'plant_code' => $this->sapValue($record, ['Plant']),
                'mt_quantity' => is_numeric($number = $this->sapValue($record, ['MtQuantity'])) ? $number : 0,
                'base_unit' => $this->sapValue($record, ['Baseunit', 'BaseUnit']),
                'quantity' => is_numeric($number = $this->sapValue($record, ['Quantity'])) ? $number : 0,
                'purchase_order_type' => $this->sapValue($record, ['Purchaseordertype', 'PurchaseOrderType']),
                'release_state' => $this->sapValue($record, ['Releasestate', 'ReleaseState']),
                'status' => 1,
                'synced_at' => $timestamp,
                'source_payload' => json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        return collect($rows)
            ->unique(fn (array $row) => $row['purchase_order'].'|'.$row['purchase_order_item'])
            ->values()
            ->all();
    }
    private function sapDate(mixed $value): ?string
    {
        if (blank($value)) return null;
        if (preg_match('~^/Date\((-?\d+)(?:[+-]\d{4})?\)/$~', (string) $value, $matches)) {
            return \Illuminate\Support\Carbon::createFromTimestampUTC(((int) $matches[1]) / 1000)->toDateString();
        }
        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            throw new RuntimeException('Invalid SAP delivery date.');
        }
    }
    private function endpointQuery(string $endpoint): array
    {
        $query = [];
        parse_str(parse_url($endpoint, PHP_URL_QUERY) ?: '', $query);
        unset($query['Material'], $query['$skip'], $query['$skiptoken'], $query['$top']);
        return $query;
    }

    private function assertVendorIndex(): void
{
    $connection = DB::connection();
    $database = $connection->getDatabaseName();
    $table = $connection->getTablePrefix().'tbl_vendors';

    $indexes = $connection->table('information_schema.STATISTICS')
        ->selectRaw(
            'INDEX_NAME AS index_name,
             COLUMN_NAME AS column_name,
             SUB_PART AS sub_part'
        )
        ->where('TABLE_SCHEMA', $database)
        ->where('TABLE_NAME', $table)
        ->where('NON_UNIQUE', 0)
        ->orderBy('INDEX_NAME')
        ->orderBy('SEQ_IN_INDEX')
        ->get()
        ->groupBy('index_name');

    $found = $indexes->contains(function ($index) {
        // Require full columns, not a prefix index.
        if ($index->contains(fn ($row) => $row->sub_part !== null)) {
            return false;
        }

        $columns = $index->pluck('column_name')
            ->map(fn ($column) => strtolower((string) $column))
            ->all();

        sort($columns);

        return $columns === [
            'purchase_order',
            'purchase_order_item',
        ];
    });

    if (! $found) {
        throw new RuntimeException(
            "Required UNIQUE index not found on "
            ."{$database}.{$table}(purchase_order, purchase_order_item)."
        );
    }

    $column = $connection->table('information_schema.COLUMNS')
        ->selectRaw('IS_NULLABLE AS nullable_flag')
        ->where('TABLE_SCHEMA', $database)
        ->where('TABLE_NAME', $table)
        ->where('COLUMN_NAME', 'vendor_code')
        ->first();

    if (! $column) {
        throw new RuntimeException(
            "Column vendor_code is missing from {$database}.{$table}."
        );
    }

    if (strtoupper((string) $column->nullable_flag) !== 'YES') {
        throw new RuntimeException(
            'Make vendor_code nullable preserving its type, '
            .'or add vendor code to the SAP response.'
        );
    }
}
    private function remainingODataPages($integration, string $url, bool $verifySsl, array $decoded, array $records): array
    {
        $origin = parse_url($url);
        $seen = [$url => true];
        while (filled($next = data_get($decoded, 'd.__next') ?? ($decoded['@odata.nextLink'] ?? null))) {
            $nextUrl = (string) \GuzzleHttp\Psr7\UriResolver::resolve(
                new \GuzzleHttp\Psr7\Uri($url), new \GuzzleHttp\Psr7\Uri((string) $next));
            $parts = parse_url($nextUrl);
            foreach (['scheme', 'host', 'port'] as $key) {
                if (($origin[$key] ?? null) !== ($parts[$key] ?? null)) throw new RuntimeException('SAP pagination changed origin.');
            }
            if (isset($seen[$nextUrl])) throw new RuntimeException('Repeated SAP pagination URL.');
            $seen[$nextUrl] = true;
            $response = $this->request($integration, $nextUrl, $verifySsl);
            if (! $response->successful()) throw new RuntimeException('SAP pagination HTTP '.$response->status());
            $decoded = $response->json();
            if (! is_array($decoded)) throw new RuntimeException('Invalid SAP pagination JSON.');
            $page = data_get($decoded, 'd.results') ?? data_get($decoded, 'value');
            if (! is_array($page) || ! array_is_list($page)) throw new RuntimeException('Invalid SAP pagination records.');
            array_push($records, ...$page);
            $url = $nextUrl;
        }
        return $records;
    }

    public function failed(?Throwable $exception): void
    {
        $run = ApiSyncRun::with('integration')->find($this->runId);
        if ($run && $run->integration) {
            $this->failRun($run, $run->integration, $exception?->getMessage() ?? 'Queue job failed.');
        }
    }

    private function sapValue(array $record, array $aliases): mixed
    {
        $normalized = [];
        foreach ($record as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalized[strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string) $key))] = $value;
            }
        }
        foreach ($aliases as $alias) {
            $key = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $alias));
            if (array_key_exists($key, $normalized) && filled($normalized[$key])) return $normalized[$key];
        }
        return null;
    }
    private function attemptRow(ApiSyncRun $run, int $attempt, string $url, string $method, string $status, int $duration, ?int $httpStatus = null, ?Throwable $exception = null): array
    {
        return ['api_sync_run_id' => $run->id, 'attempt_number' => $attempt, 'request_url' => $url, 'request_method' => $method, 'http_status' => $httpStatus, 'duration_ms' => $duration, 'status' => $status, 'error_type' => $exception ? class_basename($exception) : null, 'error_message' => $exception?->getMessage(), 'exception_class' => $exception ? get_class($exception) : null, 'attempted_at' => now(), 'created_at' => now(), 'updated_at' => now()];
    }
    private function failRun(ApiSyncRun $run, $integration, string $message): void
    {
        $run->update(['status' => 'failed', 'completed_at' => now(), 'error_message' => $message]);
        $integration->forceFill(['last_failure_at' => now(), 'last_error' => $message])->save();
    }
}


