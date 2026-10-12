<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->string('company_code_value', 30)->collation('utf8mb4_general_ci')->nullable()->after('company_id');
            $table->string('plant_code_value', 30)->collation('utf8mb4_general_ci')->nullable()->after('plant_id');
        });

        DB::statement('UPDATE procurement_daily_consumptions c LEFT JOIN tbl_company company ON company.id = c.company_id LEFT JOIN tbl_company plant ON plant.id = c.plant_id SET c.company_code_value = COALESCE(company.company_code, CAST(c.company_id AS CHAR)), c.plant_code_value = COALESCE(plant.company_code, CAST(c.plant_id AS CHAR))');

        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['plant_id']);
            $table->dropIndex('procurement_consumption_scope_date');
            $table->dropColumn(['company_id', 'plant_id']);
        });
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->renameColumn('company_code_value', 'company_id');
            $table->renameColumn('plant_code_value', 'plant_id');
        });
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->index(['company_id', 'plant_id', 'consumption_date'], 'procurement_consumption_scope_date');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->unsignedBigInteger('company_database_id')->nullable()->after('company_id');
            $table->unsignedBigInteger('plant_database_id')->nullable()->after('plant_id');
        });

        DB::statement('UPDATE procurement_daily_consumptions c LEFT JOIN tbl_company company ON company.company_code = c.company_id LEFT JOIN tbl_company plant ON plant.company_code = c.plant_id SET c.company_database_id = company.id, c.plant_database_id = plant.id');

        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->dropIndex('procurement_consumption_scope_date');
            $table->dropColumn(['company_id', 'plant_id']);
        });
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->renameColumn('company_database_id', 'company_id');
            $table->renameColumn('plant_database_id', 'plant_id');
            $table->foreign('company_id')->references('id')->on('tbl_company')->restrictOnDelete();
            $table->foreign('plant_id')->references('id')->on('tbl_company')->restrictOnDelete();
            $table->index(['company_id', 'plant_id', 'consumption_date'], 'procurement_consumption_scope_date');
        });
    }
};
