<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_api_integrations')->where('code', 'customer_collections')->update([
            'endpoint' => "sap/opu/odata/sap/ZCUST_DATA_SRV/CUST_DATASet?\$filter=Budat%20ge%20datetime%27{{from_date}}T00:00:00%27%20and%20Budat%20le%20datetime%27{{to_date}}T00:00:00%27&\$format=json",
            'description' => 'Import SAP DZ payment receipts month by month using the Budat posting-date filter',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('tbl_api_integrations')->where('code', 'customer_collections')->update([
            'endpoint' => "sap/opu/odata/sap/ZCUST_DATA_SRV/CUST_DATASet?\$filter=Kunnr%20eq%20%27{{customer_number}}%27&\$format=json",
            'updated_at' => now(),
        ]);
    }
};
