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
            $table->string('period_type', 20)->default('monthly')->after('effective_to')->index();
        });
        DB::table('procurement_daily_consumptions')->whereNotNull('effective_to')->update(['period_type' => 'datewise']);
    }

    public function down(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->dropColumn('period_type');
        });
    }
};
