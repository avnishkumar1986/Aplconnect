<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_materials') && ! Schema::hasTable('tbl_materials')) {
            Schema::rename('procurement_materials', 'tbl_materials');
        } elseif (Schema::hasTable('procurement_materials') && Schema::hasTable('tbl_materials') && DB::table('procurement_materials')->count() === 0) {
            Schema::drop('procurement_materials');
        }

        DB::table('api_integrations')->where('code', 'stock')->update([
            'tbl_name' => 'tbl_materials',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('api_integrations')->where('code', 'stock')->update([
            'tbl_name' => 'procurement_materials',
            'updated_at' => now(),
        ]);
    }
};
