<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_customer_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('user_type', 20)->default('USR4')->index();
            $table->string('user_id', 50)->unique();
            $table->date('record_creation_date')->nullable();
            $table->string('plant_code', 20)->nullable()->index();
            $table->boolean('deletion_indicator')->default(false)->index();
            $table->string('gstin', 20)->nullable()->index();
            $table->string('ecc_no', 50)->nullable();
            $table->string('cst_no', 50)->nullable();
            $table->string('lst_no', 50)->nullable();
            $table->string('pan', 20)->nullable()->index();
            $table->string('sales_organization', 20)->nullable()->index();
            $table->string('sales_district', 20)->nullable()->index();
            $table->string('sales_group', 20)->nullable()->index();
            $table->string('sales_office', 20)->nullable()->index();
            $table->string('payment_terms', 100)->nullable();
            $table->decimal('credit_limit', 18, 3)->default(0);
            $table->decimal('outstanding_amount', 18, 3)->default(0);
            $table->decimal('credit_utilization_percentage', 18, 3)->default(0);

            foreach (range(1, 6) as $level) {
                $table->string("employee_level_{$level}", 50)->nullable()->index();
            }
            foreach (range(1, 12) as $month) {
                $suffix = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
                $table->decimal("month_{$suffix}_target", 18, 2)->default(0);
                $table->decimal("month_{$suffix}_actual", 18, 2)->default(0);
            }
            foreach (range(1, 4) as $quarter) {
                $table->decimal("quarter_{$quarter}_target", 18, 2)->default(0);
                $table->decimal("quarter_{$quarter}_actual", 18, 2)->default(0);
            }

            $table->json('api_payload')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->boolean('status')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        DB::table('tbl_api_integrations')->updateOrInsert(
            ['code' => 'cust'],
            [
                'name' => 'Customers',
                'description' => 'Import SAP customers, contacts, addresses, and sales data',
                'api_type' => 'sap',
                'endpoint' => 'sap/opu/odata/sap/ZSAP_CMS_ZEMP_MASTER_SRV/ZcustEmpSet?$top=20&$format=json',
                'target_table' => json_encode(['tbl_users', 'tbl_usrcontact', 'tbl_usraddress', 'tbl_customer_sales'], JSON_THROW_ON_ERROR),
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
        Schema::dropIfExists('tbl_customer_sales');
    }
};
