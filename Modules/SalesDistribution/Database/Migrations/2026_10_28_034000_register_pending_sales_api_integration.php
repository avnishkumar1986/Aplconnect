<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_api_integrations')->updateOrInsert(
            ['code' => 'pending_sales'],
            [
                'name' => 'Pending Order',
                'description' => 'Import SAP pending order details for customer 2000789',
                'api_type' => 'sap',
                'endpoint' => "sap/opu/odata/sap/ZPENDING_SALE_DETAILS_SRV/ZCUST_PENDSALE_DETAILS_STSet?\$filter=Kunnr%20eq%20%272000789%27&\$format=json",
                'target_table' => json_encode(['tbl_pending_sales'], JSON_THROW_ON_ERROR),
                'import_mode' => 'direct',
                'chunk_size' => 100,
                'chunk_offset' => 0,
                'retries' => 3,
                'retry_delay_seconds' => 10,
                'timeout_seconds' => 300,
                'status' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('tbl_api_integrations')->where('code', 'pending_sales')->delete();
    }
};
