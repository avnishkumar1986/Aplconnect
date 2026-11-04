<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_dailysale', function (Blueprint $table): void {
            $table->index(['status', 'billing_date'], 'daily_sales_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_dailysale', function (Blueprint $table): void {
            $table->dropIndex('daily_sales_status_date_index');
        });
    }
};
