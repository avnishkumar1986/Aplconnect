<?php

namespace App\Console\Commands;

use App\Models\ApiIntegration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SyncVendorsForMaterial extends Command
{
    protected $signature = 'sap:sync-vendors {material : SAP material code}';

    protected $description = 'Import SAP vendors associated with one material code';

    public function handle(): int
    {
        $materialCode = trim((string) $this->argument('material'));
        if ($materialCode === '') {
            $this->error('A material code is required.');
            return self::FAILURE;
        }

        $profile = ApiIntegration::currentEnvironmentProfile();
        if (! $profile || blank($profile->base_url)) {
            $this->error('No SAP base URL is configured for the current environment.');
            return self::FAILURE;
        }

        $username = config('api-integrations.username');
        $password = config('api-integrations.password');
        if (blank($username) || blank($password)) {
            $this->error('SAP API username or password is not configured.');
            return self::FAILURE;
        }

        $escapedCode = str_replace("'", "''", $materialCode);
        $query = http_build_query([
            '$filter' => "Material eq '{$escapedCode}'",
            '$format' => 'json',
        ], '', '&', PHP_QUERY_RFC3986);
        $url = rtrim($profile->base_url, '/')
            .'/sap/opu/odata/sap/ZPO_ITEM_ODATA_SRV/PurchaseOrderItemSet?'.$query;

        try {
            $response = Http::timeout(1800)
                ->withOptions(['verify' => (bool) $profile->verify_ssl])
                ->acceptJson()
                ->withBasicAuth($username, $password)
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("SAP API returned HTTP {$response->status()}.");
            }

            $decoded = $response->json();
            if (! is_array($decoded)) throw new RuntimeException('SAP response is not valid JSON.');
            $records = data_get($decoded, 'd.results') ?? data_get($decoded, 'value') ?? [];
            if (! is_array($records)) $records = [];

            $rows = $this->vendorRows($materialCode, $records);
            if ($rows) {
                DB::table('tbl_vendors')->upsert(
                    $rows,
                    ['purchase_order', 'purchase_order_item'],
                    [
                        'material_code', 'vendor_code', 'vendor_name', 'delivery_date', 'short_text',
                        'company_code', 'plant_code', 'mt_quantity', 'base_unit', 'quantity',
                        'purchase_order_type', 'release_state', 'status', 'synced_at', 'source_payload', 'updated_at',
                    ]
                );
            }

            $this->info(count($rows).' vendor(s) saved for material '.$materialCode.'.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function vendorRows(string $requestedMaterialCode, array $records): array
    {
        $timestamp = now()->toDateTimeString();
        $rows = [];

        foreach ($records as $record) {
            if (! is_array($record)) continue;

            $materialCode = $this->sapValue($record, ['Material', 'MaterialCode', 'Matnr']) ?: $requestedMaterialCode;
            $vendorCode = $this->sapValue($record, ['Vendor', 'VendorCode', 'Lifnr', 'Supplier', 'SupplierCode']);
            if (blank($vendorCode)) continue;

            $purchaseOrder = $this->sapValue($record, ['Purchaseorder', 'PurchaseOrder', 'PurchasingDocument', 'Ebeln']);
            $purchaseOrderItem = $this->sapValue($record, ['Purchaseorderitem', 'PurchaseOrderItem', 'PurchasingDocumentItem', 'Ebelp']);
            if (blank($purchaseOrder) || blank($purchaseOrderItem)) continue;

            $rows[] = [
                'material_code' => (string) $materialCode,
                'vendor_code' => (string) $vendorCode,
                'vendor_name' => $this->sapValue($record, ['VendorName', 'VendorDescription', 'Name1', 'SupplierName']),
                'purchase_order' => (string) $purchaseOrder,
                'purchase_order_item' => (string) $purchaseOrderItem,
                'delivery_date' => $this->sapDate($this->sapValue($record, ['Deliverydate', 'DeliveryDate'])),
                'short_text' => $this->sapValue($record, ['Shorttext', 'ShortText']),
                'company_code' => $this->sapValue($record, ['Companycode', 'CompanyCode']),
                'plant_code' => $this->sapValue($record, ['Plant']),
                'mt_quantity' => $this->sapValue($record, ['MtQuantity']),
                'base_unit' => $this->sapValue($record, ['Baseunit', 'BaseUnit']),
                'quantity' => $this->sapValue($record, ['Quantity']),
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
        if (preg_match('/^\\/Date\\(([-]?[0-9]+)/', (string) $value, $matches)) {
            return \Illuminate\Support\Carbon::createFromTimestampMs((int) $matches[1])->toDateString();
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
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
}
