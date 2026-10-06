<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_vendors')) {
            return;
        }

        Schema::create('tbl_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('material_code', 50);
            $table->string('vendor_code', 50);
            $table->string('vendor_name')->nullable();
            $table->string('purchase_order', 50)->nullable();
            $table->string('purchase_order_item', 20)->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('short_text')->nullable();
            $table->string('company_code', 20)->nullable();
            $table->string('plant_code', 20)->nullable();
            $table->decimal('mt_quantity', 20, 3)->nullable();
            $table->string('base_unit', 10)->nullable();
            $table->decimal('quantity', 20, 3)->nullable();
            $table->string('purchase_order_type', 20)->nullable();
            $table->string('release_state', 20)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order', 'purchase_order_item'], 'vendors_po_item_unique');
            $table->index('material_code');
            $table->index('vendor_code');
        });
    }

    public function down(): void
    {
        // This migration repairs drift in an existing environment. Rolling it
        // back must not remove a table that may already contain synchronized data.
    }
};
