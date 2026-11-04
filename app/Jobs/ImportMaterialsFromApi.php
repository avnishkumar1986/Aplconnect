<?php

namespace App\Jobs;

use App\Models\ApiIntegration;
use App\Models\ApiIntegrationLog;
use App\Models\Material;
use App\Models\MaterialStock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportMaterialsFromApi implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $lastReceivedCount = 0;

    public ?string $lastBatchSignature = null;

    public function __construct(
        public int $apiId,
        public ?int $requestedBy = null,
        public ?string $material = null,
        public ?int $logId = null,
        public ?int $recordLimit = null,
        public ?int $recordSkip = null,
        public ?string $customerNumber = null,
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public bool $deferRunStatus = false,
    ) {
        $this->onQueue('api-imports');
    }

    public function handle(): void
    {
        set_time_limit($this->timeout);
        ini_set('memory_limit', '512M');

        $api = ApiIntegration::findOrFail($this->apiId);
        $runLog = $this->logId
            ? ApiIntegrationLog::query()->where('api_integration_id', $api->id)->find($this->logId)
            : null;
        $runLog ??= $api->logs()->create([
            'run_id' => (string) Str::uuid(),
            'status' => 'running',
            'attempt' => 1,
            'message' => 'API run started.',
            'created_by' => $this->requestedBy,
            'created_at' => now(),
        ]);
        $started = microtime(true);

        try {
            $environmentCode = (string) config('services.api_integrations.environment_code', 'production');
            $environmentApi = ApiIntegration::query()
                ->where('code', $environmentCode)
                ->where('status', true)
                ->first();
            $endpoint = trim((string) $api->endpoint);
            $today = now(config('app.timezone'))->toDateString();
            $apiCode = strtolower((string) $api->code);
            $fromDate = $this->validatedSalesDate($this->fromDate, $today);
            $toDate = $this->validatedSalesDate($this->toDate, $today);
            if ($apiCode === 'sales_data' && $fromDate > $toDate) {
                throw new RuntimeException('The sales import start date must be on or before the end date.');
            }
            $endpoint = str_replace(
                ['{{today}}', '{today}', '{{from_date}}', '{from_date}', '{{to_date}}', '{to_date}'],
                [$today, $today, $fromDate, $fromDate, $toDate, $toDate],
                $endpoint,
            );
            if ($apiCode === 'customer_sales_summary' || ($apiCode === 'customer_collections' && str_contains($endpoint, '{{customer_number}}'))) {
                $customerNumber = trim((string) $this->customerNumber);
                if ($customerNumber === '') {
                    throw new RuntimeException('A customer number is required for this customer API.');
                }
                $encodedCustomerNumber = rawurlencode($customerNumber);
                $parameterizedEndpoint = str_replace(
                    ['{{customer_number}}', '{customer_number}'],
                    $encodedCustomerNumber,
                    $endpoint,
                );
                if ($apiCode === 'customer_sales_summary' && $parameterizedEndpoint === $endpoint) {
                    $parameterizedEndpoint = preg_replace_callback(
                        "/zcustwise_saleSet\\((?:%27|')[^)]*(?:%27|')\\)/i",
                        fn (): string => "zcustwise_saleSet(%27{$encodedCustomerNumber}%27)",
                        $endpoint,
                        1,
                    ) ?? $endpoint;
                }
                if ($parameterizedEndpoint === $endpoint && ! str_contains($endpoint, $encodedCustomerNumber)) {
                    throw new RuntimeException('The customer endpoint must contain {{customer_number}}.');
                }
                $endpoint = $parameterizedEndpoint;
            }
            $externalAbsoluteUrl = $api->api_type === 'external' && filter_var($endpoint, FILTER_VALIDATE_URL);
            $baseUrl = trim((string) ($environmentApi?->endpoint ?? ''));

            if (! $externalAbsoluteUrl && $baseUrl === '') {
                throw new RuntimeException("The [{$environmentCode}] environment API URL is not configured in the database.");
            }

            $url = $externalAbsoluteUrl
                ? $endpoint
                : rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');
            if ($api->code === 'vendor' && filled($this->material)) {
                $material = str_replace("'", "''", trim((string) $this->material));
                $parts = parse_url($url);
                parse_str((string) ($parts['query'] ?? ''), $query);
                unset($query['Material'], $query['material']);
                $query['$filter'] = "Material eq '{$material}'";
                $query['$format'] = 'json';
                $base = (isset($parts['scheme']) ? $parts['scheme'].'://' : '')
                    .($parts['host'] ?? '')
                    .(isset($parts['port']) ? ':'.$parts['port'] : '')
                    .($parts['path'] ?? '');
                $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
                $queryString = str_replace(['%24filter', '%24format'], ['$filter', '$format'], $queryString);
                $url = $base.'?'.$queryString;
            }
            if ($api->api_type === 'sap' && $this->recordLimit !== null) {
                $url = $this->withOdataPagination($url, $this->recordLimit, $this->recordSkip ?? 0);
            }
            $host = parse_url($url, PHP_URL_HOST);
            $allowedHosts = array_filter(array_map('trim', explode(',', (string) config('services.api_integrations.allowed_hosts'))));

            if (! $host || ($allowedHosts !== [] && ! in_array($host, $allowedHosts, true))) {
                throw new RuntimeException('Material API host is not in API_ALLOWED_HOSTS.');
            }

            $maxAttempts = max(1, min(10, (int) $api->retries));
            $requestTimeout = max(1, min(300, (int) $api->timeout_seconds));
            $connectTimeout = max(1, min(
                $requestTimeout,
                (int) config('services.api_integrations.connect_timeout_seconds', 10),
            ));
            $attempts = 0;
            $client = Http::connectTimeout($connectTimeout)
                ->timeout($requestTimeout)
                ->acceptJson()
                ->beforeSending(function () use (&$attempts): void {
                    $attempts++;
                })
                ->retry(
                    $maxAttempts,
                    fn (int $attempt): int => $this->retryDelayMilliseconds($attempt, (int) $api->retry_delay_seconds),
                    function (Throwable $exception, PendingRequest $_request) use ($runLog, &$attempts, $maxAttempts): bool {
                        $shouldRetry = $this->shouldRetryApiRequest($exception);

                        if ($shouldRetry && $attempts < $maxAttempts) {
                            $runLog->update([
                                'status' => 'running',
                                'attempt' => $attempts + 1,
                                'message' => 'Transient API failure; request attempt '.($attempts + 1)." of {$maxAttempts} is pending.",
                            ]);
                        }

                        return $shouldRetry;
                    },
                );

            // The Quality SAP appliance presents its internal QASFIORIAPP.local
            // certificate while it is reached through its configured IP address.
            // Keep this exception restricted to that environment and host.
            $insecureTlsHosts = array_filter(array_map('trim', explode(',', (string) config('services.api_integrations.insecure_tls_hosts'))));
            if ($environmentCode === 'quality' && in_array($host, $insecureTlsHosts, true)) {
                $client = $client->withoutVerifying();
            }

            $credential = $api->api_type === 'sap'
                ? ($environmentApi?->credentials ?? [])
                : ($api->credentials ?? []);
            $token = is_array($credential) ? (string) ($credential['token'] ?? '') : (string) $credential;
            $username = is_array($credential) ? (string) ($credential['username'] ?? '') : '';
            $password = is_array($credential) ? (string) ($credential['password'] ?? '') : '';
            $subscriptionName = is_array($credential) ? (string) ($credential['subscription_name'] ?? '') : '';

            if ($api->api_type === 'sap' && $username !== '' && $password !== '') {
                $client = $client->withBasicAuth($username, $password);
            } elseif ($api->api_type === 'sap') {
                throw new RuntimeException("SAP credentials are not configured on the [{$environmentCode}] environment API.");
            }

            $payload = [];
            if ($api->api_type === 'external') {
                if ($subscriptionName === '' || $token === '') {
                    throw new RuntimeException('External API credentials are not configured.');
                }
                $payload = ['SubscriptionName' => $subscriptionName, 'Token' => $token];
            }

            $response = $api->api_type === 'external'
                ? $client->post($url, $payload)
                : $client->get($url);
            $response->throw();
            $json = $response->json();
            $items = $this->extractApiItems($json, $apiCode);

            if (! is_array($items)) {
                throw new RuntimeException('API response does not contain an importable record list.');
            }
            if ($this->recordLimit !== null) {
                $items = array_slice($items, 0, max(0, $this->recordLimit));
            }
            $this->lastReceivedCount = count($items);
            $this->lastBatchSignature = $items === []
                ? hash('sha256', 'empty')
                : hash('sha256', json_encode([$items[0], $items[array_key_last($items)], count($items)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'unserializable');

            $targets = $api->target_table ?: match ($apiCode) {
                'stock', 'mrmcon' => ['tbl_material_stocks'],
                'vendor' => ['tbl_vendors', 'tbl_vendor_purchase_orders'],
                'employee' => ['tbl_users', 'tbl_usrcontact', 'tbl_usrdept'],
                'cust', 'customer' => ['tbl_users', 'tbl_usrcontact', 'tbl_usraddress', 'tbl_customer_sales'],
                'sales_data' => ['tbl_dailysale'],
                'customer_sales_summary' => ['tbl_customer_sales_summary'],
                'customer_collections' => ['tbl_customer_collection_transactions'],
                default => ['tbl_materials'],
            };
            $messages = [];

            if ($apiCode === 'employee') {
                [$created, $updated, $skipped] = $this->importEmployees($items);
                $messages[] = "employee: {$created} created, {$updated} updated, {$skipped} skipped";
            }

            if (in_array($apiCode, ['cust', 'customer'], true)) {
                [$created, $updated, $skipped] = $this->importCustomers($items);
                $messages[] = "customer: {$created} created, {$updated} updated, {$skipped} skipped";
            }

            if ($apiCode === 'pending_sales') {
                [$created, $updated, $skipped] = $this->importPendingSales($items);
                Cache::forget('sales-distribution.pending-sales.stats');
                $messages[] = "pending orders: {$created} created, {$updated} updated, {$skipped} skipped";
            }

            if ($apiCode === 'sales_data') {
                [$created, $updated, $skipped] = $this->importSalesData($items);
                Cache::forget('sales-distribution.daily-sales.stats');
                $this->refreshMonthlySales($fromDate, $toDate);
                $messages[] = "sales data: {$created} created, {$updated} updated, {$skipped} skipped";
            }

            if ($apiCode === 'customer_sales_summary') {
                [$created, $updated, $skipped] = $this->importCustomerSalesSummary($items);
                Cache::forget('sales-distribution.customer-summary.stats');
                $messages[] = "customer sales summary: {$created} created, {$updated} updated, {$skipped} skipped";
            }

            if ($apiCode === 'customer_collections') {
                [$created, $skipped] = $this->importCustomerCollections($items, $fromDate, $toDate);
                Cache::forget('sales-distribution.collections.stats');
                $messages[] = "customer collections: {$created} receipts stored, {$skipped} skipped";
            }

            if (in_array('tbl_materials', $targets, true)) {
                [$created, $updated, $skipped] = $this->importMaterials($items);
                $messages[] = "tbl_materials: {$created} created, {$updated} updated, {$skipped} skipped";
                $this->clearProcurementDashboardCache();
            }

            if (in_array('tbl_material_stocks', $targets, true)) {
                [$synchronized, $skipped] = $this->importStock($items, $apiCode === 'mrmcon');
                $messages[] = "tbl_material_stocks: {$synchronized} synchronized, {$skipped} skipped";
                $this->clearProcurementDashboardCache();
            }

            $importVendors = in_array('tbl_vendors', $targets, true);
            $importPurchaseOrders = in_array('tbl_vendor_purchase_orders', $targets, true);
            if ($importVendors || $importPurchaseOrders) {
                [$purchaseOrderCount, $vendorCount, $skipped] = $this->importVendorPurchaseOrders($items, $importVendors, $importPurchaseOrders);
                if ($importVendors) {
                    $messages[] = "tbl_vendors: {$vendorCount} synchronized";
                }
                if ($importPurchaseOrders) {
                    $messages[] = "tbl_vendor_purchase_orders: {$purchaseOrderCount} synchronized";
                }
                $messages[] = "vendor payload: {$skipped} skipped";
            }

            $specializedTargets = [
                'tbl_materials', 'tbl_material_stocks', 'tbl_vendors', 'tbl_vendor_purchase_orders',
                'tbl_users', 'tbl_usrcontact', 'tbl_usraddress', 'tbl_usrdept', 'tbl_customer_sales', 'tbl_pending_sales', 'tbl_dailysale',
                'tbl_customer_sales_summary', 'tbl_customer_collection_transactions',
            ];
            foreach (array_diff($targets, $specializedTargets) as $target) {
                [$inserted, $skipped] = $this->importGenericTable($target, $items);
                $messages[] = "{$target}: {$inserted} inserted, {$skipped} skipped";
            }

            if ($messages === []) {
                throw new RuntimeException('No supported target table is configured for this API.');
            }

            if (! $this->deferRunStatus) {
                $runLog->update([
                    'status' => 'success',
                    'http_status' => $response->status(),
                    'attempt' => max(1, $attempts),
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'records_received' => count($items),
                    'message' => implode('; ', $messages).'.',
                ]);
                $api->update(['last_run_status' => 'success', 'last_run_at' => now()]);
            }
        } catch (Throwable $exception) {
            $httpStatus = $exception instanceof RequestException
                ? $exception->response->status()
                : null;
            $message = $httpStatus === 401
                ? 'API authentication failed: the remote server rejected the configured username or password.'
                : $exception->getMessage();
            if (! $this->deferRunStatus) {
                $runLog->update([
                    'status' => 'failed',
                    'http_status' => $httpStatus,
                    'attempt' => max(1, $attempts ?? 1),
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'message' => $message,
                ]);
                $api->update(['last_run_status' => 'failed', 'last_run_at' => now()]);
            }
            throw $exception;
        }
    }

    private function retryDelayMilliseconds(int $attempt, int $baseDelaySeconds): int
    {
        $baseDelayMilliseconds = max(0, min(300, $baseDelaySeconds)) * 1000;

        return min(30_000, $baseDelayMilliseconds * (2 ** max(0, $attempt - 1)));
    }

    private function shouldRetryApiRequest(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        return in_array($exception->response->status(), [408, 425, 429, 500, 502, 503, 504], true);
    }

    private function clearProcurementDashboardCache(): void
    {
        foreach (['1000', '5000'] as $companyCode) {
            Cache::forget('procurement.dashboard.stock-by-plant.'.$companyCode);
            Cache::forget('procurement.dashboard.sap-summary.'.$companyCode);
            Cache::forget('procurement.dashboard.sap-plant-demand.'.$companyCode);
            Cache::forget('procurement.dashboard.sap-material-demand.'.$companyCode);
            Cache::forget('procurement.dashboard.inventory.'.$companyCode);
        }
    }

    private function validatedSalesDate(?string $value, string $default): string
    {
        if (blank($value)) {
            return $default;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->toDateString();
        } catch (Throwable) {
            throw new RuntimeException("Invalid sales import date [{$value}]. Expected YYYY-MM-DD.");
        }
    }

    private function refreshMonthlySales(string $fromDate, string $toDate): void
    {
        if (! Schema::hasTable('tbl_monthly_sales')) {
            return;
        }

        $periods = DB::table('tbl_dailysale')
            ->whereBetween('billing_date', [$fromDate, $toDate])
            ->whereNotNull('billing_year')
            ->whereNotNull('billing_month')
            ->select(['billing_year', 'billing_month'])
            ->distinct()
            ->get();

        foreach ($periods as $period) {
            $year = (int) $period->billing_year;
            $month = (int) $period->billing_month;
            $monthName = CarbonImmutable::create($year, $month, 1)->format('F');
            $now = now();
            $companyTotals = DB::table('tbl_dailysale')
                ->where('billing_year', $year)
                ->where('billing_month', $month)
                ->groupBy('company_code')
                ->selectRaw('COALESCE(company_code, "UNKNOWN") as company_code, SUM(net_value) as net_sales, COUNT(*) as source_records')
                ->get();
            $allTotal = DB::table('tbl_dailysale')
                ->where('billing_year', $year)
                ->where('billing_month', $month)
                ->selectRaw('SUM(net_value) as net_sales, COUNT(*) as source_records')
                ->first();

            DB::table('tbl_monthly_sales')->where('sales_year', $year)->where('sales_month', $month)->delete();
            $rows = $companyTotals->map(fn ($row): array => [
                'sales_year' => $year,
                'sales_month' => $month,
                'month_name' => $monthName,
                'company_code' => $row->company_code,
                'net_sales' => $row->net_sales,
                'net_sales_crore' => round((float) $row->net_sales / 10000000, 2),
                'source_records' => $row->source_records,
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->push([
                'sales_year' => $year,
                'sales_month' => $month,
                'month_name' => $monthName,
                'company_code' => 'ALL',
                'net_sales' => $allTotal->net_sales ?? 0,
                'net_sales_crore' => round((float) ($allTotal->net_sales ?? 0) / 10000000, 2),
                'source_records' => $allTotal->source_records ?? 0,
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('tbl_monthly_sales')->insert($rows);
        }
    }

    private function withOdataPagination(string $url, int $top, int $skip): string
    {
        $parts = parse_url($url);
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['$top'] = max(1, $top);
        $query['$skip'] = max(0, $skip);
        $query['$format'] = 'json';
        $base = (isset($parts['scheme']) ? $parts['scheme'].'://' : '')
            .($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $base.'?'.str_replace(['%24top', '%24skip', '%24format'], ['$top', '$skip', '$format'], $queryString);
    }

    /** @return array{int, int, int} */
    private function importMaterials(array $items): array
    {
        $created = $updated = $skipped = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $row = array_change_key_case($item, CASE_LOWER);
            $matnr = trim((string) ($row['matnr'] ?? $row['material_code'] ?? ''));
            $maktx = trim((string) ($row['maktx'] ?? $row['material_desc'] ?? $row['description'] ?? ''));
            if ($matnr === '' || $maktx === '') {
                $skipped++;

                continue;
            }

            $material = Material::where('matnr', $matnr)->first();
            $values = [
                'mtart' => $row['mtart'] ?? null,
                'matkl' => $row['matkl'] ?? null,
                'meins' => $row['meins'] ?? null,
                'maktx' => $maktx,
                'api_payload' => $item,
                'status' => true,
                'imported_by' => $this->requestedBy,
            ];

            if ($material) {
                $material->update($values);
                $updated++;
            } else {
                Material::create(['matnr' => $matnr, ...$values]);
                $created++;
            }
        }

        return [$created, $updated, $skipped];
    }

    /** @return array{int, int, int} */
    private function importEmployees(array $items): array
    {
        $created = $updated = 0;
        $uniqueItems = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = $this->normalizeEmployeeRow($item);
            $employeeCode = trim((string) $this->employeeValue($row, [
                'pernr', 'employeecode', 'empcode', 'employeenumber', 'employeeno', 'employeeid', 'uniqueid',
            ]));
            $employmentStatus = trim((string) $this->employeeValue($row, ['employmentstatus']));
            if ($employeeCode !== '' && strtolower($employmentStatus) !== 'inactive') {
                $uniqueItems[$employeeCode] = $item;
            }
        }
        $skipped = count($items) - count($uniqueItems);
        $employeeTypeId = DB::table('tbl_usertype')
            ->whereRaw('LOWER(name) = ?', ['employee'])
            ->where('status', true)
            ->value('uid');

        if (! $employeeTypeId) {
            throw new RuntimeException('An active Employee user type is required before importing employees.');
        }

        foreach ($uniqueItems as $item) {
            $row = $this->normalizeEmployeeRow($item);
            $employeeCode = trim((string) $this->employeeValue($row, [
                'pernr', 'employeecode', 'empcode', 'employeenumber', 'employeeno', 'employeeid', 'uniqueid',
            ]));
            $firstName = trim((string) $this->employeeValue($row, ['firstname', 'givenname']));
            $middleName = trim((string) $this->employeeValue($row, ['middlename']));
            $lastName = trim((string) $this->employeeValue($row, ['lastname', 'surname', 'familyname']));
            $fullName = trim((string) $this->employeeValue($row, ['name', 'ename', 'employeename', 'fullname']));
            if ($firstName === '' && $fullName !== '') {
                [$firstName, $splitMiddleName, $lastName] = $this->splitEmployeeName($fullName);
                $middleName = $middleName !== '' ? $middleName : ($splitMiddleName ?? '');
            }
            if ($employeeCode === '' || $firstName === '') {
                $skipped++;

                continue;
            }

            $existingUser = DB::table('tbl_users')->where('unique_id', $employeeCode)->first();
            $now = now();

            DB::transaction(function () use ($row, $employeeCode, $firstName, $middleName, $lastName, $existingUser, $employeeTypeId, $now): void {
                $userValues = [
                    'user_type' => $employeeTypeId,
                    'name' => trim(implode(' ', array_filter([$firstName, $middleName, $lastName], fn (string $part): bool => trim($part) !== ''))),
                    'first_name' => $firstName,
                    'middle_name' => $middleName !== '' ? $middleName : null,
                    'last_name' => $lastName,
                    'gender' => $this->nullableEmployeeString($row, ['gender', 'gendername']),
                    'marital_status' => $this->nullableEmployeeString($row, ['maritalstatus', 'maritalstatusname']),
                    'date_of_birth' => $this->normalizeApiDate($this->employeeValue($row, ['dateofbirth', 'birthdate', 'dob'])),
                    'status' => true,
                    'updated_by' => $this->requestedBy,
                    'updated_at' => $now,
                ];

                if ($existingUser) {
                    DB::table('tbl_users')->where('id', $existingUser->id)->update($userValues);
                    $userId = $existingUser->id;
                } else {
                    $userId = DB::table('tbl_users')->insertGetId([
                        'unique_id' => $employeeCode,
                        'created_by' => $this->requestedBy,
                        'created_at' => $now,
                        ...$userValues,
                    ]);
                }

                $email = strtolower(trim((string) $this->employeeValue($row, [
                    'officialemail', 'officialemailid', 'emailaddress', 'email', 'emailid', 'workemail',
                ])));
                $contactNumber = trim((string) $this->employeeValue($row, [
                    'contactnumber', 'mobile', 'mobileno', 'mobilenumber', 'phonenumber', 'telnumber',
                ]));
                if ($email !== '' || $contactNumber !== '') {
                    DB::table('tbl_usrcontact')->updateOrInsert(
                        ['user_id' => $employeeCode, 'is_primary' => true],
                        [
                            'usr_type' => $employeeTypeId,
                            'contact_type' => 'primary',
                            'email' => $email !== '' ? $email : null,
                            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                            'is_verified' => false,
                            'status' => true,
                            'created_by' => $this->requestedBy,
                            'updated_by' => $this->requestedBy,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }

                $this->syncEmployeeIdentityDocuments($row, $employeeCode, $employeeTypeId, $now);

                $departmentValue = trim((string) $this->employeeValue($row, [
                    'zdepartment', 'zdepartement', 'departmentcode', 'departmentid', 'department',
                ]));
                $departmentName = trim((string) $this->employeeValue($row, ['departmentdesc', 'departmentname']));
                $designationValue = trim((string) $this->employeeValue($row, [
                    'zposition', 'positioncode', 'designationcode', 'positionid', 'designationid',
                ]));
                $designationName = trim((string) $this->employeeValue($row, [
                    'positiondesc', 'positionname', 'designationname', 'designation',
                ]));
                $departmentId = $this->sapIdOrMasterId(
                    $departmentValue,
                    'tbl_departments',
                    'department_code',
                    'department_name',
                    $departmentName,
                );
                $designationId = $this->sapIdOrMasterId(
                    $designationValue,
                    'tbl_designations',
                    'des_id',
                    'designation_name',
                    $designationName,
                );

                DB::table('tbl_usrdept')->updateOrInsert(
                    ['user_id' => $employeeCode],
                    [
                        'user_type' => $employeeTypeId,
                        'sitting_location' => $this->nullableEmployeeString($row, [
                            'sittinglocation', 'worklocation', 'locationname', 'location', 'branchname',
                        ]),
                        'department_id' => $departmentId,
                        'designation_id' => $designationId,
                        'status' => true,
                        'created_by' => $this->requestedBy,
                        'updated_by' => $this->requestedBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });

            $existingUser ? $updated++ : $created++;
        }

        return [$created, $updated, $skipped];
    }

    /** @return array{int, int, int} */
    private function importCustomers(array $items): array
    {
        $created = $updated = $skipped = 0;
        $customerType = DB::table('tbl_usertype')->where('uid', 'USR4')->where('status', true)->value('uid');
        if (! $customerType) {
            throw new RuntimeException('An active Customer user type (USR4) is required before importing customers.');
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $row = $this->normalizeEmployeeRow($item);
            $customerNumber = trim((string) $this->employeeValue($row, ['kunnr', 'customernumber']));
            $customerName = trim((string) $this->employeeValue($row, ['name1', 'customername', 'name']));
            if ($customerNumber === '' || $customerName === '') {
                $skipped++;

                continue;
            }

            [$firstName, $middleName, $lastName] = $this->splitEmployeeName($customerName);
            $existing = DB::table('tbl_users')->where('unique_id', $customerNumber)->first();
            $now = now();
            $active = ! $this->apiBoolean($this->employeeValue($row, ['loevm', 'deletionindicator']));

            DB::transaction(function () use ($item, $row, $customerNumber, $customerName, $firstName, $middleName, $lastName, $existing, $customerType, $active, $now): void {
                $userValues = [
                    'user_type' => $customerType,
                    'name' => $customerName,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'status' => $active,
                    'updated_by' => $this->requestedBy,
                    'updated_at' => $now,
                ];
                if ($existing) {
                    DB::table('tbl_users')->where('id', $existing->id)->update($userValues);
                } else {
                    DB::table('tbl_users')->insert([
                        'unique_id' => $customerNumber,
                        'created_by' => $this->requestedBy,
                        'created_at' => $now,
                        ...$userValues,
                    ]);
                }

                $email = strtolower(trim((string) $this->employeeValue($row, ['mailid', 'email', 'emailaddress'])));
                $mobile = trim((string) $this->employeeValue($row, ['mobileno', 'mobile', 'contactnumber']));
                DB::table('tbl_usrcontact')->updateOrInsert(
                    ['user_id' => $customerNumber, 'contact_type' => 'primary'],
                    [
                        'usr_type' => $customerType,
                        'email' => $email !== '' && strtolower($email) !== 'na' ? $email : null,
                        'contact_number' => $mobile !== '' && strtolower($mobile) !== 'na' ? $mobile : null,
                        'is_primary' => true,
                        'is_verified' => false,
                        'status' => $active,
                        'created_by' => $this->requestedBy,
                        'updated_by' => $this->requestedBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                DB::table('tbl_usraddress')->updateOrInsert(
                    ['user_id' => $customerNumber, 'address_type' => 'primary'],
                    [
                        'user_type' => $customerType,
                        'address_line_1' => trim((string) $this->employeeValue($row, ['adrnr', 'address'])) ?: 'Address not provided',
                        'city' => $this->nullableEmployeeString($row, ['ort01', 'city']),
                        'state' => $this->nullableEmployeeString($row, ['state', 'statename']),
                        'district' => $this->nullableEmployeeString($row, ['bzirk', 'salesdistrict']),
                        'postal_code' => $this->nullableEmployeeString($row, ['pstlz', 'postalcode']),
                        'country' => 'India',
                        'is_primary' => true,
                        'status' => $active,
                        'created_by' => $this->requestedBy,
                        'updated_by' => $this->requestedBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                $sales = [
                    'user_type' => $customerType,
                    'record_creation_date' => $this->normalizeApiDate($this->employeeValue($row, ['erdat', 'recordcreationdate'])),
                    'plant_code' => $this->nullableEmployeeString($row, ['werks', 'plantcode']),
                    'deletion_indicator' => ! $active,
                    'gstin' => $this->nullableEmployeeString($row, ['gstin']),
                    'ecc_no' => $this->nullableEmployeeString($row, ['eccno']),
                    'cst_no' => $this->nullableEmployeeString($row, ['cstno']),
                    'lst_no' => $this->nullableEmployeeString($row, ['lstno']),
                    'pan' => $this->nullableEmployeeString($row, ['pan']),
                    'sales_organization' => $this->nullableEmployeeString($row, ['vkorg']),
                    'sales_district' => $this->nullableEmployeeString($row, ['bzirk']),
                    'sales_group' => $this->nullableEmployeeString($row, ['vkgrp']),
                    'sales_office' => $this->nullableEmployeeString($row, ['vkbur']),
                    'payment_terms' => $this->nullableEmployeeString($row, ['paymentterms']),
                    'credit_limit' => $this->apiNumber($this->employeeValue($row, ['limit'])),
                    'outstanding_amount' => $this->apiNumber($this->employeeValue($row, ['amount'])),
                    'credit_utilization_percentage' => $this->apiNumber($this->employeeValue($row, ['utilization'])),
                    'api_payload' => json_encode($item, JSON_THROW_ON_ERROR),
                    'synced_at' => $now,
                    'status' => $active,
                    'created_by' => $this->requestedBy,
                    'updated_by' => $this->requestedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach (range(1, 6) as $level) {
                    $sales["employee_level_{$level}"] = $this->nullableEmployeeString($row, ["empl{$level}"]);
                }
                foreach (range(1, 12) as $month) {
                    $sapMonth = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
                    $sales["month_{$sapMonth}_target"] = $this->apiNumber($this->employeeValue($row, ["z{$sapMonth}t"]));
                    $sales["month_{$sapMonth}_actual"] = $this->apiNumber($this->employeeValue($row, ["z{$sapMonth}a"]));
                }
                foreach (range(1, 4) as $quarter) {
                    $sales["quarter_{$quarter}_target"] = $this->apiNumber($this->employeeValue($row, ["q{$quarter}t"]));
                    $sales["quarter_{$quarter}_actual"] = $this->apiNumber($this->employeeValue($row, ["q{$quarter}a"]));
                }
                DB::table('tbl_customer_sales')->updateOrInsert(['user_id' => $customerNumber], $sales);
            });

            $existing ? $updated++ : $created++;
        }

        return [$created, $updated, $skipped];
    }

    private function apiNumber(mixed $value): float
    {
        $number = str_replace([',', ' '], '', trim((string) $value));

        return is_numeric($number) ? (float) $number : 0.0;
    }

    private function apiBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'x', 'yes', 'y'], true);
    }

    /** @param array<string, mixed> $row */
    private function syncEmployeeIdentityDocuments(array $row, string $employeeCode, string $employeeTypeId, mixed $now): void
    {
        $aadhaarNumber = preg_replace('/[\s-]+/', '', trim((string) $this->employeeValue($row, [
            'aadhaarnumber', 'aadharnumber', 'adhaarnumber', 'aadhaarno', 'aadharno', 'adhaarno', 'aadhaar', 'aadhar',
        ]))) ?: null;
        $panNumber = strtoupper(preg_replace('/\s+/', '', trim((string) $this->employeeValue($row, [
            'pannumber', 'panno', 'pancardnumber', 'pancardno', 'pancard', 'pan',
        ]))) ?: '');

        foreach (['aadhaar' => $aadhaarNumber, 'pan' => $panNumber ?: null] as $type => $number) {
            if ($number === null) {
                continue;
            }

            DB::table('tbl_usrdocument')->updateOrInsert(
                ['user_id' => $employeeCode, 'document_type' => $type],
                [
                    'user_type' => $employeeTypeId,
                    'document_number' => $number,
                    'document_name' => strtoupper($type),
                    'status' => true,
                    'created_by' => $this->requestedBy,
                    'updated_by' => $this->requestedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function extractApiItems(mixed $json, string $apiCode): array
    {
        if (! is_array($json)) {
            throw new RuntimeException('API response is not valid JSON data.');
        }

        $paths = in_array($apiCode, ['employee', 'cust', 'customer'], true)
            ? [
                'd.results', 'CustomerMasterDetails', 'customerMasterDetails', 'Customers', 'customers',
                'EmployeeMasterDetails', 'employeeMasterDetails', 'EmployeeDetails', 'employeeDetails',
                'Employees', 'employees', 'Result.EmployeeMasterDetails', 'result.employeeMasterDetails',
                'Data.EmployeeMasterDetails', 'data.employeeMasterDetails', 'data', 'value',
            ]
            : ['d.results', 'value', 'data'];

        foreach ($paths as $path) {
            $candidate = data_get($json, $path);
            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        if (array_is_list($json)) {
            return $json;
        }

        if ($apiCode === 'customer_sales_summary') {
            $entity = data_get($json, 'd');
            if (is_array($entity) && ! array_is_list($entity)) {
                return [$entity];
            }
        }

        throw new RuntimeException("API response does not contain a record list for [{$apiCode}].");
    }

    private function normalizeEmployeeRow(array $item): array
    {
        $normalized = [];
        foreach ($item as $key => $value) {
            $normalized[strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $key))] = $value;
        }

        return $normalized;
    }

    private function employeeValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function nullableEmployeeString(array $row, array $keys): ?string
    {
        $value = trim((string) $this->employeeValue($row, $keys));

        return $value !== '' ? $value : null;
    }

    /** @return array{string, string|null, string} */
    private function splitEmployeeName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $firstName = array_shift($parts) ?? '';
        $lastName = count($parts) > 0 ? array_pop($parts) : '';
        $middleName = $parts !== [] ? implode(' ', $parts) : null;

        return [$firstName, $middleName, $lastName];
    }

    private function resolveMasterId(string $table, string $codeColumn, string $nameColumn, string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $id = DB::table($table)
            ->whereRaw("LOWER({$codeColumn}) = ?", [strtolower($value)])
            ->orWhereRaw("LOWER({$nameColumn}) = ?", [strtolower($value)])
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function sapIdOrMasterId(
        string $sapValue,
        string $table,
        string $codeColumn,
        string $nameColumn,
        string $description = '',
    ): ?int {
        if ($sapValue !== '' && ctype_digit($sapValue)) {
            return (int) $sapValue;
        }

        return $this->resolveMasterId($table, $codeColumn, $nameColumn, $sapValue ?: $description);
    }

    /** @return array{int, int, int} */
    private function importPendingSales(array $items): array
    {
        $created = $updated = $skipped = 0;
        $now = now();

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $source = array_change_key_case($item, CASE_LOWER);
            $customerNumber = trim((string) ($source['kunnr'] ?? ''));
            $plantCode = trim((string) ($source['werks'] ?? ''));
            $salesDocument = trim((string) ($source['vbeln'] ?? ''));
            $materialNumber = trim((string) ($source['matnr'] ?? ''));
            if ($customerNumber === '' || $plantCode === '' || $salesDocument === '' || $materialNumber === '') {
                $skipped++;

                continue;
            }

            $identity = [
                'customer_number' => $customerNumber,
                'plant_code' => $plantCode,
                'sales_document_number' => $salesDocument,
                'material_number' => $materialNumber,
            ];
            $exists = DB::table('tbl_pending_sales')->where($identity)->exists();
            $rawDate = trim((string) ($source['erdat'] ?? ''));
            $documentDate = null;
            if (preg_match('/^\d{8}$/', $rawDate) === 1) {
                try {
                    $documentDate = CarbonImmutable::createFromFormat('Ymd', $rawDate)->toDateString();
                } catch (Throwable) {
                    $documentDate = null;
                }
            } else {
                $documentDate = $this->normalizeApiDate($rawDate);
            }

            DB::table('tbl_pending_sales')->updateOrInsert($identity, [
                'document_date' => $documentDate,
                'external_material_group' => $this->nullableEmployeeString($source, ['extwg']),
                'material_group' => $this->nullableEmployeeString($source, ['matkl']),
                'order_quantity' => $this->apiNumber($source['kwmeng'] ?? null),
                'amount' => $this->apiNumber($source['amount'] ?? null),
                'open_delivery_amount' => $this->apiNumber($source['open_del_amt'] ?? null),
                'delivered_quantity' => $this->apiNumber($source['lfimg'] ?? null),
                'material_text_1' => $this->nullableEmployeeString($source, ['vtext1']),
                'material_text_2' => $this->nullableEmployeeString($source, ['vtext2']),
                'material_text_3' => $this->nullableEmployeeString($source, ['vtext3']),
                'api_payload' => json_encode($item, JSON_THROW_ON_ERROR),
                'synced_at' => $now,
                'status' => true,
                'created_by' => $this->requestedBy,
                'updated_by' => $this->requestedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $exists ? $updated++ : $created++;
        }

        return [$created, $updated, $skipped];
    }

    /** @return array{int, int, int} */
    private function importSalesData(array $items): array
    {
        $skipped = 0;
        $now = now();
        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $source = array_change_key_case($item, CASE_LOWER);
            $billingDocument = trim((string) ($source['vbeln'] ?? ''));
            $customerNumber = trim((string) ($source['kunag'] ?? ''));
            $plantCode = trim((string) ($source['werks'] ?? ''));
            $materialNumber = trim((string) ($source['matnr'] ?? ''));

            if ($billingDocument === '' || $customerNumber === '' || $plantCode === '' || $materialNumber === '') {
                $skipped++;

                continue;
            }

            $identity = [
                'billing_document_number' => $billingDocument,
                'customer_number' => $customerNumber,
                'plant_code' => $plantCode,
                'material_number' => $materialNumber,
            ];
            $billingDate = $this->normalizeApiDate($source['fkdat'] ?? null);
            $billingPeriod = $billingDate ? CarbonImmutable::parse($billingDate) : null;

            $row = array_merge($identity, [
                'billing_type' => $this->nullableEmployeeString($source, ['fkart']),
                'billing_date' => $billingDate,
                'billing_year' => $billingPeriod?->year,
                'billing_month' => $billingPeriod?->month,
                'record_creation_date' => $this->normalizeApiDate($source['erdat'] ?? null),
                'company_code' => $this->nullableEmployeeString($source, ['bukrs']),
                'customer_name' => $this->nullableEmployeeString($source, ['name1']),
                'material_description' => $this->nullableEmployeeString($source, ['maktx']),
                'material_group' => $this->nullableEmployeeString($source, ['matkl']),
                'material_group_description' => $this->nullableEmployeeString($source, ['wgbez']),
                'product_hierarchy' => $this->nullableEmployeeString($source, ['prodh']),
                'product_hierarchy_description' => $this->nullableEmployeeString($source, ['vtext']),
                'external_material_group' => $this->nullableEmployeeString($source, ['extwg']),
                'external_material_group_description' => $this->nullableEmployeeString($source, ['ewbez']),
                'billed_quantity' => $this->apiNumber($source['fkimg'] ?? null),
                'billed_quantity_kg' => $this->apiNumber($source['kgfkimg'] ?? null),
                'billed_quantity_liters' => $this->apiNumber($source['lfkimg'] ?? null),
                'billed_quantity_meters' => $this->apiNumber($source['mfkimg'] ?? null),
                'sales_unit' => $this->nullableEmployeeString($source, ['vrkme']),
                'base_unit' => $this->nullableEmployeeString($source, ['meins']),
                'net_value' => $this->apiNumber($source['netwr'] ?? null),
                'tax_amount' => $this->apiNumber($source['mwsbp'] ?? null),
                'fiscal_year' => $this->nullableEmployeeString($source, ['gjahr']),
                'condition_value' => $this->apiNumber($source['kwert'] ?? null),
                'api_payload' => json_encode($item, JSON_THROW_ON_ERROR),
                'synced_at' => $now,
                'status' => true,
                'created_by' => $this->requestedBy,
                'updated_by' => $this->requestedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $rows[implode('|', $identity)] = $row;
        }

        $beforeCount = DB::table('tbl_dailysale')->count();
        $updateColumns = array_values(array_diff(array_keys(reset($rows) ?: []), [
            'billing_document_number', 'customer_number', 'plant_code', 'material_number', 'created_at',
        ]));
        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            DB::table('tbl_dailysale')->upsert(
                $chunk,
                ['billing_document_number', 'customer_number', 'plant_code', 'material_number'],
                $updateColumns,
            );
        }
        if (Schema::hasTable('tbl_customer_company_map')) {
            $maps = collect($rows)
                ->filter(fn (array $row): bool => filled($row['customer_number']) && filled($row['company_code']))
                ->map(fn (array $row): array => [
                    'customer_number' => $row['customer_number'],
                    'company_code' => $row['company_code'],
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->unique(fn (array $row): string => $row['customer_number'].'|'.$row['company_code'])
                ->values();
            foreach ($maps->chunk(1000) as $chunk) {
                DB::table('tbl_customer_company_map')->upsert(
                    $chunk->all(),
                    ['customer_number', 'company_code'],
                    ['synced_at', 'updated_at'],
                );
            }
        }
        $created = max(0, DB::table('tbl_dailysale')->count() - $beforeCount);
        $updated = max(0, count($rows) - $created);

        return [$created, $updated, $skipped];
    }

    /** @return array{int, int, int} */
    private function importCustomerSalesSummary(array $items): array
    {
        $created = $updated = $skipped = 0;
        $now = now();

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $source = array_change_key_case($item, CASE_LOWER);
            $customerNumber = trim((string) ($source['kunnr'] ?? ''));
            if ($customerNumber === '') {
                $skipped++;

                continue;
            }

            $identity = ['customer_number' => $customerNumber];
            $exists = DB::table('tbl_customer_sales_summary')->where($identity)->exists();

            DB::table('tbl_customer_sales_summary')->updateOrInsert($identity, [
                'year_to_date_sales' => $this->apiNumber($source['ytdsales'] ?? null),
                'last_year_sales' => $this->apiNumber($source['lastysales'] ?? null),
                'pending_sales' => $this->apiNumber($source['pendingsales'] ?? null),
                'last_month_sales' => $this->apiNumber($source['lastmsales'] ?? null),
                'current_month_sales' => $this->apiNumber($source['currmsales'] ?? null),
                'two_months_ago_sales' => $this->apiNumber($source['ltolastsales'] ?? null),
                'last_month_outstanding' => $this->apiNumber($source['lastmout'] ?? null),
                'current_month_outstanding' => $this->apiNumber($source['currmout'] ?? null),
                'two_months_ago_outstanding' => $this->apiNumber($source['ltolastmout'] ?? null),
                'last_month_overdue' => $this->apiNumber($source['lastmoverdue'] ?? null),
                'current_month_overdue' => $this->apiNumber($source['currmoverdue'] ?? null),
                'two_months_ago_overdue' => $this->apiNumber($source['ltolastmoverdue'] ?? null),
                'last_month_payment_received' => $this->apiNumber($source['lastmpayrec'] ?? null),
                'current_month_payment_received' => $this->apiNumber($source['currmpayrec'] ?? null),
                'two_months_ago_payment_received' => $this->apiNumber($source['ltolastmpayrec'] ?? null),
                'payment_terms_code' => $this->nullableEmployeeString($source, ['zterm']),
                'payment_terms_description' => $this->nullableEmployeeString($source, ['text1']),
                'credit_limit' => $this->apiNumber($source['credit_limit'] ?? null),
                'api_payload' => json_encode($item, JSON_THROW_ON_ERROR),
                'synced_at' => $now,
                'status' => true,
                'created_by' => $this->requestedBy,
                'updated_by' => $this->requestedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $exists ? $updated++ : $created++;
        }

        return [$created, $updated, $skipped];
    }

    /** @return array{int, int} */
    private function importCustomerCollections(array $items, string $fromDate, string $toDate): array
    {
        $rows = [];
        $skipped = 0;
        $now = now();
        $customerNumber = trim((string) $this->customerNumber);

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;
                continue;
            }

            $source = array_change_key_case($item, CASE_LOWER);
            $postingDate = $this->normalizeApiDate($source['budat'] ?? null);
            $documentType = strtoupper(trim((string) ($source['blart'] ?? '')));
            $amount = abs($this->apiNumber($source['dmbtr'] ?? null));
            if ($postingDate === null || $documentType !== 'DZ' || $amount <= 0) {
                $skipped++;
                continue;
            }

            $sourceCustomer = trim((string) ($source['kunnr'] ?? $customerNumber));
            $storedCustomer = ctype_digit($sourceCustomer) ? (ltrim($sourceCustomer, '0') ?: '0') : $sourceCustomer;
            $identity = [
                $storedCustomer,
                trim((string) ($source['bukrs'] ?? '')),
                trim((string) ($source['belnr'] ?? '')),
                $postingDate,
                number_format($amount, 3, '.', ''),
                trim((string) ($source['sgtxt'] ?? '')),
            ];
            $rows[] = [
                'customer_number' => $identity[0],
                'company_code' => $identity[1] ?: null,
                'document_number' => $identity[2] ?: null,
                'fiscal_year' => is_numeric($source['gjahr'] ?? null) ? (int) $source['gjahr'] : null,
                'posting_date' => $postingDate,
                'document_date' => $this->normalizeApiDate($source['bldat'] ?? null),
                'document_type' => $documentType,
                'amount_received' => $amount,
                'currency' => trim((string) ($source['waers'] ?? '')) ?: null,
                'reference_number' => trim((string) ($source['xblnr'] ?? '')) ?: null,
                'description' => trim((string) ($source['sgtxt'] ?? '')) ?: null,
                'source_hash' => hash('sha256', implode('|', $identity)),
                'api_payload' => json_encode($item, JSON_THROW_ON_ERROR),
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $delete = DB::table('tbl_customer_collection_transactions');
        if ($this->fromDate !== null || $this->toDate !== null) {
            $delete->whereBetween('posting_date', [$fromDate, $toDate]);
        } else {
            $delete->whereRaw('CAST(customer_number AS UNSIGNED) = CAST(? AS UNSIGNED)', [$customerNumber]);
        }
        $delete->delete();

        $created = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $created += DB::table('tbl_customer_collection_transactions')->insertOrIgnore($chunk);
        }

        return [$created, $skipped];
    }

    /** @return array{int, int} */
    private function importGenericTable(string $table, array $items): array
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || ! Schema::hasTable($table)) {
            throw new RuntimeException("Target table [{$table}] does not exist.");
        }

        $columns = Schema::getColumnListing($table);
        $writableColumns = array_values(array_diff($columns, ['id', 'created_by', 'updated_by']));
        $rows = [];
        $skipped = 0;
        $now = now();

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $source = array_change_key_case($item, CASE_LOWER);
            $row = array_intersect_key($source, array_flip($writableColumns));
            if (in_array('api_payload', $writableColumns, true)) {
                $row['api_payload'] = json_encode($item);
            }
            if (in_array('status', $writableColumns, true) && ! array_key_exists('status', $row)) {
                $row['status'] = true;
            }
            if (in_array('created_at', $writableColumns, true)) {
                $row['created_at'] = $now;
            }
            if (in_array('updated_at', $writableColumns, true)) {
                $row['updated_at'] = $now;
            }

            if ($row === []) {
                $skipped++;

                continue;
            }
            $rows[] = $row;
        }

        $groups = collect($rows)->groupBy(function (array $row): string {
            $keys = array_keys($row);
            sort($keys);

            return implode('|', $keys);
        });
        foreach ($groups as $group) {
            foreach (array_chunk($group->all(), 500) as $batch) {
                DB::table($table)->insert($batch);
            }
        }

        return [count($rows), $skipped];
    }

    /** @return array{int, int} */
    private function importStock(array $items, bool $consumptionOnly = false): array
    {
        $batch = [];
        $synchronized = 0;
        $skipped = 0;
        $syncedAt = now();

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $item = array_change_key_case($item, CASE_LOWER);
            $matnr = trim((string) ($item['material_code'] ?? $item['matnr'] ?? ''));
            $plantCode = trim((string) ($item['plant'] ?? $item['plant_code'] ?? $item['werks'] ?? ''));
            if ($matnr === '' || $plantCode === '') {
                $skipped++;

                continue;
            }

            $row = [
                'matnr' => $matnr,
                'plant_code' => $plantCode,
                'status' => true,
                'synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ];

            if ($consumptionOnly) {
                $apiNumber = static function (mixed $value): float {
                    $normalized = str_replace([',', ' '], '', trim((string) $value));

                    return is_numeric($normalized) ? (float) $normalized : 0.0;
                };
                $currentMonth = $apiNumber($item['m_consump'] ?? 0);
                $previousMonth = $apiNumber($item['l_consump'] ?? 0);
                $twoMonthsAgo = $apiNumber($item['ll_consump'] ?? 0);
                $monthlyAverage = ($currentMonth + $previousMonth + $twoMonthsAgo) / 3;

                $row['sap_current_month_consumption'] = round($currentMonth, 3);
                $row['sap_previous_month_consumption'] = round($previousMonth, 3);
                $row['sap_two_months_ago_consumption'] = round($twoMonthsAgo, 3);
                $row['auto_monthly_consumption'] = round($monthlyAverage, 3);
                $row['auto_perday_consumption'] = round($monthlyAverage / CarbonImmutable::now()->daysInMonth, 3);
            } else {
                $row['material_desc'] = trim((string) ($item['material_desc'] ?? $item['maktx'] ?? '')) ?: null;
                $row['current_stock'] = (float) ($item['current_stock'] ?? $item['labst'] ?? 0);
                $row['stock_value'] = (float) ($item['stock_value'] ?? 0);
            }

            $batch[$matnr."\0".$plantCode] = $row;

            if (count($batch) >= 500) {
                $synchronized += $this->upsertStockBatch(array_values($batch));
                $batch = [];
            }
        }

        if ($batch !== []) {
            $synchronized += $this->upsertStockBatch(array_values($batch));
        }

        return [$synchronized, $skipped];
    }

    private function upsertStockBatch(array $rows): int
    {
        $updateColumns = isset($rows[0]['auto_perday_consumption'])
            ? [
                'sap_current_month_consumption',
                'sap_previous_month_consumption',
                'sap_two_months_ago_consumption',
                'auto_monthly_consumption',
                'auto_perday_consumption',
                'status',
                'synced_at',
                'updated_at',
            ]
            : ['material_desc', 'current_stock', 'stock_value', 'status', 'synced_at', 'updated_at'];

        MaterialStock::upsert(
            $rows,
            ['matnr', 'plant_code'],
            $updateColumns,
        );

        return count($rows);
    }

    /** @return array{int, int, int} */
    private function importVendorPurchaseOrders(array $items, bool $importVendors = true, bool $importPurchaseOrders = true): array
    {
        $vendors = [];
        $purchaseOrders = [];
        $skipped = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $row = array_change_key_case($item, CASE_LOWER);
            $poNumber = trim((string) ($row['purchaseorder'] ?? $row['purchase_order'] ?? $row['ebeln'] ?? $row['po_number'] ?? ''));
            $itemNumber = trim((string) ($row['purchaseorderitem'] ?? $row['purchase_order_item'] ?? $row['ebelp'] ?? $row['item_number'] ?? '00010'));
            $vendorCode = trim((string) ($row['supplier'] ?? $row['vendor'] ?? $row['vendor_code'] ?? $row['lifnr'] ?? ''));
            $vendorName = trim((string) ($row['suppliername'] ?? $row['vendor_name'] ?? $row['name1'] ?? $vendorCode));
            $material = trim((string) ($row['material'] ?? $row['material_code'] ?? $row['matnr'] ?? ''));
            $plantCode = trim((string) ($row['plant'] ?? $row['plant_code'] ?? $row['werks'] ?? ''));

            if ($poNumber === '' || $material === '' || $plantCode === '') {
                $skipped++;

                continue;
            }

            $quantity = (float) ($row['orderquantity'] ?? $row['purchaseorderquantity'] ?? $row['order_quantity'] ?? $row['menge'] ?? $row['quantity'] ?? 0);
            $receivedQuantity = (float) ($row['receivedquantity'] ?? $row['received_quantity'] ?? $row['wemng'] ?? 0);
            $unit = strtoupper(trim((string) ($row['purchaseorderquantityunit'] ?? $row['orderquantityunit'] ?? $row['order_unit'] ?? $row['meins'] ?? 'MT')));
            $quantityMt = $this->convertToMt($quantity, $unit);
            $receivedQuantityMt = $this->convertToMt($receivedQuantity, $unit);
            $amount = (float) ($row['netamount'] ?? $row['netvalue'] ?? $row['amount'] ?? $row['netwr'] ?? $row['poprice'] ?? 0);
            $rate = (float) ($row['netpriceamount'] ?? $row['netprice'] ?? $row['unitprice'] ?? $row['rate'] ?? 0);
            if ($rate <= 0 && $amount > 0 && $quantityMt > 0) {
                $rate = $amount / $quantityMt;
            }
            $currency = strtoupper(trim((string) ($row['documentcurrency'] ?? $row['currency'] ?? $row['currencycode'] ?? $row['waers'] ?? '')));
            $cancelled = filled($row['deletionindicator'] ?? $row['deletion_flag'] ?? $row['loekz'] ?? null);
            $completed = in_array(strtoupper((string) ($row['deliverycompleted'] ?? $row['elikz'] ?? '')), ['1', 'X', 'TRUE'], true);
            $status = $cancelled ? 'cancelled' : ($completed || ($quantity > 0 && $receivedQuantity >= $quantity) ? 'received' : ($receivedQuantity > 0 ? 'partially_received' : 'planned'));
            $now = now();

            if ($vendorCode !== '') {
                $vendors[$vendorCode] = [
                    'vendor_code' => $vendorCode,
                    'vendor_name' => $vendorName ?: $vendorCode,
                    'company_code' => trim((string) ($row['companycode'] ?? $row['company_code'] ?? $row['bukrs'] ?? '')) ?: null,
                    'status' => true,
                    'api_payload' => json_encode($item),
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $purchaseOrders[$poNumber."\0".$itemNumber] = [
                'po_number' => $poNumber,
                'item_number' => $itemNumber ?: '00010',
                'vendor_code' => $vendorCode ?: null,
                'vendor_name' => $vendorName ?: null,
                'company_code' => trim((string) ($row['companycode'] ?? $row['company_code'] ?? $row['bukrs'] ?? '')) ?: null,
                'material' => $material,
                'plant_code' => $plantCode,
                'order_date' => $this->normalizeApiDate($row['purchaseorderdate'] ?? $row['order_date'] ?? $row['bedat'] ?? null),
                'expected_delivery_date' => $this->normalizeApiDate($row['deliverydate'] ?? $row['expected_delivery_date'] ?? $row['eindt'] ?? null),
                'order_quantity' => $quantity,
                'order_unit' => $unit ?: null,
                'quantity_mt' => $quantityMt,
                'received_quantity_mt' => $receivedQuantityMt,
                'rate' => $rate,
                'amount' => $amount,
                'currency' => $currency ?: null,
                'delivery_status' => $status,
                'status' => ! $cancelled,
                'api_payload' => json_encode($item),
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($importVendors && $vendors !== []) {
            foreach (array_chunk(array_values($vendors), 250) as $vendorBatch) {
                DB::table('tbl_vendors')->upsert($vendorBatch, ['vendor_code'], ['vendor_name', 'company_code', 'status', 'api_payload', 'synced_at', 'updated_at']);
            }
        }
        if ($importPurchaseOrders && $purchaseOrders !== []) {
            foreach (array_chunk(array_values($purchaseOrders), 250) as $purchaseOrderBatch) {
                DB::table('tbl_vendor_purchase_orders')->upsert($purchaseOrderBatch, ['po_number', 'item_number'], ['vendor_code', 'vendor_name', 'company_code', 'material', 'plant_code', 'order_date', 'expected_delivery_date', 'order_quantity', 'order_unit', 'quantity_mt', 'received_quantity_mt', 'rate', 'amount', 'currency', 'delivery_status', 'status', 'api_payload', 'synced_at', 'updated_at']);
            }
        }

        return [$importPurchaseOrders ? count($purchaseOrders) : 0, $importVendors ? count($vendors) : 0, $skipped];
    }

    private function convertToMt(float $quantity, string $unit): float
    {
        return match ($unit) {
            'KG', 'KGM' => $quantity / 1000,
            'G', 'GRM' => $quantity / 1000000,
            'MT', 'MTO', 'TON', 'TO', 'T' => $quantity,
            default => 0.0,
        };
    }

    private function normalizeApiDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        if (preg_match('/\/Date\((\d+)/', (string) $value, $matches)) {
            return CarbonImmutable::createFromTimestampMs((int) $matches[1])->toDateString();
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
