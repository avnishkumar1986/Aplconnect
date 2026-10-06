<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('tbl_vendors')) return;

        // These values join tbl_company.company_code and tbl_material_stocks.plant_code,
        // both of which use utf8mb4_general_ci.
        DB::statement('ALTER TABLE `tbl_vendors`
            MODIFY `company_code` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
            MODIFY `plant_code` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('tbl_vendors')) return;

        DB::statement('ALTER TABLE `tbl_vendors`
            MODIFY `company_code` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            MODIFY `plant_code` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
    }
};
