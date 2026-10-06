<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->decimal('planned_buying_mt', 20, 3)->default(0);
            $table->decimal('imports_mt', 20, 3)->default(0);
            $table->decimal('total_buying_mt', 20, 3)->default(0);
            $table->decimal('availability_mt', 20, 3)->nullable();
            $table->decimal('projected_closing_mt', 20, 3)->nullable();
            $table->decimal('stock_cover_days', 20, 3)->nullable();
        });
        Schema::create('procurement_balance_results', function (Blueprint $table) {
            $table->id();
            $table->string('material', 40);
            $table->integer('plant_id');
            $table->date('month');
            $table->json('calculations');
            $table->timestamps();
            $table->unique(['material', 'plant_id', 'month']);
        });
        Schema::create('procurement_plan_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consumption_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at');
            $table->unique(['consumption_id', 'version']);
            $table->foreign('consumption_id')->references('id')->on('procurement_daily_consumptions')->cascadeOnDelete();
        });
        Schema::create('procurement_mou_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consumption_id');
            $table->string('vendor_name');
            $table->string('vendor_code')->nullable();
            $table->decimal('quantity_mt', 14, 3);
            $table->string('remarks', 1000)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['consumption_id', 'vendor_name']);
            $table->foreign('consumption_id')->references('id')->on('procurement_daily_consumptions')->cascadeOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('procurement_mou_plans');
        Schema::dropIfExists('procurement_plan_histories');
        Schema::dropIfExists('procurement_balance_results');
        Schema::table('procurement_daily_consumptions', fn (Blueprint $table) => $table->dropColumn(['planned_buying_mt', 'imports_mt', 'total_buying_mt', 'availability_mt', 'projected_closing_mt', 'stock_cover_days']));
    }
};
