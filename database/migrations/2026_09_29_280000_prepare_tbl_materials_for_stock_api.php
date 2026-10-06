<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_materials')->where('matnr', '')->delete();

        Schema::table('tbl_materials', function (Blueprint $table) {
            $table->dropUnique('procurement_materials_matnr_unique');
            $table->string('mtart', 20)->nullable()->change();
            $table->string('matkl', 40)->nullable()->change();
            $table->string('meins', 10)->nullable()->change();
            $table->string('plant', 10)->nullable()->after('maktx');
            $table->decimal('current_stock', 20, 3)->default(0)->after('plant');
            $table->decimal('stock_value', 20, 3)->default(0)->after('current_stock');
            $table->unique(['matnr', 'plant'], 'tbl_materials_matnr_plant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_materials', function (Blueprint $table) {
            $table->dropUnique('tbl_materials_matnr_plant_unique');
            $table->dropColumn(['plant', 'current_stock', 'stock_value']);
            $table->unique('matnr', 'procurement_materials_matnr_unique');
        });
    }
};
