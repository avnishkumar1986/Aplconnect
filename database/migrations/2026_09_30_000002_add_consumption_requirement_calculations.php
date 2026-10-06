<?php

use App\Services\ConsumptionRequirementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->decimal('current_stock_mt', 20, 3)->nullable();
            $table->decimal('daily_consumption_mt', 20, 6)->default(0);
            $table->decimal('extra_required_mt', 20, 3)->nullable();
            $table->timestamp('stock_calculated_at')->nullable();
        });
        app(ConsumptionRequirementService::class)->refresh(null, false);
    }

    public function down(): void
    {
        Schema::table('procurement_daily_consumptions', fn (Blueprint $table) =>
            $table->dropColumn(['current_stock_mt', 'daily_consumption_mt', 'extra_required_mt', 'stock_calculated_at']));
    }
};
