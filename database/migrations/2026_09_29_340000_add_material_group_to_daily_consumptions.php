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
            $table->string('material_group', 40)->nullable()->after('material');
            $table->index('material_group');
        });

        DB::table('procurement_daily_consumptions as consumption')
            ->join('tbl_materials as material', 'material.matnr', '=', 'consumption.material')
            ->whereNull('consumption.material_group')
            ->update(['consumption.material_group' => DB::raw('material.matkl')]);
    }

    public function down(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->dropIndex(['material_group']);
            $table->dropColumn('material_group');
        });
    }
};
