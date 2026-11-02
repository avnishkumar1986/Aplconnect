<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_monthly_sales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('sales_year');
            $table->unsignedTinyInteger('sales_month');
            $table->string('month_name', 9);
            $table->string('company_code', 20)->default('ALL');
            $table->decimal('net_sales', 20, 2)->default(0);
            $table->decimal('net_sales_crore', 20, 2)->default(0);
            $table->unsignedBigInteger('source_records')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['sales_year', 'sales_month', 'company_code'], 'monthly_sales_period_company_unique');
            $table->index(['sales_year', 'sales_month'], 'monthly_sales_period_index');
        });

        $now = now();
        $companyRows = DB::table('tbl_dailysale')
            ->whereNotNull('billing_year')
            ->whereNotNull('billing_month')
            ->groupBy('billing_year', 'billing_month', 'company_code')
            ->selectRaw('billing_year, billing_month, COALESCE(company_code, "UNKNOWN") as company_code, SUM(net_value) as net_sales, COUNT(*) as source_records')
            ->get();
        $allRows = DB::table('tbl_dailysale')
            ->whereNotNull('billing_year')
            ->whereNotNull('billing_month')
            ->groupBy('billing_year', 'billing_month')
            ->selectRaw('billing_year, billing_month, SUM(net_value) as net_sales, COUNT(*) as source_records')
            ->get();

        $rows = $companyRows->map(fn ($row): array => [
            'sales_year' => $row->billing_year,
            'sales_month' => $row->billing_month,
            'month_name' => date('F', mktime(0, 0, 0, (int) $row->billing_month, 1)),
            'company_code' => $row->company_code,
            'net_sales' => $row->net_sales,
            'net_sales_crore' => round((float) $row->net_sales / 10000000, 2),
            'source_records' => $row->source_records,
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->concat($allRows->map(fn ($row): array => [
            'sales_year' => $row->billing_year,
            'sales_month' => $row->billing_month,
            'month_name' => date('F', mktime(0, 0, 0, (int) $row->billing_month, 1)),
            'company_code' => 'ALL',
            'net_sales' => $row->net_sales,
            'net_sales_crore' => round((float) $row->net_sales / 10000000, 2),
            'source_records' => $row->source_records,
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]))->values()->all();

        if ($rows !== []) {
            DB::table('tbl_monthly_sales')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_monthly_sales');
    }
};
