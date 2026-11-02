<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_pendingslaes', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_number', 50)->index(); // KUNNR
            $table->string('plant_code', 20)->index(); // WERKS
            $table->string('sales_document_number', 50)->index(); // VBELN
            $table->date('document_date')->nullable()->index(); // ERDAT
            $table->string('external_material_group', 100)->nullable()->index(); // EXTWG
            $table->string('material_group', 100)->nullable()->index(); // MATKL
            $table->string('material_number', 100)->index(); // MATNR
            $table->decimal('order_quantity', 20, 3)->default(0); // KWMENG
            $table->decimal('amount', 20, 2)->default(0); // AMOUNT
            $table->decimal('open_delivery_amount', 20, 2)->default(0); // OPEN_DEL_AMT
            $table->decimal('delivered_quantity', 20, 3)->default(0); // LFIMG
            $table->string('material_text_1', 255)->nullable(); // VTEXT1
            $table->string('material_text_2', 255)->nullable(); // VTEXT2
            $table->string('material_text_3', 255)->nullable(); // VTEXT3
            $table->json('api_payload')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->boolean('status')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['customer_number', 'plant_code', 'sales_document_number', 'material_number'],
                'pending_sales_customer_plant_document_material_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_pendingslaes');
    }
};
