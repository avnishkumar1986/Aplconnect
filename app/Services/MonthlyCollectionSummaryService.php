<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonthlyCollectionSummaryService
{
    public function refresh(): void
    {
        if (! Schema::hasTable('tbl_monthly_collections')) {
            return;
        }

        if (Schema::hasTable('tbl_customer_collection_transactions')
            && DB::table('tbl_customer_collection_transactions')->exists()) {
            $this->refreshFromTransactions();
            return;
        }

        $today = CarbonImmutable::today(config('app.timezone'));
        $periods = [
            ['date' => $today->subMonthsNoOverflow(2), 'field' => 'two_months_ago_payment_received'],
            ['date' => $today->subMonthNoOverflow(), 'field' => 'last_month_payment_received'],
            ['date' => $today, 'field' => 'current_month_payment_received'],
        ];
        $customerCompanyMap = DB::table('tbl_customer_company_map')
            ->select(['customer_number', 'company_code']);

        foreach ($periods as $period) {
            $date = $period['date'];
            $field = $period['field'];
            $now = now();
            $companyTotals = DB::table('tbl_customer_sales_summary as summary')
                ->joinSub($customerCompanyMap, 'customer_company', 'customer_company.customer_number', '=', 'summary.customer_number')
                ->where('summary.status', true)
                ->groupBy('customer_company.company_code')
                ->selectRaw("customer_company.company_code, SUM(summary.{$field}) as collection_amount, COUNT(DISTINCT summary.customer_number) as source_customers")
                ->get();
            $allTotal = DB::table('tbl_customer_sales_summary')
                ->where('status', true)
                ->selectRaw("SUM({$field}) as collection_amount, COUNT(DISTINCT customer_number) as source_customers")
                ->first();

            DB::table('tbl_monthly_collections')
                ->where('collection_year', $date->year)
                ->where('collection_month', $date->month)
                ->delete();
            $rows = $companyTotals->map(fn ($row): array => $this->row(
                $date,
                (string) $row->company_code,
                (float) $row->collection_amount,
                (int) $row->source_customers,
                $now,
            ))->push($this->row(
                $date,
                'ALL',
                (float) ($allTotal->collection_amount ?? 0),
                (int) ($allTotal->source_customers ?? 0),
                $now,
            ))->all();
            DB::table('tbl_monthly_collections')->insert($rows);
        }
    }

    private function refreshFromTransactions(): void
    {
        $year = CarbonImmutable::today(config('app.timezone'))->year;
        $now = now();
        $map = DB::table('tbl_customer_company_map')->select(['customer_number', 'company_code']);
        $companyTotals = DB::table('tbl_customer_collection_transactions as receipt')
            ->joinSub($map, 'customer_company', 'customer_company.customer_number', '=', 'receipt.customer_number')
            ->whereYear('receipt.posting_date', $year)
            ->where('receipt.status', true)
            ->groupByRaw('MONTH(receipt.posting_date), customer_company.company_code')
            ->selectRaw('MONTH(receipt.posting_date) as collection_month, customer_company.company_code, SUM(receipt.amount_received) as collection_amount, COUNT(DISTINCT receipt.customer_number) as source_customers')
            ->get();
        $allTotals = DB::table('tbl_customer_collection_transactions')
            ->whereYear('posting_date', $year)
            ->where('status', true)
            ->groupByRaw('MONTH(posting_date)')
            ->selectRaw('MONTH(posting_date) as collection_month, SUM(amount_received) as collection_amount, COUNT(DISTINCT customer_number) as source_customers')
            ->get();

        DB::table('tbl_monthly_collections')->where('collection_year', $year)->delete();
        $rows = $companyTotals->map(fn ($row): array => $this->row(
            CarbonImmutable::create($year, (int) $row->collection_month, 1),
            (string) $row->company_code,
            (float) $row->collection_amount,
            (int) $row->source_customers,
            $now,
        ));
        foreach ($allTotals as $row) {
            $rows->push($this->row(
                CarbonImmutable::create($year, (int) $row->collection_month, 1),
                'ALL',
                (float) $row->collection_amount,
                (int) $row->source_customers,
                $now,
            ));
        }
        if ($rows->isNotEmpty()) {
            DB::table('tbl_monthly_collections')->insert($rows->all());
        }
    }

    private function row(CarbonImmutable $date, string $companyCode, float $amount, int $customers, mixed $now): array
    {
        return [
            'collection_year' => $date->year,
            'collection_month' => $date->month,
            'month_name' => $date->format('F'),
            'company_code' => $companyCode,
            'collection_amount' => $amount,
            'collection_crore' => round($amount / 10000000, 2),
            'source_customers' => $customers,
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
