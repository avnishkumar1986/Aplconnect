<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('material_code', 50);
            $table->string('vendor_code', 50);
            $table->string('vendor_name')->nullable();
            $table->string('purchase_order', 50)->nullable();
            $table->string('purchase_order_item', 20)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamps();

            $table->unique(['material_code', 'vendor_code'], 'vendors_material_vendor_unique');
            $table->index('vendor_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_vendors');
    }
};
