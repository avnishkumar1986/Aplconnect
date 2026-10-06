<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_material_stocks', function (Blueprint $table) {
            $table->string('plant_code', 20)->collation('utf8mb4_general_ci')->change();
        });
        Schema::table('tbl_procurement_stocks', function (Blueprint $table) {
            $table->string('plant_code', 20)->collation('utf8mb4_general_ci')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_material_stocks', function (Blueprint $table) {
            $table->string('plant_code', 20)->collation('utf8mb4_unicode_ci')->change();
        });
        Schema::table('tbl_procurement_stocks', function (Blueprint $table) {
            $table->string('plant_code', 20)->collation('utf8mb4_unicode_ci')->change();
        });
    }
};
