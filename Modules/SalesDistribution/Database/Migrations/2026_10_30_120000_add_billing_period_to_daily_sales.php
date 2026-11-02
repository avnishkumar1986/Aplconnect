<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_dailysale', function (Blueprint $table): void {
            $table->unsignedSmallInteger('billing_year')->nullable()->after('billing_date');
            $table->unsignedTinyInteger('billing_month')->nullable()->after('billing_year');
            $table->index(['billing_year', 'billing_month'], 'daily_sale_billing_period_index');
        });

        DB::table('tbl_dailysale')
            ->whereNotNull('billing_date')
            ->update([
                'billing_year' => DB::raw('YEAR(billing_date)'),
                'billing_month' => DB::raw('MONTH(billing_date)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tbl_dailysale', function (Blueprint $table): void {
            $table->dropIndex('daily_sale_billing_period_index');
            $table->dropColumn(['billing_year', 'billing_month']);
        });
    }
};
