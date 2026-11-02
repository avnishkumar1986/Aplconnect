<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_customer_collection_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_number', 50)->index();
            $table->string('company_code', 20)->nullable()->index();
            $table->string('document_number', 50)->nullable()->index();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->date('posting_date')->index();
            $table->date('document_date')->nullable();
            $table->string('document_type', 10)->nullable();
            $table->decimal('amount_received', 20, 3)->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->char('source_hash', 64)->unique();
            $table->json('api_payload')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
            $table->index(['posting_date', 'company_code'], 'collection_posting_company_index');
        });

        DB::table('tbl_api_integrations')->updateOrInsert(
            ['code' => 'customer_collections'],
            [
                'name' => 'Customer Collections',
                'description' => 'Import customer payment receipts from SAP and summarize Collections by posting month',
                'api_type' => 'sap',
                'endpoint' => "sap/opu/odata/sap/ZCUST_DATA_SRV/CUST_DATASet(%27{{customer_number}}%27)?\$format=json",
                'target_table' => json_encode(['tbl_customer_collection_transactions'], JSON_THROW_ON_ERROR),
                'import_mode' => 'direct',
                'chunk_size' => 1,
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
        DB::table('tbl_api_integrations')->where('code', 'customer_collections')->delete();
        Schema::dropIfExists('tbl_customer_collection_transactions');
    }
};
