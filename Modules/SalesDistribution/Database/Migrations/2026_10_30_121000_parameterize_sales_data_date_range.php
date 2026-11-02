<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_api_integrations')
            ->where('code', 'sales_data')
            ->update([
                'description' => 'Import SAP billing and sales data for a requested billing-date range',
                'endpoint' => "sap/opu/odata/sap/ZSALES_DATA_SRV/zcust_salesSet?\$filter=Fkdat%20ge%20datetime%27{{from_date}}T00:00:00%27%20and%20Fkdat%20le%20datetime%27{{to_date}}T00:00:00%27&\$format=json",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('tbl_api_integrations')
            ->where('code', 'sales_data')
            ->update([
                'description' => 'Import SAP billing and sales data for the current date',
                'endpoint' => "sap/opu/odata/sap/ZSALES_DATA_SRV/zcust_salesSet?\$filter=Fkdat%20ge%20datetime%27{{today}}T00:00:00%27%20and%20Fkdat%20le%20datetime%27{{today}}T00:00:00%27&\$format=json",
                'updated_at' => now(),
            ]);
    }
};
