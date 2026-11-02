<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_api_integrations')
            ->where('code', 'customer_sales_summary')
            ->update([
                'description' => 'Import SAP customer-wise sales summaries one customer at a time from active USR4 users',
                'endpoint' => "sap/opu/odata/sap/ZCUST_SALE_HEADER_SRV/zcustwise_saleSet(%27{{customer_number}}%27)?\$format=json",
                'import_mode' => 'direct',
                'chunk_size' => 1,
                'chunk_offset' => 0,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('tbl_api_integrations')
            ->where('code', 'customer_sales_summary')
            ->update([
                'endpoint' => "sap/opu/odata/sap/ZCUST_SALE_HEADER_SRV/zcustwise_saleSet(%272000789%27)?\$format=json",
                'updated_at' => now(),
            ]);
    }
};
