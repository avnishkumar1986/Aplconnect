<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchases', function (Blueprint $table) {
            $table->foreignId('consumption_id')
                ->nullable()
                ->after('plant_id')
                ->constrained('procurement_daily_consumptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumption_id');
        });
    }
};
