<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_procurement_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('matnr', 50);
            $table->string('maktx', 255)->nullable();
            $table->string('mtart', 20)->nullable();
            $table->string('matkl', 40)->nullable();
            $table->string('meins', 10)->nullable();
            $table->string('plant_code', 20);
            $table->decimal('current_stock', 20, 3)->default(0);
            $table->decimal('stock_value', 20, 3)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['matnr', 'plant_code'], 'procurement_stocks_matnr_plant_unique');
            $table->index(['matkl', 'plant_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_procurement_stocks');
    }
};
