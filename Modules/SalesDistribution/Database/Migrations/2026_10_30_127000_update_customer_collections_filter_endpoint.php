<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_api_integrations')
            ->where('code', 'customer_collections')
            ->update([
                'endpoint' => "sap/opu/odata/sap/ZCUST_DATA_SRV/CUST_DATASet?\$filter=Kunnr%20eq%20%27{{customer_number}}%27&\$format=json",
                'description' => 'Import SAP DZ customer payment receipts using the Kunnr filter and summarize Collections by posting month',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('tbl_api_integrations')
            ->where('code', 'customer_collections')
            ->update([
                'endpoint' => "sap/opu/odata/sap/ZCUST_DATA_SRV/CUST_DATASet(%27{{customer_number}}%27)?\$format=json",
                'updated_at' => now(),
            ]);
    }
};
