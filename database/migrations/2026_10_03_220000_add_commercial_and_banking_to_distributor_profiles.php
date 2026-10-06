<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_distributor_profiles', function (Blueprint $table): void {
            $table->string('company_association', 30)->nullable()->after('distributor_category');
            $table->json('applicable_plants')->nullable()->after('company_association');
            $table->json('material_groups')->nullable()->after('applicable_plants');
            $table->decimal('credit_limit', 15, 2)->nullable()->after('material_groups');
            $table->unsignedInteger('credit_period_days')->nullable()->after('credit_limit');
            $table->string('payment_terms', 100)->nullable()->after('credit_period_days');
            $table->string('price_list', 100)->nullable()->after('payment_terms');
            $table->char('currency', 3)->default('INR')->after('price_list');
            $table->string('tax_classification', 100)->nullable()->after('currency');
            $table->string('bank_name', 150)->nullable()->after('tax_classification');
            $table->string('account_holder_name', 255)->nullable()->after('bank_name');
            $table->string('account_number', 50)->nullable()->after('account_holder_name');
            $table->string('ifsc_code', 20)->nullable()->after('account_number');
            $table->string('bank_branch', 150)->nullable()->after('ifsc_code');
            $table->string('cancelled_cheque_path')->nullable()->after('bank_branch');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_distributor_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'company_association', 'applicable_plants', 'material_groups',
                'credit_limit', 'credit_period_days', 'payment_terms', 'price_list',
                'currency', 'tax_classification', 'bank_name', 'account_holder_name',
                'account_number', 'ifsc_code', 'bank_branch', 'cancelled_cheque_path',
            ]);
        });
    }
};
