<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_vendors', function (Blueprint $table) {
            $table->dropUnique('vendors_material_vendor_unique');
            $table->date('delivery_date')->nullable()->after('purchase_order_item');
            $table->string('short_text')->nullable()->after('delivery_date');
            $table->string('company_code', 20)->nullable()->after('short_text');
            $table->string('plant_code', 20)->nullable()->after('company_code');
            $table->decimal('mt_quantity', 20, 3)->nullable()->after('plant_code');
            $table->string('base_unit', 10)->nullable()->after('mt_quantity');
            $table->decimal('quantity', 20, 3)->nullable()->after('base_unit');
            $table->string('purchase_order_type', 20)->nullable()->after('quantity');
            $table->string('release_state', 20)->nullable()->after('purchase_order_type');
            $table->unique(['purchase_order', 'purchase_order_item'], 'vendors_po_item_unique');
            $table->index('material_code');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_vendors', function (Blueprint $table) {
            $table->dropUnique('vendors_po_item_unique');
            $table->dropIndex(['material_code']);
            $table->dropColumn([
                'delivery_date', 'short_text', 'company_code', 'plant_code', 'mt_quantity',
                'base_unit', 'quantity', 'purchase_order_type', 'release_state',
            ]);
            $table->unique(['material_code', 'vendor_code'], 'vendors_material_vendor_unique');
        });
    }
};
