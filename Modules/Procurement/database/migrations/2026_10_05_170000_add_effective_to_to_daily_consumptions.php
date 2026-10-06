<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->date('effective_to')->nullable()->after('consumption_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->dropColumn('effective_to');
        });
    }
};
