<?php

namespace Modules\GroupOverview\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GroupOverviewController extends Controller
{
    public function index(Request $request): Response
    {
        // A full browser refresh always starts Group Overview at All companies.
        // Inertia requests retain the selection while the user remains on the page.
        if (! $request->header('X-Inertia')) {
            $request->session()->forget('group_overview.company');
        }

        $plants = Company::query()
            ->from('tbl_company as plant')
            ->join('tbl_company as parent', 'parent.company_code', '=', 'plant.parent_company_id')
            ->where('plant.record_type', 2)
            ->where('plant.status', true)
            ->where('parent.status', true)
            ->whereRaw('LOWER(parent.company_name) <> ?', ['mauria'])
            ->orderBy('parent.company_name')
            ->orderBy('plant.company_name')
            ->get([
                'plant.company_code as code',
                'plant.company_name as name',
                'parent.company_code as company_code',
                'parent.company_name as company_name',
            ]);

        $companies = $plants
            ->unique('company_code')
            ->map(fn ($plant) => [
                'code' => $plant->company_code,
                'name' => $plant->company_name,
            ])
            ->values();

        $requestedCompany = trim((string) $request->session()->get('group_overview.company', ''));
        $selectedCompany = $companies->contains('code', $requestedCompany)
            ? $requestedCompany
            : null;

        $customerSalesSummary = DB::table('tbl_customer_sales_summary as summary')
            ->join('tbl_users as customer', 'customer.unique_id', '=', 'summary.customer_number')
            ->where('summary.status', true)
            ->where('customer.user_type', 'USR4')
            ->where('customer.status', true)
            ->whereExists(function ($query) use ($selectedCompany, $companies): void {
                $query->selectRaw('1')
                    ->from('tbl_customer_company_map as customer_company')
                    ->whereColumn('customer_company.customer_number', 'customer.unique_id')
                    ->when(
                        $selectedCompany,
                        fn ($query) => $query->where('customer_company.company_code', $selectedCompany),
                        fn ($query) => $query->whereIn('customer_company.company_code', $companies->pluck('code')),
                    );
            });

        $netSales = (clone $customerSalesSummary)
            ->selectRaw('COALESCE(SUM(summary.current_month_sales), 0) as mtd')
            ->selectRaw('COALESCE(SUM(summary.year_to_date_sales), 0) as ytd')
            ->selectRaw('COALESCE(SUM(summary.last_month_sales), 0) as last_month_sales')
            ->selectRaw('COALESCE(SUM(summary.two_months_ago_sales), 0) as two_months_ago_sales')
            ->selectRaw('COALESCE(SUM(summary.current_month_payment_received), 0) as collection_mtd')
            ->selectRaw('COALESCE(SUM(summary.two_months_ago_overdue), 0) as receivables_overdue')
            ->selectRaw('COALESCE(SUM(summary.current_month_outstanding), 0) as total_receivables')
            ->selectRaw('COALESCE(SUM(summary.last_month_payment_received), 0) as collection_last_month')
            ->selectRaw('COALESCE(SUM(summary.two_months_ago_payment_received), 0) as collection_two_months_ago')
            ->selectRaw('COALESCE(SUM(COALESCE(summary.current_month_payment_received, 0) + COALESCE(summary.last_month_payment_received, 0) + COALESCE(summary.two_months_ago_payment_received, 0)), 0) as collection_three_months')
            ->selectRaw('MAX(COALESCE(summary.synced_at, summary.updated_at)) as sales_at')
            ->first();

        $dailySalesQuery = DB::table('tbl_dailysale')
            ->where('status', true)
            ->when($selectedCompany, fn ($query) => $query->where('company_code', $selectedCompany));
        $latestSalesDate = (clone $dailySalesQuery)
            ->where('billing_date', '<=', today()->toDateString())
            ->orderByDesc('billing_date')
            ->value('billing_date');
        $yesterdaySalesDate = $latestSalesDate && Carbon::parse($latestSalesDate)->lt(today())
            ? Carbon::parse($latestSalesDate)->toDateString()
            : today()->subDay()->toDateString();
        $yesterdaySales = (float) (clone $dailySalesQuery)
            ->where('billing_date', $yesterdaySalesDate)
            ->sum('net_value');

        $latestSummaryAt = $netSales->sales_at ?? null;
        $apiSyncAt = DB::table('tbl_api_integrations')
            ->where('code', 'customer_sales_summary')
            ->value('last_run_at');

        $year = today()->year;
        $monthlySalesRows = DB::table('tbl_monthly_sales')
            ->where('sales_year', $year)
            ->where('company_code', $selectedCompany ?: 'ALL')
            ->where('sales_month', '<=', today()->month)
            ->get(['sales_month', 'net_sales', 'net_sales_crore', 'synced_at']);
        $monthlySales = $monthlySalesRows->pluck('net_sales_crore', 'sales_month');
        $monthlyCollectionQuery = DB::table('tbl_customer_collection_transactions as receipt')
            ->where('receipt.status', true)
            ->whereBetween('receipt.posting_date', [today()->startOfYear()->toDateString(), today()->toDateString()])
            ->when($selectedCompany, fn ($query) => $query->where('receipt.company_code', $selectedCompany));
        $monthlyCollectionRows = (clone $monthlyCollectionQuery)
            ->groupByRaw('MONTH(receipt.posting_date)')
            ->orderByRaw('MONTH(receipt.posting_date)')
            ->get([
                DB::raw('MONTH(receipt.posting_date) as collection_month'),
                DB::raw('SUM(receipt.amount_received) as collection_amount'),
                DB::raw('SUM(receipt.amount_received) / 10000000 as collection_crore'),
                DB::raw('MAX(receipt.synced_at) as synced_at'),
            ]);
        $monthlyCollections = $monthlyCollectionRows->pluck('collection_crore', 'collection_month');

        $collectionTotals = (clone $monthlyCollectionQuery)
            ->selectRaw('COALESCE(SUM(receipt.amount_received), 0) as ytd')
            ->selectRaw('COALESCE(SUM(CASE WHEN receipt.posting_date >= ? THEN receipt.amount_received ELSE 0 END), 0) as mtd', [today()->startOfMonth()->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN receipt.posting_date = ? THEN receipt.amount_received ELSE 0 END), 0) as yesterday', [today()->subDay()->toDateString()])
            ->selectRaw('MAX(receipt.posting_date) as data_through_date')
            ->selectRaw('MAX(receipt.synced_at) as synced_at')
            ->first();

        $currentSales = $monthlySalesRows->firstWhere('sales_month', today()->month);
        $currentCollections = $monthlyCollectionRows->firstWhere('collection_month', today()->month);
        // Use the invoice-level daily sales table for all sales KPIs so the
        // cards and the date-wise graph always reconcile to the same source.
        $salesMtd = (float) ($currentSales->net_sales ?? 0);
        $salesYtd = (float) $monthlySalesRows->sum(fn ($row): float => (float) $row->net_sales);
        $collectionsMtd = (float) ($collectionTotals->mtd ?? 0);
        $collectionsYtd = (float) ($collectionTotals->ytd ?? 0);

        $selectedPlantCodes = $selectedCompany
            ? $plants->where('company_code', $selectedCompany)->pluck('code')
            : collect();
        $materialTypes = DB::table('tbl_materials')
            ->where('status', true)
            ->groupBy('matnr')
            ->selectRaw('matnr, MAX(UPPER(TRIM(mtart))) as material_type');
        $inventory = DB::table('tbl_material_stocks as stock')
            ->leftJoinSub($materialTypes, 'material', 'material.matnr', '=', 'stock.matnr')
            ->where('stock.status', true)
            ->when($selectedCompany, fn ($query) => $query->whereIn('stock.plant_code', $selectedPlantCodes))
            ->selectRaw('COALESCE(SUM(stock.stock_value), 0) as stock_value')
            ->selectRaw("COALESCE(SUM(CASE WHEN material.material_type = 'ZRAW' THEN stock.stock_value ELSE 0 END), 0) as zraw_value")
            ->selectRaw("COALESCE(SUM(CASE WHEN material.material_type = 'YRAW' THEN stock.stock_value ELSE 0 END), 0) as yraw_value")
            ->selectRaw('COALESCE(SUM(stock.current_stock), 0) as current_stock')
            ->selectRaw('COALESCE(SUM(stock.auto_perday_consumption), 0) as daily_consumption')
            ->selectRaw('MAX(stock.synced_at) as synced_at')
            ->first();
        $dailyConsumption = (float) ($inventory->daily_consumption ?? 0);

        $cardProps = [
            'plants' => $plants,
            'companies' => $companies,
            'filters' => ['company' => $selectedCompany],
            'netSales' => [
                'mtd' => $salesMtd,
                'ytd' => $salesYtd,
                'yesterday' => $yesterdaySales,
            ],
            'collections' => [
                'mtd' => $collectionsMtd,
                'ytd' => $collectionsYtd,
                'yesterday' => (float) ($collectionTotals->yesterday ?? 0),
            ],
            'receivablesOverdue' => (float) ($netSales->receivables_overdue ?? 0),
            'totalReceivables' => (float) ($netSales->total_receivables ?? 0),
            'inventory' => [
                'value' => (float) ($inventory->stock_value ?? 0),
                'zrawValue' => (float) ($inventory->zraw_value ?? 0),
                'yrawValue' => (float) ($inventory->yraw_value ?? 0),
                'daysOfCover' => $dailyConsumption > 0 ? round((float) $inventory->current_stock / $dailyConsumption, 1) : null,
            ],
            'salesMeta' => [
                'salesDateTime' => $currentSales?->synced_at ? Carbon::parse($currentSales->synced_at)->format('d F Y, h:i A') : ($latestSummaryAt ? Carbon::parse($latestSummaryAt)->format('d F Y, h:i A') : null),
                'collectionsDateTime' => $collectionTotals?->synced_at ? Carbon::parse($collectionTotals->synced_at)->format('d F Y, h:i A') : null,
                'receivablesDateTime' => $latestSummaryAt ? Carbon::parse($latestSummaryAt)->format('d F Y, h:i A') : null,
                'inventoryDateTime' => $inventory?->synced_at ? Carbon::parse($inventory->synced_at)->format('d F Y, h:i A') : null,
                'apiSyncDateTime' => $apiSyncAt ? Carbon::parse($apiSyncAt)->format('d F Y, h:i A') : null,
                'salesDataDate' => $latestSalesDate ? Carbon::parse($latestSalesDate)->format('d M Y') : null,
                'yesterdaySalesDate' => Carbon::parse($yesterdaySalesDate)->format('d M Y'),
                'collectionsDataDate' => $collectionTotals?->data_through_date ? Carbon::parse($collectionTotals->data_through_date)->format('d M Y') : null,
                'yesterdayCollectionsDate' => today()->subDay()->format('d M Y'),
                'receivablesDataDate' => $latestSummaryAt ? Carbon::parse($latestSummaryAt)->format('d M Y') : null,
                'inventoryDataDate' => $inventory?->synced_at ? Carbon::parse($inventory->synced_at)->format('d M Y') : null,
            ],
        ];

        $partialProps = array_filter(explode(',', (string) $request->header('X-Inertia-Partial-Data', '')));
        if ($partialProps !== [] && array_intersect($partialProps, ['performance', 'companyScorecard']) === []) {
            return Inertia::render('modules/groupoverview/dashboard', $cardProps);
        }

        $fiscalStartYear = today()->month >= 4 ? today()->year : today()->year - 1;
        $targetFiscalYear = $fiscalStartYear.'-'.substr((string) ($fiscalStartYear + 1), -2);
        $customerCompanyMap = DB::table('tbl_customer_company_map')
            ->select(['customer_number', 'company_code'])
            ->distinct();
        $targetQuery = DB::table('tbl_product_targets as target')
            ->joinSub($customerCompanyMap, 'target_company', 'target_company.customer_number', '=', 'target.customer_code')
            ->where('target.status', true)
            ->where('target.fiscal_year', $targetFiscalYear)
            ->when($selectedCompany, fn ($query) => $query->where('target_company.company_code', $selectedCompany));
        $yearlyTarget = (float) (clone $targetQuery)->sum('target.total_sale');
        $yearlyTargetCrore = round($yearlyTarget / 100, 2);
        $monthlyTargets = (clone $targetQuery)
            ->whereNotNull('target.month')
            ->where('target.month', '<>', '')
            ->groupBy('target.month')
            ->selectRaw('target.month, SUM(target.total_sale) / 100 as target_crore')
            ->get()
            ->mapWithKeys(function ($row): array {
                $month = is_numeric($row->month)
                    ? (int) $row->month
                    : Carbon::parse((string) $row->month)->month;

                return [$month => round((float) $row->target_crore, 2)];
            });
        $proratedMonthlyTarget = round($yearlyTargetCrore / 12, 2);

        $dailySales = DB::table('tbl_dailysale')
            ->where('status', true)
            ->whereBetween('billing_date', [today()->startOfMonth()->toDateString(), today()->toDateString()])
            ->when($selectedCompany, fn ($query) => $query->where('company_code', $selectedCompany))
            ->groupBy('billing_date')
            ->selectRaw('billing_date, SUM(net_value) / 10000000 as sales_crore')
            ->pluck('sales_crore', 'billing_date');
        $dailyCollectionQuery = DB::table('tbl_customer_collection_transactions as receipt')
            ->where('receipt.status', true)
            ->whereBetween('receipt.posting_date', [today()->startOfMonth()->toDateString(), today()->toDateString()])
            ->when($selectedCompany, fn ($query) => $query->where('receipt.company_code', $selectedCompany));
        $dailyCollections = $dailyCollectionQuery
            ->groupBy('receipt.posting_date')
            ->selectRaw('receipt.posting_date, SUM(receipt.amount_received) / 10000000 as collection_crore')
            ->pluck('collection_crore', 'posting_date');
        $currentMonthTarget = (float) ($monthlyTargets[today()->month] ?? $proratedMonthlyTarget);
        $dailyTarget = round($currentMonthTarget / today()->daysInMonth, 2);
        $dailyTrend = collect(range(1, today()->daysInMonth))->map(function (int $day) use ($dailySales, $dailyCollections, $dailyTarget): array {
            $date = today()->startOfMonth()->day($day);
            $dateKey = $date->toDateString();

            return [
                'date' => $date->format('d'),
                'sales' => $dailySales->has($dateKey) ? round((float) $dailySales[$dateKey], 2) : null,
                'collections' => $dailyCollections->has($dateKey) ? round((float) $dailyCollections[$dateKey], 2) : null,
                'target' => $dailyTarget,
            ];
        });

        $monthlyTrend = collect(range(1, 12))->map(fn (int $month): array => [
            'date' => today()->startOfYear()->addMonths($month - 1)->format('M'),
            'sales' => $monthlySales->has($month) ? round((float) $monthlySales[$month], 2) : null,
            'collections' => $monthlyCollections->has($month) ? round((float) $monthlyCollections[$month], 2) : null,
            'target' => (float) ($monthlyTargets[$month] ?? ($monthlyTargets->isEmpty() ? $proratedMonthlyTarget : 0)),
        ]);

        $actualSalesByCompany = DB::table('tbl_monthly_sales')
            ->selectRaw('company_code, net_sales / 100000 as total_lakh')
            ->where('sales_year', $year)
            ->where('sales_month', today()->month)
            ->where('company_code', '<>', 'ALL')
            ->when($selectedCompany, fn ($query) => $query->where('company_code', $selectedCompany))
            ->pluck('total_lakh', 'company_code');
        $monthlyTargetsByCompany = DB::table('tbl_product_targets as target')
            ->joinSub($customerCompanyMap, 'target_company', 'target_company.customer_number', '=', 'target.customer_code')
            ->where('target.status', true)
            ->where('target.fiscal_year', $targetFiscalYear)
            ->when($selectedCompany, fn ($query) => $query->where('target_company.company_code', $selectedCompany))
            ->groupBy('target_company.company_code')
            ->selectRaw('target_company.company_code, SUM(target.total_sale) / 12 as monthly_target_lakh')
            ->pluck('monthly_target_lakh', 'target_company.company_code');
        $mtdCollectionsByCompany = DB::table('tbl_customer_collection_transactions as receipt')
            ->where('receipt.status', true)
            ->whereBetween('receipt.posting_date', [today()->startOfMonth()->toDateString(), today()->toDateString()])
            ->when($selectedCompany, fn ($query) => $query->where('receipt.company_code', $selectedCompany))
            ->groupBy('receipt.company_code')
            ->selectRaw('receipt.company_code, SUM(receipt.amount_received) / 10000000 as collection_crore')
            ->pluck('collection_crore', 'receipt.company_code');
        $customerMetricsByCompany = DB::table('tbl_customer_sales_summary as summary')
            ->joinSub($customerCompanyMap, 'customer_company', 'customer_company.customer_number', '=', 'summary.customer_number')
            ->where('summary.status', true)
            ->when($selectedCompany, fn ($query) => $query->where('customer_company.company_code', $selectedCompany))
            ->groupBy('customer_company.company_code')
            ->selectRaw('customer_company.company_code')
            ->selectRaw('SUM(summary.two_months_ago_overdue) / 10000000 as overdue_crore')
            ->selectRaw('COUNT(DISTINCT summary.customer_number) as active_customers')
            ->get()
            ->keyBy('company_code');

        $planComparison = $companies
            ->when($selectedCompany, fn ($companies) => $companies->where('code', $selectedCompany))
            ->map(fn (array $company): array => [
                'name' => $company['name'],
                'actual' => round((float) ($actualSalesByCompany[$company['code']] ?? 0), 2),
                'plan' => round((float) ($monthlyTargetsByCompany[$company['code']] ?? 0), 2),
            ])
            ->values();
        $companyScorecard = $companies
            ->when($selectedCompany, fn ($companies) => $companies->where('code', $selectedCompany))
            ->map(function (array $company) use ($actualSalesByCompany, $monthlyTargetsByCompany, $mtdCollectionsByCompany, $customerMetricsByCompany): array {
            $actual = (float) ($actualSalesByCompany[$company['code']] ?? 0) / 100;
            $target = (float) ($monthlyTargetsByCompany[$company['code']] ?? 0) / 100;
            $metrics = $customerMetricsByCompany[$company['code']] ?? null;

            return [
                'code' => $company['code'],
                'company' => $company['name'],
                'mtdSales' => round($actual, 2),
                'monthlyTarget' => round($target, 2),
                'monthlyAchievement' => $target > 0 ? round(($actual / $target) * 100, 1) : 0.0,
                'mtdCollections' => round((float) ($mtdCollectionsByCompany[$company['code']] ?? 0), 2),
                'overdue' => round((float) ($metrics->overdue_crore ?? 0), 2),
                'activeCustomers' => (int) ($metrics->active_customers ?? 0),
            ];
            })->values();

        return Inertia::render('modules/groupoverview/dashboard', [
            ...$cardProps,
            'performance' => [
                'year' => $year,
                'yearlyTarget' => $yearlyTargetCrore,
                'targetFiscalYear' => $targetFiscalYear,
                'monthlyTrend' => $monthlyTrend,
                'dailyTrend' => $dailyTrend,
                'planComparison' => $planComparison,
            ],
            'companyScorecard' => $companyScorecard,
        ]);
    }

    public function updateCompanyFilter(Request $request): RedirectResponse
    {
        $company = trim((string) $request->validate([
            'company' => ['nullable', 'string', 'max:50'],
        ])['company'] ?? '');

        $isValidCompany = $company !== '' && Company::query()
            ->where('company_code', $company)
            ->where('record_type', 1)
            ->where('status', true)
            ->exists();

        if ($isValidCompany) {
            $request->session()->put('group_overview.company', $company);
        } else {
            $request->session()->forget('group_overview.company');
        }

        return to_route('admin.group-overview.index');
    }
}
