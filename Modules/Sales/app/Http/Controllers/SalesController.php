<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SalesController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('sales.overview.view');

        $companies = DB::table('tbl_company')
            ->whereIn('company_code', ['1000', '5000'])
            ->where('record_type', 1)
            ->where('Status', 1)
            ->orderByRaw("FIELD(company_code, '1000', '5000')")
            ->get(['id', 'company_code', 'company_name']);

        $dashboard = [
            'updated_at' => now()->format('d M Y, h:i A'),
            'kpis' => [
                ['label' => 'Sales this month', 'value' => 18450000, 'change' => 12.4, 'tone' => 'cyan', 'icon' => 'sales'],
                ['label' => 'Sales this year', 'value' => 174820000, 'change' => 8.7, 'tone' => 'blue', 'icon' => 'calendar'],
                ['label' => 'Target achieved', 'value' => 86.4, 'suffix' => '%', 'change' => 4.2, 'tone' => 'emerald', 'icon' => 'target'],
                ['label' => 'Outstanding', 'value' => 32750000, 'change' => -6.1, 'tone' => 'amber', 'icon' => 'wallet'],
            ],
            'monthly_sales' => [
                'labels' => ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
                'apl' => [9.2, 10.8, 11.5, 12.4, 13.1, 12.7, 14.6, 15.2, 15.9, 16.8, 17.4, 18.1],
                'kpl' => [6.8, 7.2, 7.9, 8.4, 8.1, 9.3, 9.8, 10.6, 10.2, 11.4, 12.1, 13.2],
            ],
            'companies' => [
                'APL' => ['sales' => 108600000, 'target' => 120000000, 'achievement' => 90.5],
                'KPL' => ['sales' => 66220000, 'target' => 82500000, 'achievement' => 80.3],
            ],
            'pipeline' => [
                ['label' => 'Pending approval', 'count' => 12, 'amount' => 8650000, 'tone' => 'amber'],
                ['label' => 'Ready to dispatch', 'count' => 17, 'amount' => 12300000, 'tone' => 'blue'],
                ['label' => 'Partially dispatched', 'count' => 6, 'amount' => 4780000, 'tone' => 'violet'],
                ['label' => 'Delayed dispatch', 'count' => 4, 'amount' => 2150000, 'tone' => 'red'],
            ],
            'outstanding' => [
                ['label' => 'Not due', 'value' => 12800000, 'percent' => 39, 'tone' => 'emerald'],
                ['label' => '1–30 days', 'value' => 9450000, 'percent' => 29, 'tone' => 'cyan'],
                ['label' => '31–60 days', 'value' => 6230000, 'percent' => 19, 'tone' => 'amber'],
                ['label' => 'Over 60 days', 'value' => 4270000, 'percent' => 13, 'tone' => 'red'],
            ],
        ];

        return view('sales::overview', compact('companies', 'dashboard'));
    }
}
