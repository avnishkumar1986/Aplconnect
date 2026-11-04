<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_dailysale', function (Blueprint $table): void {
            $table->index(['status', 'company_code', 'billing_date'], 'daily_sales_dashboard_filter_index');
        });

        Schema::table('tbl_customer_collection_transactions', function (Blueprint $table): void {
            $table->index(['status', 'company_code', 'posting_date'], 'collections_dashboard_filter_index');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_dailysale', function (Blueprint $table): void {
            $table->dropIndex('daily_sales_dashboard_filter_index');
        });

        Schema::table('tbl_customer_collection_transactions', function (Blueprint $table): void {
            $table->dropIndex('collections_dashboard_filter_index');
        });
    }
};
