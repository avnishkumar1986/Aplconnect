<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_sales') && ! Schema::hasTable('tbl_dailysale')) {
            Schema::rename('tbl_sales', 'tbl_dailysale');
        }

        DB::table('tbl_api_integrations')
            ->where('code', 'sales_data')
            ->update([
                'target_table' => json_encode(['tbl_dailysale'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_dailysale') && ! Schema::hasTable('tbl_sales')) {
            Schema::rename('tbl_dailysale', 'tbl_sales');
        }

        DB::table('tbl_api_integrations')
            ->where('code', 'sales_data')
            ->update([
                'target_table' => json_encode(['tbl_sales'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }
};
