<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_daily_consumptions', fn (Blueprint $table) => $table->unsignedTinyInteger('day_count')->nullable()->after('consumption_date'));
    }

    public function down(): void
    {
        Schema::table('procurement_daily_consumptions', fn (Blueprint $table) => $table->dropColumn('day_count'));
    }
};
