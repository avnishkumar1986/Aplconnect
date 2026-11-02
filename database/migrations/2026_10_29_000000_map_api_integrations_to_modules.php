<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_api_integrations', 'module_id')) {
            Schema::table('tbl_api_integrations', function (Blueprint $table): void {
                $table->foreignId('module_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tbl_modules')
                    ->nullOnDelete();
            });
        }

        $mappings = [
            'procurement' => ['material', 'stock', 'vendor', 'mrmcon'],
            'sales-distribution' => ['cust', 'customer', 'customer_sales_summary', 'pending_sales', 'sales_data'],
            'production-capacity' => ['production', 'quality'],
            'group-overview' => ['employee'],
            'support' => ['security'],
        ];

        foreach ($mappings as $moduleSlug => $codes) {
            $moduleId = DB::table('tbl_modules')->where('slug', $moduleSlug)->value('id');

            if ($moduleId !== null) {
                DB::table('tbl_api_integrations')
                    ->whereIn('code', $codes)
                    ->update(['module_id' => $moduleId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_api_integrations', 'module_id')) {
            Schema::table('tbl_api_integrations', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('module_id');
            });
        }
    }
};
