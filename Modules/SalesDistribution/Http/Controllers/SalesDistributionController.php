<?php

namespace Modules\SalesDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SalesDistributionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('modules/salesdistribution/dashboard', [
            'dashboard' => [
                'updatedAt' => now()->format('d M Y, h:i A'),
                'kpis' => [
                    ['label' => 'Net sales MTD', 'value' => '₹137.2 Cr', 'change' => '+8.4%', 'note' => 'ahead of monthly plan', 'tone' => 'teal'],
                    ['label' => 'Order book', 'value' => '₹62.8 Cr', 'change' => '+12.1%', 'note' => 'vs previous month', 'tone' => 'blue'],
                    ['label' => 'Dispatch today', 'value' => '₹5.8 Cr', 'change' => '94.2%', 'note' => 'of today’s target', 'tone' => 'amber'],
                    ['label' => 'Perfect order rate', 'value' => '96.4%', 'change' => '+1.8 pts', 'note' => 'month over month', 'tone' => 'violet'],
                ],
                'salesTrend' => [58, 64, 61, 72, 69, 77, 74, 86, 83, 91, 89, 98],
                'regions' => [
                    ['name' => 'North', 'sales' => '₹42.6 Cr', 'achievement' => 96, 'growth' => '+11.4%'],
                    ['name' => 'West', 'sales' => '₹38.9 Cr', 'achievement' => 91, 'growth' => '+8.7%'],
                    ['name' => 'South', 'sales' => '₹31.4 Cr', 'achievement' => 87, 'growth' => '+6.2%'],
                    ['name' => 'East', 'sales' => '₹24.3 Cr', 'achievement' => 82, 'growth' => '+4.9%'],
                ],
                'fulfilment' => [
                    ['label' => 'Orders confirmed', 'value' => 186, 'percent' => 100],
                    ['label' => 'Ready to dispatch', 'value' => 142, 'percent' => 76],
                    ['label' => 'In transit', 'value' => 98, 'percent' => 53],
                    ['label' => 'Delivered', 'value' => 76, 'percent' => 41],
                ],
                'actions' => [
                    ['customer' => 'Northstar Infra', 'issue' => 'Release blocked pending credit approval', 'owner' => 'Commercial', 'priority' => 'High', 'due' => 'Today'],
                    ['customer' => 'Metro Buildcon', 'issue' => 'Expedite dispatch for project milestone', 'owner' => 'Logistics', 'priority' => 'High', 'due' => 'Today'],
                    ['customer' => 'Apex Projects', 'issue' => 'Confirm revised delivery schedule', 'owner' => 'Regional Sales', 'priority' => 'Medium', 'due' => 'Tomorrow'],
                    ['customer' => 'Eastern Engineering', 'issue' => 'Resolve short-supply claim', 'owner' => 'Customer Service', 'priority' => 'Medium', 'due' => '29 Oct'],
                ],
            ],
        ]);
    }
}
