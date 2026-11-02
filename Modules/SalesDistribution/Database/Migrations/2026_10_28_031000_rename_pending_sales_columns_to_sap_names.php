<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_pendingslaes', function (Blueprint $table): void {
            $table->renameColumn('customer_number', 'Kunnr');
            $table->renameColumn('plant_code', 'WERKS');
            $table->renameColumn('sales_document_number', 'VBELN');
            $table->renameColumn('document_date', 'ERDAT');
            $table->renameColumn('external_material_group', 'EXTWG');
            $table->renameColumn('material_group', 'MATKL');
            $table->renameColumn('material_number', 'MATNR');
            $table->renameColumn('order_quantity', 'KWMENG');
            $table->renameColumn('amount', 'AMOUNT');
            $table->renameColumn('open_delivery_amount', 'OPEN_DEL_AMT');
            $table->renameColumn('delivered_quantity', 'LFIMG');
            $table->renameColumn('material_text_1', 'VTEXT1');
            $table->renameColumn('material_text_2', 'VTEXT2');
            $table->renameColumn('material_text_3', 'VTEXT3');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_pendingslaes', function (Blueprint $table): void {
            $table->renameColumn('Kunnr', 'customer_number');
            $table->renameColumn('WERKS', 'plant_code');
            $table->renameColumn('VBELN', 'sales_document_number');
            $table->renameColumn('ERDAT', 'document_date');
            $table->renameColumn('EXTWG', 'external_material_group');
            $table->renameColumn('MATKL', 'material_group');
            $table->renameColumn('MATNR', 'material_number');
            $table->renameColumn('KWMENG', 'order_quantity');
            $table->renameColumn('AMOUNT', 'amount');
            $table->renameColumn('OPEN_DEL_AMT', 'open_delivery_amount');
            $table->renameColumn('LFIMG', 'delivered_quantity');
            $table->renameColumn('VTEXT1', 'material_text_1');
            $table->renameColumn('VTEXT2', 'material_text_2');
            $table->renameColumn('VTEXT3', 'material_text_3');
        });
    }
};
