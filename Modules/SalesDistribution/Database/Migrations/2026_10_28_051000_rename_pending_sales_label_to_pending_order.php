<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_api_integrations')
            ->where('code', 'pending_sales')
            ->update([
                'name' => 'Pending Order',
                'description' => 'Import SAP pending order details for customer 2000789',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('tbl_api_integrations')
            ->where('code', 'pending_sales')
            ->update([
                'name' => 'Pending Sales',
                'description' => 'Import SAP pending sales details for customer 2000789',
                'updated_at' => now(),
            ]);
    }
};
