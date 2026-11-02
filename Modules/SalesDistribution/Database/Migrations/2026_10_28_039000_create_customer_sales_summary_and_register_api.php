<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_customer_sales_summary')) {
            Schema::create('tbl_customer_sales_summary', function (Blueprint $table): void {
                $table->id();
                $table->string('customer_number', 50)->unique(); // Kunnr
                $table->decimal('year_to_date_sales', 20, 3)->default(0); // YtdSales
                $table->decimal('last_year_sales', 20, 3)->default(0); // LastySales
                $table->decimal('pending_sales', 20, 3)->default(0); // PendingSales
                $table->decimal('last_month_sales', 20, 3)->default(0); // LastmSales
                $table->decimal('current_month_sales', 20, 3)->default(0); // CurrmSales
                $table->decimal('two_months_ago_sales', 20, 3)->default(0); // LtolastSales
                $table->decimal('last_month_outstanding', 20, 3)->default(0); // LastmOut
                $table->decimal('current_month_outstanding', 20, 3)->default(0); // CurrmOut
                $table->decimal('two_months_ago_outstanding', 20, 3)->default(0); // LtolastmOut
                $table->decimal('last_month_overdue', 20, 3)->default(0); // LastmOverdue
                $table->decimal('current_month_overdue', 20, 3)->default(0); // CurrmOverdue
                $table->decimal('two_months_ago_overdue', 20, 3)->default(0); // LtolastmOverdue
                $table->decimal('last_month_payment_received', 20, 3)->default(0); // LastmPayRec
                $table->decimal('current_month_payment_received', 20, 3)->default(0); // CurrmPayRec
                $table->decimal('two_months_ago_payment_received', 20, 3)->default(0); // LtolastmPayRec
                $table->string('payment_terms_code', 50)->nullable(); // ZTERM
                $table->string('payment_terms_description')->nullable(); // TEXT1
                $table->decimal('credit_limit', 20, 3)->default(0); // CREDIT_LIMIT
                $table->json('api_payload')->nullable();
                $table->timestamp('synced_at')->nullable()->index();
                $table->boolean('status')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }

        DB::table('tbl_api_integrations')->updateOrInsert(
            ['code' => 'customer_sales_summary'],
            [
                'name' => 'Customer Sales Summary',
                'description' => 'Import SAP customer-wise sales, outstanding, overdue, payment, and credit summary',
                'api_type' => 'sap',
                'endpoint' => "sap/opu/odata/sap/ZCUST_SALE_HEADER_SRV/zcustwise_saleSet(%27{{customer_number}}%27)?\$format=json",
                'target_table' => json_encode(['tbl_customer_sales_summary'], JSON_THROW_ON_ERROR),
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
        DB::table('tbl_api_integrations')->where('code', 'customer_sales_summary')->delete();
        Schema::dropIfExists('tbl_customer_sales_summary');
    }
};
