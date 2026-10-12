<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // These columns join directly to tbl_company.company_code, so their
        // length, character set and collation must match it exactly.
        DB::statement('ALTER TABLE procurement_daily_consumptions MODIFY company_id VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
        DB::statement('ALTER TABLE procurement_daily_consumptions MODIFY plant_id VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE procurement_daily_consumptions MODIFY company_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE procurement_daily_consumptions MODIFY plant_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }
};
