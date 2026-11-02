<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('billing_document_number', 50)->index(); // VBELN
            $table->string('billing_type', 20)->nullable()->index(); // FKART
            $table->date('billing_date')->nullable()->index(); // FKDAT
            $table->date('record_creation_date')->nullable()->index(); // ERDAT
            $table->string('company_code', 20)->nullable()->index(); // BUKRS
            $table->string('plant_code', 20)->nullable()->index(); // WERKS
            $table->string('customer_number', 50)->index(); // KUNAG
            $table->string('customer_name')->nullable(); // NAME1
            $table->string('material_number', 100)->index(); // MATNR
            $table->string('material_description')->nullable(); // MAKTX
            $table->string('material_group', 100)->nullable()->index(); // MATKL
            $table->string('material_group_description')->nullable(); // WGBEZ
            $table->string('product_hierarchy', 100)->nullable()->index(); // PRODH
            $table->string('product_hierarchy_description')->nullable(); // VTEXT
            $table->string('external_material_group', 100)->nullable()->index(); // EXTWG
            $table->string('external_material_group_description')->nullable(); // EWBEZ
            $table->decimal('billed_quantity', 18, 3)->default(0); // FKIMG
            $table->decimal('billed_quantity_kg', 18, 3)->default(0); // KGFKIMG
            $table->decimal('billed_quantity_liters', 18, 3)->default(0); // LFKIMG
            $table->decimal('billed_quantity_meters', 18, 3)->default(0); // MFKIMG
            $table->string('sales_unit', 20)->nullable(); // VRKME
            $table->string('base_unit', 20)->nullable(); // MEINS
            $table->decimal('net_value', 18, 2)->default(0); // NETWR
            $table->decimal('tax_amount', 18, 2)->default(0); // MWSBP
            $table->string('fiscal_year', 4)->nullable()->index(); // GJAHR
            $table->decimal('condition_value', 18, 2)->default(0); // KWERT
            $table->json('api_payload')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->boolean('status')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['billing_document_number', 'customer_number', 'plant_code', 'material_number'],
                'tbl_sales_document_customer_plant_material_unique'
            );
        });

        DB::table('tbl_api_integrations')->updateOrInsert(
            ['code' => 'sales_data'],
            [
                'name' => 'Daily Sales Data',
                'description' => 'Import SAP billing and sales data for the current date',
                'api_type' => 'sap',
                'endpoint' => "sap/opu/odata/sap/ZSALES_DATA_SRV/zcust_salesSet?\$filter=Fkdat%20ge%20datetime%27{{today}}T00:00:00%27%20and%20Fkdat%20le%20datetime%27{{today}}T00:00:00%27&\$format=json",
                'target_table' => json_encode(['tbl_sales'], JSON_THROW_ON_ERROR),
                'import_mode' => 'chunk_all',
                'chunk_size' => 100,
                'chunk_offset' => 0,
                'retries' => 3,
                'retry_delay_seconds' => 10,
                'timeout_seconds' => 300,
                'status' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('tbl_api_integrations')->where('code', 'sales_data')->delete();
        Schema::dropIfExists('tbl_sales');
    }
};
