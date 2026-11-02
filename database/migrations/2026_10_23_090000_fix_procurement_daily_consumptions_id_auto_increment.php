<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            'procurement_daily_consumptions',
            'procurement_purchases',
            'procurement_balance_results',
            'procurement_mou_plans',
            'tbl_jobs',
            'tbl_failed_jobs',
            'tbl_vendors',
            'tbl_vendor_purchase_orders',
            'tbl_api_integration_logs',
            'tbl_material_stocks',
            'tbl_materials',
        ] as $table) {
            $this->repairIdColumn($table);
        }
    }

    public function down(): void
    {
        // Removing AUTO_INCREMENT could prevent new consumption records.
    }

    private function repairIdColumn(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $database = DB::connection()->getDatabaseName();
        $extra = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'id')
            ->value('EXTRA');

        if (str_contains(strtolower((string) $extra), 'auto_increment')) {
            return;
        }

        $hasIdIndex = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'id')
            ->exists();

        if (! $hasIdIndex) {
            $hasPrimaryKey = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', 'PRIMARY')
                ->exists();

            DB::statement($hasPrimaryKey
                ? "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$table}_id_unique` (`id`)"
                : "ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    }
};
