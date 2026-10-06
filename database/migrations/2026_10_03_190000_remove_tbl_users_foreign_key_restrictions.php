<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $foreignKeys = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'tbl_users')
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->pluck('CONSTRAINT_NAME');

        foreach ($foreignKeys as $foreignKey) {
            DB::statement(sprintf(
                'ALTER TABLE `tbl_users` DROP FOREIGN KEY `%s`',
                str_replace('`', '``', $foreignKey),
            ));
        }
    }

    public function down(): void
    {
        // Restrictions are intentionally not recreated automatically because
        // rows may have been edited to contain values absent from parent tables.
    }
};
