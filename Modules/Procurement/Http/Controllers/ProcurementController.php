<?php

namespace Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ImportMaterialsFromApi;
use App\Models\ApiIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    private const COMPANY_MATERIAL_TYPES = [
        '1000' => 'ZRAW',
        '5000' => 'YRAW',
    ];

    public function dashboard(Request $request): Response
    {
        $companies = DB::table('tbl_company')
            ->where('record_type', 1)
            ->where('status', 1)
            ->whereIn('company_code', ['1000', '5000'])
            ->orderBy('company_code')
            ->get(['id', 'company_code', 'company_name']);
        $selectedCompanyCode = $request->string('company')->trim()->toString() ?: '1000';
        $company = $companies->firstWhere('company_code', $selectedCompanyCode);
        abort_unless($company, 404);
        $selectedSource = $request->string('source')->trim()->lower()->toString() ?: 'sap';
        abort_unless(in_array($selectedSource, ['sap', 'manual'], true), 404);
        $consumptionPlants = DB::table('tbl_company')
            ->where('record_type', 2)
            ->where('status', 1)
            ->where('parent_company_id', $company->company_code)
            ->orderBy('company_name')
            ->get(['company_code', 'company_name']);
        $selectedPlantCodes = $consumptionPlants->pluck('company_code');
        $materials = DB::table('tbl_materials as material')
            ->join('tbl_material_stocks as stock', function (JoinClause $join): void {
                $join->on('stock.matnr', '=', 'material.matnr')->where('stock.status', 1);
            })
            ->where('material.status', 1)
            ->whereIn('stock.plant_code', $selectedPlantCodes)
            ->select('material.matnr', 'material.maktx', 'material.meins')
            ->distinct()
            ->orderBy('material.maktx')
            ->orderBy('material.matnr')
            ->get();
        $requestedMaterial = $request->string('material')->trim()->toString();
        $selectedConsumptionMaterial = $requestedMaterial !== '' && $materials->contains('matnr', $requestedMaterial)
            ? $requestedMaterial
            : '';
        $selectedMaterialUnit = strtoupper((string) ($materials->firstWhere('matnr', $selectedConsumptionMaterial)->meins ?? 'KG'));
        $consumptionDisplayUnit = $selectedConsumptionMaterial !== '' && $selectedMaterialUnit !== 'KG'
            ? $selectedMaterialUnit
            : 'MT';
        $consumptionDivisor = $consumptionDisplayUnit === 'MT' ? 1000 : 1;
        $requestedConsumptionPlant = $request->string('consumption_plant')->trim()->toString();
        $selectedConsumptionPlant = $requestedConsumptionPlant !== '' && $consumptionPlants->contains('company_code', $requestedConsumptionPlant)
            ? $requestedConsumptionPlant
            : '';
        $filterPurchases = fn ($query) => $query->where('company_id', $company->id);

        $localPurchaseMetrics = (object) ['purchase_quantity_mt' => 0, 'open_orders' => 0, 'open_order_quantity_mt' => 0];
        $localDeliveryStatus = collect();

        if ($selectedSource === 'manual') {
            $localPurchases = $filterPurchases(DB::table('procurement_purchases')->where('status', 1));
            $localPurchaseMetrics = (clone $localPurchases)
                ->selectRaw('COALESCE(SUM(quantity_mt), 0) as purchase_quantity_mt')
                ->selectRaw("SUM(CASE WHEN delivery_status IN ('planned', 'in_transit', 'partially_received') THEN 1 ELSE 0 END) as open_orders")
                ->selectRaw("COALESCE(SUM(CASE WHEN delivery_status IN ('planned', 'in_transit', 'partially_received') THEN quantity_mt ELSE 0 END), 0) as open_order_quantity_mt")
                ->first();
            $localDeliveryStatus = (clone $localPurchases)
                ->select(
                    DB::raw("'local' as source"),
                    'id',
                    'reference as po_number',
                    'vendor_name',
                    'delivery_status as label',
                    'quantity_mt',
                    'expected_delivery_date',
                )
                ->orderBy('expected_delivery_date')
                ->get();
        }

        $apiDeliveryStatus = DB::table('tbl_vendor_purchase_orders')
            ->where('status', 1)
            ->where('quantity_mt', '>', 0)
            ->when($selectedSource === 'manual', fn ($query) => $query->whereRaw('1 = 0'))
            ->whereIn('plant_code', $selectedPlantCodes)
            ->select(
                DB::raw("'sap' as source"),
                'id',
                'po_number',
                'item_number',
                'vendor_name',
                'delivery_status as label',
                'quantity_mt as total_quantity_mt',
                'received_quantity_mt',
                DB::raw("CASE WHEN delivery_status = 'received' THEN received_quantity_mt ELSE quantity_mt END as quantity_mt"),
                'expected_delivery_date',
            )
            ->orderBy('expected_delivery_date')
            ->get();

        $deliveryStatus = $localDeliveryStatus
            ->concat($apiDeliveryStatus)
            ->sortBy('expected_delivery_date')
            ->values()
            ->map(function ($purchase) {
                $purchase->po_number = filled($purchase->po_number)
                    ? $purchase->po_number.($purchase->source === 'sap' && filled($purchase->item_number ?? null) ? '/'.$purchase->item_number : '')
                    : 'LOCAL-'.$purchase->id;

                return $purchase;
            });
        $apiOpenPurchases = $apiDeliveryStatus->whereIn('label', ['planned', 'in_transit', 'partially_received']);
        $apiPurchaseQuantity = (float) $apiDeliveryStatus->sum('total_quantity_mt');
        $apiOpenQuantity = (float) $apiOpenPurchases->sum(fn ($purchase): float => max((float) $purchase->total_quantity_mt - (float) $purchase->received_quantity_mt, 0));

        $stockByPlant = Cache::remember(
            'procurement.dashboard.stock-by-plant.'.$company->company_code,
            now()->addMinutes(10),
            fn () => DB::table('tbl_company as plant')
                ->leftJoin('tbl_material_stocks as stock', function (JoinClause $join): void {
                    $join->on('stock.plant_code', '=', 'plant.company_code')
                        ->where('stock.status', 1);
                })
                ->where('plant.record_type', 2)
                ->where('plant.status', 1)
                ->where('plant.parent_company_id', $company->company_code)
                ->select('plant.company_name as label', DB::raw('COALESCE(SUM(stock.current_stock), 0) / 1000 as value'))
                ->groupBy('plant.id', 'plant.company_name')
                ->orderByDesc('value')
                ->get(),
        );

        $planningMonth = CarbonImmutable::now()->startOfMonth()->toDateString();
        $sapConsumptionByOffset = collect();
        $activeConsumptionCount = 0;
        $monthlyDemandMt = 0.0;

        if ($selectedSource === 'sap') {
            $sapConsumption = DB::table('tbl_material_stocks as stock')
                ->where('stock.status', 1)
                ->whereIn('stock.plant_code', $selectedPlantCodes);
            $sapConsumptionTrend = (clone $sapConsumption)
                ->when($selectedConsumptionMaterial !== '', fn ($query) => $query->where('stock.matnr', $selectedConsumptionMaterial))
                ->when($selectedConsumptionPlant !== '', fn ($query) => $query->where('stock.plant_code', $selectedConsumptionPlant));

            $sapSummary = Cache::remember(
                'procurement.dashboard.sap-summary.'.$company->company_code,
                now()->addMinutes(10),
                fn () => (clone $sapConsumption)
                    ->selectRaw('SUM(CASE WHEN stock.sap_current_month_consumption != 0 OR stock.sap_previous_month_consumption != 0 OR stock.sap_two_months_ago_consumption != 0 THEN 1 ELSE 0 END) as active_count')
                    ->selectRaw('COALESCE(SUM(stock.sap_current_month_consumption), 0) as current_consumption')
                    ->selectRaw('COALESCE(SUM(stock.sap_previous_month_consumption), 0) as previous_consumption')
                    ->selectRaw('COALESCE(SUM(stock.sap_two_months_ago_consumption), 0) as two_months_ago_consumption')
                    ->first(),
            );
            $activeConsumptionCount = (int) $sapSummary->active_count;
            $monthlyDemandMt = (float) $sapSummary->current_consumption / 1000;
            $plantDemand = Cache::remember(
                'procurement.dashboard.sap-plant-demand.'.$company->company_code,
                now()->addMinutes(10),
                fn () => DB::table('tbl_material_stocks as stock')
                    ->join('tbl_company as plant', 'plant.company_code', '=', 'stock.plant_code')
                    ->where('stock.status', 1)
                    ->whereIn('stock.plant_code', $selectedPlantCodes)
                    ->select('plant.company_name as label', DB::raw('COALESCE(SUM(stock.sap_current_month_consumption), 0) / 1000 as value'))
                    ->groupBy('plant.id', 'plant.company_name')
                    ->orderByDesc('value')
                    ->get(),
            );
            $materialDemand = Cache::remember(
                'procurement.dashboard.sap-material-demand.'.$company->company_code,
                now()->addMinutes(10),
                function () use ($selectedPlantCodes) {
                    $stockDemand = DB::table('tbl_material_stocks as stock')
                        ->selectRaw('COALESCE(SUM(stock.sap_current_month_consumption), 0) / 1000')
                        ->whereColumn('stock.matnr', 'material.matnr')
                        ->where('stock.status', 1)
                        ->whereIn('stock.plant_code', $selectedPlantCodes);

                    return DB::table('tbl_materials as material')
                        ->where('material.status', 1)
                        ->select('material.matnr as code', 'material.maktx as name')
                        ->selectSub($stockDemand, 'value')
                        ->orderByDesc('value')
                        ->limit(12)
                        ->get();
                },
            );
            $trendSummary = $selectedConsumptionMaterial === '' && $selectedConsumptionPlant === ''
                ? $sapSummary
                : (clone $sapConsumptionTrend)
                    ->selectRaw('COALESCE(SUM(stock.sap_current_month_consumption), 0) as current_consumption')
                    ->selectRaw('COALESCE(SUM(stock.sap_previous_month_consumption), 0) as previous_consumption')
                    ->selectRaw('COALESCE(SUM(stock.sap_two_months_ago_consumption), 0) as two_months_ago_consumption')
                    ->first();
            $sapConsumptionByOffset = collect([
                0 => (float) $trendSummary->current_consumption / $consumptionDivisor,
                1 => (float) $trendSummary->previous_consumption / $consumptionDivisor,
                2 => (float) $trendSummary->two_months_ago_consumption / $consumptionDivisor,
            ]);
        } else {
            $lockedManual = DB::table('procurement_daily_consumptions as consumption')
                ->join('procurement_planning_decisions as decision', function (JoinClause $join) use ($planningMonth): void {
                    $join->on('decision.company_id', '=', 'consumption.company_id')
                        ->on('decision.plant_id', '=', 'consumption.plant_id')
                        ->on('decision.material', '=', 'consumption.material')
                        ->whereDate('decision.planning_month', $planningMonth)
                        ->where('decision.planning_mode', 'manual')
                        ->where('decision.status', 1)
                        ->whereNotNull('decision.locked_at');
                })
                ->where('consumption.company_id', $company->id)
                ->where('consumption.status', 1);

            $activeConsumptionCount = (clone $lockedManual)->distinct()->count('consumption.id');
            $monthlyDemandMt = (float) (clone $lockedManual)->sum(DB::raw('consumption.quantity_mt * COALESCE(consumption.day_count, 1)'));
            $plantDemand = (clone $lockedManual)
                ->join('tbl_company as plant', 'plant.id', '=', 'consumption.plant_id')
                ->select('plant.company_name as label', DB::raw('SUM(consumption.quantity_mt * COALESCE(consumption.day_count, 1)) as value'))
                ->groupBy('plant.id', 'plant.company_name')
                ->orderByDesc('value')
                ->get();
            $materialDemand = (clone $lockedManual)
                ->leftJoin('tbl_materials as material', 'material.matnr', '=', 'consumption.material')
                ->select('consumption.material as code', DB::raw('MAX(material.maktx) as name'), DB::raw('SUM(consumption.quantity_mt * COALESCE(consumption.day_count, 1)) as value'))
                ->groupBy('consumption.material')
                ->orderByDesc('value')
                ->get();
        }

        $monthOffsets = $selectedSource === 'sap' ? range(2, 0) : range(5, 0);
        $monthlyConsumption = collect($monthOffsets)->map(function (int $monthsAgo) use ($monthlyDemandMt, $selectedSource, $sapConsumptionByOffset): array {
            $month = CarbonImmutable::now()->startOfMonth()->subMonths($monthsAgo);

            return [
                'month' => $month->format('M Y'),
                'value' => $selectedSource === 'sap'
                    ? (float) ($sapConsumptionByOffset[$monthsAgo] ?? 0)
                    : ($monthsAgo === 0 ? $monthlyDemandMt : 0.0),
            ];
        })->values();
        $inventoryMt = Cache::remember(
            'procurement.dashboard.inventory.'.$company->company_code,
            now()->addMinutes(10),
            fn (): float => (float) DB::table('tbl_material_stocks')
                ->where('status', 1)
                ->whereIn('plant_code', $selectedPlantCodes)
                ->sum('current_stock') / 1000,
        );

        return Inertia::render('modules/procurement/dashboard', [
            'companies' => $companies,
            'selectedCompany' => $company?->company_code,
            'selectedSource' => $selectedSource,
            'materials' => $materials,
            'selectedMaterial' => $selectedConsumptionMaterial,
            'consumptionPlants' => $consumptionPlants,
            'selectedConsumptionPlant' => $selectedConsumptionPlant,
            'consumptionUnit' => $consumptionDisplayUnit,
            'kpis' => [
                'active_consumptions' => $activeConsumptionCount,
                'monthly_demand_mt' => $monthlyDemandMt,
                'purchase_quantity_mt' => (float) $localPurchaseMetrics->purchase_quantity_mt + $apiPurchaseQuantity,
                'open_orders' => (int) $localPurchaseMetrics->open_orders + $apiOpenPurchases->count(),
                'open_order_quantity_mt' => (float) $localPurchaseMetrics->open_order_quantity_mt + $apiOpenQuantity,
                'inventory_mt' => $inventoryMt,
            ],
            'plantDemand' => $plantDemand,
            'materialDemand' => $materialDemand,
            'deliveryStatus' => $deliveryStatus,
            'stockByPlant' => $stockByPlant,
            'monthlyConsumption' => $monthlyConsumption,
        ]);
    }

    public function supplyPlan(int $consumption): Response
    {
        $plan = DB::table('vw_procurement_consumption_sheet')->where('id', $consumption)->first();
        abort_unless($plan, 404);
        $monthStart = CarbonImmutable::parse($plan->consumption_date)->startOfMonth();
        $plant = DB::table('tbl_company')->where('id', $plan->plant_id)->first(['company_code', 'company_name']);
        $company = DB::table('tbl_company')->where('id', $plan->company_id)->first(['company_code', 'company_name']);
        $material = DB::table('tbl_materials')->where('matnr', $plan->material)->first(['matnr', 'maktx', 'meins']);
        $this->syncVendorPurchaseOrdersForMaterial($plan->material);
        $purchases = DB::table('vw_procurement_purchase_receipts')
            ->where('material', $plan->material)
            ->where('plant_code', $plant?->company_code)
            ->where('status', 1)
            ->whereBetween('expected_delivery_date', [$monthStart->toDateString(), $monthStart->endOfMonth()->toDateString()])
            ->orderBy('expected_delivery_date')
            ->get()
            ->each(fn ($purchase) => $purchase->vendor_source = $purchase->source);
        $this->persistConsumptionCalculation($consumption);
        $storedResult = DB::table('procurement_balance_results')->where('material', $plan->material)->where('plant_id', $plan->plant_id)->whereDate('month', $monthStart->toDateString())->first();
        $calculation = json_decode((string) $storedResult?->calculations, true) ?: [];

        return Inertia::render('modules/procurement/planning/supply-plan', [
            'plan' => $plan,
            'plant' => $plant,
            'company' => $company,
            'material' => $material,
            'purchases' => $purchases,
            'mouPlans' => DB::table('procurement_mou_plans')->where('consumption_id', $plan->id)->orderBy('vendor_name')->get(),
            'vendors' => DB::table('tbl_vendor_purchase_orders')
                ->where('status', 1)
                ->where('plant_code', $plant?->company_code)
                ->where('material', $plan->material)
                ->whereNotNull('vendor_name')
                ->where('vendor_name', '!=', '')
                ->distinct()
                ->orderBy('vendor_name')
                ->pluck('vendor_name'),
            'summary' => [
                'opening_stock' => (float) ($calculation['opening_stock_mt'] ?? 0),
                'working_days' => (int) ($calculation['day_count'] ?? 1),
                'total_required' => (float) ($calculation['total_required_mt'] ?? 0),
                'projected_closing' => (float) ($calculation['projected_closing_mt'] ?? 0),
                'gross_shortage' => (float) ($calculation['gross_shortage_mt'] ?? 0),
                'remaining_shortage' => (float) ($calculation['remaining_shortage_mt'] ?? 0),
                'stock_cover' => (float) ($calculation['cover_days'] ?? 0),
            ],
        ]);
    }

    public function autoSupplyPlan(Request $request): Response
    {
        $filters = $request->validate([
            'company_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'plant_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'material' => ['required', 'string', 'max:50', 'exists:tbl_materials,matnr'],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $monthStart = CarbonImmutable::parse($filters['month'].'-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $company = DB::table('tbl_company')->where('id', $filters['company_id'])->first(['company_code', 'company_name']);
        $plant = DB::table('tbl_company')
            ->where('id', $filters['plant_id'])
            ->where('parent_company_id', $company?->company_code)
            ->first(['company_code', 'company_name']);
        abort_unless($company && $plant, 404);
        $material = DB::table('tbl_materials')->where('matnr', $filters['material'])->first(['matnr', 'maktx', 'meins']);
        abort_unless($material, 404);

        $decision = DB::table('procurement_planning_decisions')
            ->where('company_id', $filters['company_id'])
            ->where('plant_id', $filters['plant_id'])
            ->where('material', $filters['material'])
            ->whereDate('planning_month', $monthStart->toDateString())
            ->where('status', 1)
            ->value('planning_mode');
        abort_if($decision === 'manual', 404);

        $stock = DB::table('tbl_material_stocks')
            ->where('status', 1)
            ->where('plant_code', $plant->company_code)
            ->where('matnr', $filters['material'])
            ->selectRaw('COALESCE(MAX(current_stock), 0) / 1000 as opening_stock_mt, COALESCE(MAX(auto_perday_consumption), 0) / 1000 as daily_consumption_mt')
            ->first();
        $purchases = DB::table('vw_procurement_purchase_receipts')
            ->where('material', $filters['material'])
            ->where('plant_code', $plant->company_code)
            ->where('status', 1)
            ->whereBetween('expected_delivery_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('expected_delivery_date')
            ->get()
            ->each(fn ($purchase) => $purchase->vendor_source = $purchase->source);
        $openingStock = (float) ($stock?->opening_stock_mt ?? 0);
        $dailyConsumption = (float) ($stock?->daily_consumption_mt ?? 0);
        $workingDays = $monthEnd->day;
        $totalRequired = $dailyConsumption * $workingDays;
        $plannedBuying = (float) $purchases->sum('quantity_mt');
        $projectedClosing = ($openingStock + $plannedBuying) - $totalRequired;

        return Inertia::render('modules/procurement/planning/supply-plan', [
            'plan' => [
                'id' => null,
                'company_id' => (int) $filters['company_id'],
                'plant_id' => (int) $filters['plant_id'],
                'material' => $filters['material'],
                'consumption_date' => $monthStart->toDateString(),
                'quantity_mt' => $dailyConsumption,
                'planning_mode' => 'auto',
            ],
            'plant' => $plant,
            'company' => $company,
            'material' => $material,
            'purchases' => $purchases,
            'mouPlans' => DB::table('procurement_mou_plans')
                ->whereNull('consumption_id')
                ->where('company_id', $filters['company_id'])
                ->where('plant_id', $filters['plant_id'])
                ->where('material', $filters['material'])
                ->whereDate('planning_month', $monthStart->toDateString())
                ->orderBy('vendor_name')
                ->get(),
            'vendors' => DB::table('tbl_vendor_purchase_orders')
                ->where('status', 1)
                ->where('plant_code', $plant->company_code)
                ->where('material', $filters['material'])
                ->whereNotNull('vendor_name')
                ->where('vendor_name', '!=', '')
                ->distinct()
                ->orderBy('vendor_name')
                ->pluck('vendor_name'),
            'summary' => [
                'opening_stock' => $openingStock,
                'working_days' => $workingDays,
                'total_required' => $totalRequired,
                'projected_closing' => $projectedClosing,
                'gross_shortage' => max($totalRequired - $openingStock, 0),
                'remaining_shortage' => max(-$projectedClosing, 0),
                'stock_cover' => $dailyConsumption > 0 ? $openingStock / $dailyConsumption : 0,
            ],
        ]);
    }

    private function persistConsumptionCalculation(int $consumption): void
    {
        DB::statement('CALL sp_refresh_procurement_consumption_calculation(?)', [$consumption]);
    }

    private function syncVendorPurchaseOrdersForMaterial(?string $material = null): void
    {
        $fresh = DB::table('tbl_vendor_purchase_orders')
            ->when(filled($material), fn ($query) => $query->where('material', $material))
            ->where('synced_at', '>=', now()->subMinutes(15))
            ->exists();
        if ($fresh) {
            return;
        }

        $api = ApiIntegration::query()->where('code', 'vendor')->where('status', true)->first();
        if (! $api || blank($api->endpoint)) {
            return;
        }

        try {
            ImportMaterialsFromApi::dispatchSync($api->id, auth()->id(), $material);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function storeSupplyPlanPurchase(Request $request, int $consumption): RedirectResponse
    {
        $plan = DB::table('procurement_daily_consumptions')->where('id', $consumption)->first();
        abort_unless($plan, 404);
        $data = $request->validate(['vendor_name' => ['required', 'string', 'max:255'], 'quantity_mt' => ['required', 'numeric', 'gt:0'], 'expected_delivery_date' => ['required', 'date'], 'delivery_status' => ['required', Rule::in(['planned', 'in_transit', 'partially_received', 'received', 'cancelled'])], 'remarks' => ['nullable', 'string', 'max:1000']]);
        DB::table('procurement_purchases')->insert([...$data, 'company_id' => $plan->company_id, 'plant_id' => $plan->plant_id, 'consumption_id' => $plan->id, 'vendor_source' => 'local', 'material' => $plan->material, 'purchase_date' => CarbonImmutable::parse($plan->consumption_date)->startOfMonth()->toDateString(), 'reference' => null, 'status' => 1, 'submission_token' => (string) Str::uuid(), 'created_by' => $request->user()?->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->persistConsumptionCalculation($consumption);

        return back()->with('success', 'Manual purchase order created.');
    }

    public function storeMouPlan(Request $request, int $consumption): RedirectResponse
    {
        abort_unless(DB::table('procurement_daily_consumptions')->where('id', $consumption)->exists(), 404);
        $data = $request->validate(['vendor_name' => ['required', 'string', 'max:255'], 'vendor_code' => ['nullable', 'string', 'max:100'], 'quantity_mt' => ['required', 'numeric', 'gt:0'], 'remarks' => ['nullable', 'string', 'max:1000']]);
        DB::table('procurement_mou_plans')->updateOrInsert(['consumption_id' => $consumption, 'vendor_name' => $data['vendor_name']], [...$data, 'created_by' => $request->user()?->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'MOU lifting plan saved.');
    }

    public function storeAutoMouPlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'plant_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'material' => ['required', 'string', 'max:50', 'exists:tbl_materials,matnr'],
            'planning_month' => ['required', 'date'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'vendor_code' => ['nullable', 'string', 'max:100'],
            'quantity_mt' => ['required', 'numeric', 'gt:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $month = CarbonImmutable::parse($data['planning_month'])->startOfMonth()->toDateString();
        $decision = DB::table('procurement_planning_decisions')
            ->where('company_id', $data['company_id'])
            ->where('plant_id', $data['plant_id'])
            ->where('material', $data['material'])
            ->whereDate('planning_month', $month)
            ->where('status', 1)
            ->value('planning_mode');
        abort_if($decision === 'manual', 422, 'This material is configured for manual planning.');

        DB::table('procurement_mou_plans')->updateOrInsert(
            [
                'consumption_id' => null,
                'company_id' => $data['company_id'],
                'plant_id' => $data['plant_id'],
                'material' => $data['material'],
                'planning_month' => $month,
                'vendor_name' => $data['vendor_name'],
            ],
            [
                'vendor_code' => $data['vendor_code'] ?? null,
                'quantity_mt' => $data['quantity_mt'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return back()->with('success', 'MOU lifting plan saved.');
    }

    public function balanceSheet(Request $request): Response
    {
        $companies = Cache::remember('procurement:balance-companies:v2', now()->addMinutes(5), fn () => DB::table('tbl_company')
            ->where('status', 1)
            ->where('record_type', 1)
            ->whereIn('company_code', array_keys(self::COMPANY_MATERIAL_TYPES))
            ->orderByRaw("FIELD(company_code, '1000', '5000')")
            ->get(['id', 'company_code', 'company_name']));
        $companyCode = trim((string) $request->string('company_code'));
        $selectedCompany = $companyCode !== '' ? $companies->firstWhere('company_code', $companyCode) : null;
        if (! $selectedCompany && $request->integer('company_id') > 0) {
            $selectedCompany = $companies->firstWhere('id', $request->integer('company_id'));
        }
        $selectedCompany ??= $companies->first();
        $companyId = (int) ($selectedCompany->id ?? 0);
        $companyCode = (string) ($selectedCompany->company_code ?? '');
        $materialType = self::COMPANY_MATERIAL_TYPES[$companyCode] ?? null;
        $materials = Cache::remember('procurement:balance-materials:'.($materialType ?? 'none'), now()->addMinutes(2), fn () => DB::table('tbl_materials')
            ->where('status', 1)
            ->when($materialType, fn ($query) => $query->whereRaw('UPPER(TRIM(mtart)) = ?', [$materialType]))
            ->when(! $materialType, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('maktx')
            ->limit(5000)
            ->get(['matnr', 'maktx', 'mtart', 'matkl', 'meins']));
        $requestedMaterialCode = trim((string) $request->string('material'));
        $allMaterialsSelected = $requestedMaterialCode === 'all';
        $requestedDisplayMaterialCode = trim((string) $request->string('selected_material'));
        $displayMaterialCode = $allMaterialsSelected && $requestedDisplayMaterialCode !== ''
            ? $requestedDisplayMaterialCode
            : $requestedMaterialCode;
        $selectedMaterial = $displayMaterialCode !== '' && $displayMaterialCode !== 'all'
            ? $materials->firstWhere('matnr', $displayMaterialCode)
            : null;
        $selectedMaterial ??= $materials->first();
        $materialCode = (string) ($selectedMaterial->matnr ?? '');
        $materialFilter = $allMaterialsSelected ? 'all' : $materialCode;
        $requestedMonth = trim((string) $request->string('month'));
        $allMonthsSelected = preg_match('/^(\d{4})-all$/', $requestedMonth, $allMonthsMatch) === 1;
        $monthStart = $allMonthsSelected
            ? CarbonImmutable::create((int) $allMonthsMatch[1], 1, 1, 0, 0, 0, 'Asia/Kolkata')->startOfMonth()
            : (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedMonth)
                ? CarbonImmutable::parse($requestedMonth.'-01')->startOfMonth()
                : CarbonImmutable::now('Asia/Kolkata')->startOfMonth());
        $month = $allMonthsSelected ? $monthStart->format('Y').'-all' : $monthStart->format('Y-m');
        $scopeDecisions = DB::table('procurement_planning_decisions')
            ->where('company_id', $companyId)
            ->where('material', $materialCode)
            ->whereDate('planning_month', $monthStart->toDateString())
            ->where('status', 1);
        $planningMode = (clone $scopeDecisions)->where('planning_mode', 'auto')->exists() || ! (clone $scopeDecisions)->exists()
            ? 'auto'
            : 'manual';
        $monthCount = $allMonthsSelected ? 12 : 1;

        $plantsQuery = DB::table('tbl_company')
            ->where('status', 1)
            ->where('record_type', 2)
            ->whereRaw("LOWER(TRIM(company_name)) NOT IN ('head office', 'other plant')");
        if ($selectedCompany) {
            $plantsQuery->where('parent_company_id', $selectedCompany->company_code);
        }
        $plantCode = trim((string) $request->string('plant_code'));
        if ($plantCode === '' && $request->integer('plant_id') > 0) {
            $plantCode = (string) DB::table('tbl_company')->where('id', $request->integer('plant_id'))->value('company_code');
        }
        if ($plantCode !== '') {
            $plantsQuery->where('company_code', $plantCode);
        }
        $plants = $plantsQuery->orderBy('company_name')->get(['id', 'company_code', 'company_name', 'record_type', 'parent_company_id']);
        $selectedPlant = $plantCode !== '' ? $plants->firstWhere('company_code', $plantCode) : null;
        $plantId = (int) ($selectedPlant->id ?? 0);
        $plantCode = (string) ($selectedPlant->company_code ?? '');

        $periods = collect(range(0, $monthCount - 1))
            ->map(fn (int $offset) => $monthStart->addMonths($offset));
        $horizonEnd = $periods->last()->endOfMonth();
        $planningDecisions = DB::table('procurement_planning_decisions')
            ->where('company_id', $companyId)
            ->where('material', $materialCode)
            ->where('status', 1)
            ->whereBetween('planning_month', [$monthStart->toDateString(), $horizonEnd->toDateString()])
            ->get(['plant_id', 'planning_month', 'planning_mode'])
            ->keyBy(fn ($decision) => $decision->plant_id.'|'.CarbonImmutable::parse($decision->planning_month)->format('Y-m'));

        // The visual balance workspace must remain instant when switching tabs.
        // API imports are managed by the integration runner; do not block this GET.
        if ($materialCode !== '' && ! $request->routeIs('admin.procurement.balance-sheet')) {
            $this->syncVendorPurchaseOrdersForMaterial($materialCode);
        }
        $stocks = DB::table('tbl_material_stocks')
            ->where('matnr', $materialCode)
            ->where('status', 1)
            ->whereIn('plant_code', $plants->pluck('company_code'))
            ->select(
                'plant_code',
                DB::raw('SUM(current_stock) as current_stock'),
                DB::raw('SUM(auto_perday_consumption) as auto_perday_consumption'),
            )
            ->groupBy('plant_code')
            ->get()
            ->keyBy('plant_code');
        $consumptions = DB::table('procurement_daily_consumptions')
            ->where('material', $materialCode)
            ->where('status', 1)
            ->where('consumption_date', '<=', $horizonEnd->toDateString())
            ->whereIn('plant_id', $plants->pluck('id'))
            ->orderBy('consumption_date')
            ->get()
            ->groupBy('plant_id');
        $availableMonths = DB::table('procurement_daily_consumptions')
            ->where('material', $materialCode)
            ->where('company_id', $companyId)
            ->where('status', 1)
            ->when($plantId > 0, fn ($query) => $query->where('plant_id', $plantId))
            ->whereBetween('consumption_date', [
                CarbonImmutable::now('Asia/Kolkata')->startOfYear()->toDateString(),
                CarbonImmutable::now('Asia/Kolkata')->addYears(20)->endOfYear()->toDateString(),
            ])
            ->selectRaw("DATE_FORMAT(consumption_date, '%Y-%m') as period")
            ->distinct()
            ->orderBy('period')
            ->pluck('period')
            ->values();
        if ($allMonthsSelected) {
            $consumptionMonths = $consumptions
                ->flatten(1)
                ->map(fn ($consumption) => CarbonImmutable::parse($consumption->consumption_date)->format('Y-m'))
                ->unique();
            $periods = $periods
                ->filter(fn (CarbonImmutable $period) => $consumptionMonths->contains($period->format('Y-m')))
                ->values();
        }
        $purchases = DB::table('vw_procurement_purchase_receipts')
            ->where('material', $materialCode)
            ->whereBetween('expected_delivery_date', [$monthStart->toDateString(), $horizonEnd->toDateString()])
            ->whereIn('plant_code', $plants->pluck('company_code'))
            ->get()
            ->groupBy(fn ($row) => $row->plant_id.'|'.CarbonImmutable::parse($row->expected_delivery_date)->format('Y-m'));
        $savedResults = DB::table('procurement_balance_results')->where('material', $materialCode)->whereBetween('month', [$monthStart->toDateString(), $horizonEnd->toDateString()])->whereIn('plant_id', $plants->pluck('id'))->get()->keyBy(fn ($row) => $row->plant_id.'|'.CarbonImmutable::parse($row->month)->format('Y-m'));
        // In the All plants view every active plant must remain visible, even when
        // the selected material currently has no stock, consumption, or purchases.
        $displayPlants = $plants;
        $mouPlans = DB::table('procurement_mou_plans as mou')
            ->join('procurement_daily_consumptions as consumption', 'consumption.id', '=', 'mou.consumption_id')
            ->where('consumption.material', $materialCode)
            ->where('consumption.status', 1)
            ->whereBetween('consumption.consumption_date', [$monthStart->toDateString(), $horizonEnd->toDateString()])
            ->when($companyId > 0, fn ($query) => $query->where('consumption.company_id', $companyId))
            ->select('mou.vendor_name', 'mou.vendor_code', DB::raw('SUM(mou.quantity_mt) as quantity_mt'))
            ->groupBy('mou.vendor_name', 'mou.vendor_code')
            ->orderBy('mou.vendor_name')
            ->get();

        $rollingOpeningStock = $displayPlants->mapWithKeys(fn ($plant) => [
            // SAP stock is stored in KG; all procurement planning calculations use MT.
            $plant->id => (float) ($stocks->get($plant->company_code)?->current_stock ?? 0) / 1000,
        ])->all();

        $plantColumns = $periods->flatMap(function (CarbonImmutable $period) use ($displayPlants, $stocks, $consumptions, $purchases, $savedResults, $planningDecisions, &$rollingOpeningStock, $monthStart, $allMonthsSelected) {
            $periodKey = $period->format('Y-m');
            $periodStart = $period->startOfMonth();
            $periodEnd = $period->endOfMonth();

            return $displayPlants->map(function ($plant) use ($stocks, $consumptions, $purchases, $savedResults, $planningDecisions, &$rollingOpeningStock, $periodKey, $periodStart, $periodEnd, $monthStart, $allMonthsSelected) {
                $groupKey = $plant->id.'|'.$periodKey;
                $plantConsumptions = $consumptions->get($plant->id, collect());
                $hasPeriodConsumption = $plantConsumptions->contains(
                    fn ($plan) => CarbonImmutable::parse($plan->consumption_date)->format('Y-m') === $periodKey
                );
                $rowPlanningMode = $planningDecisions->get($groupKey)?->planning_mode
                    ?? $planningDecisions->get($plant->id.'|'.$monthStart->format('Y-m'))?->planning_mode
                    ?? 'auto';
                if (($allMonthsSelected || $rowPlanningMode === 'manual') && ! $hasPeriodConsumption) {
                    return null;
                }
                $activePlan = $plantConsumptions
                    ->filter(fn ($plan) => CarbonImmutable::parse($plan->consumption_date)->lte($periodEnd)
                        && (blank($plan->effective_to) || CarbonImmutable::parse($plan->effective_to)->gte($periodStart)))
                    ->sortByDesc('consumption_date')
                    ->first();
                $plantPurchases = $purchases->get($groupKey, collect());
                // Portal-generated POs remain actionable until their delivery is
                // cancelled. Some imported live records carry a false generic
                // status even though the portal PO is still planned, which made
                // the To Buy value show zero while the same PO appeared in More.
                // SAP rows continue to respect their integration status flag.
                $calculationPurchases = $plantPurchases->filter(fn ($purchase) => $purchase->delivery_status !== 'cancelled'
                    && ($purchase->source === 'local' || (bool) $purchase->status)
                );
                $openingStock = $rollingOpeningStock[$plant->id] ?? 0;
                $manualDailyConsumption = (float) ($activePlan?->quantity_mt ?? 0);
                $autoDailyConsumption = (float) ($stocks->get($plant->company_code)?->auto_perday_consumption ?? 0) / 1000;
                $dailyConsumption = $rowPlanningMode === 'auto' ? $autoDailyConsumption : $manualDailyConsumption;
                $isPlanStartMonth = $activePlan && CarbonImmutable::parse($activePlan->consumption_date)->format('Y-m') === $periodKey;
                $days = $rowPlanningMode === 'auto'
                    ? $periodEnd->day
                    : ($activePlan ? ($activePlan->period_type === 'datewise' ? 1 : ($isPlanStartMonth ? max(1, (int) $activePlan->day_count) : $periodEnd->day)) : $periodEnd->day);
                $plannedBuying = (float) ($activePlan?->planned_buying_mt ?? 0);
                // Buying / Imports represents SAP-generated POs only. Portal POs
                // are displayed separately under To Buy, but both sources still
                // contribute to Total Buying and stock availability.
                $localPurchases = $calculationPurchases->where('source', 'local');
                $supplierPurchases = $calculationPurchases->where('source', '!=', 'local');
                $localPurchaseQuantity = (float) $localPurchases->sum('quantity_mt');
                $sapPurchaseQuantity = (float) $supplierPurchases->sum('quantity_mt');
                $toBuy = $plannedBuying + $localPurchaseQuantity;
                $imports = $sapPurchaseQuantity;
                $supplierReceipts = $imports;
                $consumption = $dailyConsumption * $days;
                // Total Buying and shortage calculations include both categorized
                // PO sources even though Buying / Imports displays SAP only.
                $totalBuying = $toBuy + $sapPurchaseQuantity;
                $totalAvailability = $openingStock + $totalBuying;
                $closingStock = $totalAvailability - $consumption;
                $grossShortage = max(0, $consumption - ($openingStock + $plannedBuying));
                $remainingShortage = max(0, $grossShortage - $localPurchaseQuantity - $sapPurchaseQuantity);
                $purchaseOrders = $plantPurchases->map(function ($purchase) {
                    $totalQuantity = (float) ($purchase->total_quantity_mt ?? $purchase->quantity_mt);
                    $rate = (float) ($purchase->rate ?? 0);
                    $amount = (float) ($purchase->amount ?? 0);

                    // Legacy SAP imports stored POPRICE (the complete PO value)
                    // in rate and left amount at zero. Keep historical rows correct
                    // until the normalization migration has been deployed.
                    if ($amount <= 0 && $rate > 0 && $totalQuantity > 0) {
                        $amount = $rate;
                        $rate = $amount / $totalQuantity;
                    }

                    return [
                        'po_number' => $purchase->reference ?: 'Manual PO',
                        'vendor_name' => $purchase->vendor_name ?: 'Vendor not provided',
                        'quantity_mt' => (float) $purchase->quantity_mt,
                        'total_quantity_mt' => $totalQuantity,
                        'reduced_quantity_mt' => (float) ($purchase->reduced_quantity_mt ?? 0),
                        'balance_quantity_mt' => (float) ($purchase->balance_quantity_mt ?? $purchase->quantity_mt),
                        'rate' => $rate,
                        'amount' => $amount,
                        'currency' => $purchase->currency ?: 'INR',
                        'eta' => $purchase->expected_delivery_date,
                        'source' => $purchase->source ?? 'local',
                        'status' => $purchase->delivery_status,
                    ];
                })->values();
                $saved = $savedResults->get($groupKey);
                $remarks = $saved?->remarks ? (json_decode($saved->remarks, true) ?: []) : [];
                $rollingOpeningStock[$plant->id] = $closingStock;

                return [
                    'id' => (int) $plant->id,
                    'column_key' => $groupKey,
                    'month' => $periodKey,
                    'code' => $plant->company_code,
                    'name' => $plant->company_name.' ('.$periodEnd->format('F Y').')',
                    'consumption_id' => $activePlan?->id,
                    'opening_stock' => $openingStock,
                    'cover_days' => $dailyConsumption > 0 ? $openingStock / $dailyConsumption : null,
                    'to_buy' => $toBuy,
                    'supplier_receipts' => $supplierReceipts,
                    'purchase_orders' => $purchaseOrders,
                    'buying_imports' => $imports,
                    'total_buying' => $totalBuying,
                    'total_availability' => $totalAvailability,
                    'average_consumption' => $dailyConsumption,
                    'planning_mode' => $rowPlanningMode,
                    'days' => $days,
                    'consumption' => $consumption,
                    'gross_shortage' => $grossShortage,
                    'closing_stock' => $closingStock,
                    'remaining_shortage' => $remainingShortage,
                    'remarks' => $remarks,
                ];
            })->filter();
        })->values();

        return Inertia::render('modules/procurement/planning/balance-sheet', [
            'companies' => $companies,
            'plants' => $plants,
            'materials' => $materials,
            'columns' => $plantColumns,
            'mouPlans' => $mouPlans,
            'availableMonths' => $availableMonths,
            'selectedMaterial' => $selectedMaterial,
            'filters' => ['company_code' => $companyCode, 'plant_code' => $plantCode ?: null, 'material' => $materialFilter, 'month' => $month, 'months' => $monthCount],
            'planningMode' => $planningMode,
            'canManage' => $request->user()?->can('users.manage') ?? false,
        ]);
    }

    public function updateBalanceRemark(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material' => ['required', 'string', 'max:50'],
            'plant_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'month' => ['required', 'date_format:Y-m'],
            'metric' => ['required', Rule::in(['opening_stock', 'cover_days', 'to_buy', 'supplier_receipts', 'buying_imports', 'total_buying', 'total_availability', 'average_consumption', 'days', 'consumption', 'gross_shortage', 'closing_stock', 'remaining_shortage'])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $month = CarbonImmutable::parse($data['month'].'-01')->toDateString();
        $existing = DB::table('procurement_balance_results')->where('material', $data['material'])->where('plant_id', $data['plant_id'])->whereDate('month', $month)->first();
        $remarks = $existing?->remarks ? (json_decode($existing->remarks, true) ?: []) : [];
        if (filled($data['remark'] ?? null)) {
            $remarks[$data['metric']] = $data['remark'];
        } else {
            unset($remarks[$data['metric']]);
        }
        DB::table('procurement_balance_results')->updateOrInsert(
            ['material' => $data['material'], 'plant_id' => $data['plant_id'], 'month' => $month],
            ['calculations' => $existing?->calculations ?? '{}', 'remarks' => json_encode($remarks), 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()],
        );

        return back()->with('success', 'Balance sheet remark saved.');
    }

    private function normaliseMonth(string $month): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return $month;
        }

        return now()->format('Y-m');
    }

    public function index(Request $r): Response
    {
        $search = trim((string) $r->string('search'));
        $tab = $r->routeIs('admin.procurement.daily-consumption.index') ? 'consumptions' : 'purchases';
        $month = $this->normaliseMonth(trim((string) $r->string('month')));
        $monthStart = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $companies = DB::table('tbl_company')->where('status', 1)->orderBy('company_name')->get(['id', 'company_code', 'company_name', 'record_type', 'parent_company_id']);
        $purchases = $tab === 'purchases'
            ? DB::table('procurement_purchases as p')->leftJoin('tbl_company as c', 'c.id', '=', 'p.company_id')->leftJoin('tbl_company as plant', 'plant.id', '=', 'p.plant_id')->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('p.material', 'like', "%{$search}%")->orWhere('p.vendor_name', 'like', "%{$search}%")->orWhere('p.reference', 'like', "%{$search}%")))->select('p.*', 'c.company_name', 'plant.company_name as plant_name')->latest('p.purchase_date')->limit(200)->get()
            : collect();
        $consumptions = collect();
        $availableMonths = collect();
        if ($tab === 'consumptions') {
            $consumptionMonths = DB::table('procurement_daily_consumptions')
                ->where('status', 1)
                ->selectRaw("DATE_FORMAT(consumption_date, '%Y-%m') as period")
                ->distinct()
                ->pluck('period');
            $decisionMonths = DB::table('procurement_planning_decisions')
                ->where('status', 1)
                ->whereIn('planning_mode', ['auto', 'manual'])
                ->selectRaw("DATE_FORMAT(planning_month, '%Y-%m') as period")
                ->distinct()
                ->pluck('period');
            $availableMonths = $consumptionMonths
                ->merge($decisionMonths)
                ->filter()
                ->unique()
                ->sort()
                ->values();

            $automaticPurchases = DB::table('vw_procurement_purchase_receipts')
                ->where('status', 1)
                ->where('delivery_status', '!=', 'cancelled')
                ->whereBetween('expected_delivery_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->select(
                    'material',
                    'plant_code',
                    DB::raw('SUM(quantity_mt) as quantity_mt'),
                )
                ->groupBy('material', 'plant_code')
                ->get()
                ->keyBy(fn ($purchase) => $purchase->material.'|'.$purchase->plant_code);

            $automaticConsumptions = DB::table('tbl_materials as material')
                ->crossJoin('tbl_company as plant')
                ->join('tbl_company as company', function (JoinClause $join): void {
                    $join->on('company.company_code', '=', 'plant.parent_company_id')->where('company.record_type', 1);
                })
                ->leftJoin('tbl_material_stocks as stock', function (JoinClause $join): void {
                    $join->on('stock.matnr', '=', 'material.matnr')
                        ->on('stock.plant_code', '=', 'plant.company_code')
                        ->where('stock.status', 1);
                })
                ->leftJoin('procurement_planning_decisions as decision', function (JoinClause $join) use ($monthStart): void {
                    $join->on('decision.company_id', '=', 'company.id')
                        ->on('decision.plant_id', '=', 'plant.id')
                        ->on('decision.material', '=', 'material.matnr')
                        ->where('decision.planning_month', $monthStart->toDateString())
                        ->where('decision.status', 1);
                })
                ->where(fn ($query) => $query->whereNull('decision.id')->orWhere('decision.planning_mode', 'auto'))
                ->where('material.status', 1)
                ->where('plant.status', 1)
                ->where('plant.record_type', 2)
                ->whereRaw("LOWER(TRIM(plant.company_name)) NOT IN ('head office', 'other plant')")
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query->where('company.company_code', '1000')->whereRaw("UPPER(TRIM(material.mtart)) = 'ZRAW'"))
                    ->orWhere(fn ($query) => $query->where('company.company_code', '5000')->whereRaw("UPPER(TRIM(material.mtart)) = 'YRAW'")))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('material.matnr', 'like', "%{$search}%")
                    ->orWhere('material.maktx', 'like', "%{$search}%")
                    ->orWhere('company.company_name', 'like', "%{$search}%")
                    ->orWhere('plant.company_name', 'like', "%{$search}%")))
                ->select(
                    DB::raw('-MIN(stock.id) as id'),
                    'company.id as company_id',
                    'plant.id as plant_id',
                    'company.company_name',
                    'plant.company_name as plant_name',
                    'plant.company_code as plant_code',
                    'material.matnr as material',
                    DB::raw("'auto' as planning_mode"),
                    DB::raw('COALESCE(SUM(stock.current_stock), 0) / 1000 as opening_stock_mt'),
                    DB::raw('COALESCE(SUM(stock.auto_perday_consumption), 0) / 1000 as quantity_mt'),
                )
                ->groupBy('company.id', 'plant.id', 'company.company_name', 'plant.company_name', 'plant.company_code', 'material.matnr')
                ->orderBy('material.matnr')
                ->orderBy('plant.company_name')
                ->get()
                ->map(function ($consumption) use ($automaticPurchases, $monthStart, $monthEnd) {
                    $consumption->id ??= -((int) sprintf('%u', crc32($consumption->company_id.'|'.$consumption->plant_id.'|'.$consumption->material)) + 1);
                    $dailyConsumption = (float) $consumption->quantity_mt;
                    $days = $monthEnd->day;
                    $openingStock = (float) $consumption->opening_stock_mt;
                    $plannedBuying = (float) ($automaticPurchases->get($consumption->material.'|'.$consumption->plant_code)?->quantity_mt ?? 0);
                    $totalRequired = $dailyConsumption * $days;

                    $consumption->consumption_date = $monthStart->toDateString();
                    $consumption->effective_to = $monthEnd->toDateString();
                    $consumption->period_type = 'monthly';
                    $consumption->remarks = null;
                    $consumption->status = 1;
                    $consumption->planned_buying_mt = $plannedBuying;
                    $consumption->total_availability_mt = $openingStock + $plannedBuying;
                    $consumption->day_count = $days;
                    $consumption->total_required_mt = $totalRequired;
                    $consumption->projected_closing_mt = ($openingStock + $plannedBuying) - $totalRequired;
                    $consumption->gross_shortage_mt = max($totalRequired - $openingStock, 0);
                    $consumption->remaining_shortage_mt = max($totalRequired - ($openingStock + $plannedBuying), 0);
                    $consumption->cover_days = $dailyConsumption > 0 ? $openingStock / $dailyConsumption : null;

                    return $consumption;
                });

            $manualConsumptions = DB::table('vw_procurement_consumption_sheet as consumption')
                ->where('consumption.status', 1)
                ->whereBetween('consumption.consumption_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereRaw("LOWER(TRIM(consumption.plant_name)) NOT IN ('head office', 'other plant')")
                ->whereExists(fn ($query) => $query->selectRaw('1')
                    ->from('procurement_planning_decisions as decision')
                    ->whereColumn('decision.company_id', 'consumption.company_id')
                    ->whereColumn('decision.plant_id', 'consumption.plant_id')
                    ->whereColumn('decision.material', 'consumption.material')
                    ->whereDate('decision.planning_month', $monthStart->toDateString())
                    ->where('decision.planning_mode', 'manual')
                    ->where('decision.status', 1))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('consumption.material', 'like', "%{$search}%")
                    ->orWhere('consumption.company_name', 'like', "%{$search}%")
                    ->orWhere('consumption.plant_name', 'like', "%{$search}%")))
                ->select('consumption.*')
                ->addSelect(DB::raw("'manual' as planning_mode"))
                ->latest('consumption.consumption_date')
                ->limit(200)
                ->get();

            $consumptions = $automaticConsumptions->concat($manualConsumptions)->values();

            $materialDescriptions = DB::table('tbl_materials')
                ->whereIn('matnr', $consumptions->pluck('material')->unique())
                ->pluck('maktx', 'matnr');
            $materialUnits = DB::table('tbl_materials')
                ->whereIn('matnr', $consumptions->pluck('material')->unique())
                ->pluck('meins', 'matnr');
            $plantCodes = DB::table('tbl_company')
                ->whereIn('id', $consumptions->pluck('plant_id')->unique())
                ->pluck('company_code', 'id');
            $stockValues = DB::table('tbl_material_stocks')
                ->where('status', 1)
                ->whereIn('matnr', $consumptions->pluck('material')->unique())
                ->select('matnr', 'plant_code', DB::raw('SUM(stock_value) as stock_value'))
                ->groupBy('matnr', 'plant_code')
                ->get()
                ->keyBy(fn ($stock) => $stock->matnr.'|'.$stock->plant_code);
            $plannedPurchaseOrders = DB::table('vw_procurement_purchase_receipts')
                ->where('status', 1)
                ->where('delivery_status', 'planned')
                ->whereBetween('expected_delivery_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereIn('material', $consumptions->pluck('material')->unique())
                ->select('material', 'plant_code', DB::raw('SUM(quantity_mt) as planned_po_mt'))
                ->groupBy('material', 'plant_code')
                ->get()
                ->keyBy(fn ($purchase) => $purchase->material.'|'.$purchase->plant_code);

            $consumptions->each(function ($consumption) use ($materialDescriptions, $materialUnits, $plantCodes, $stockValues, $plannedPurchaseOrders): void {
                $consumption->material_desc = (string) ($materialDescriptions[$consumption->material] ?? '');
                $consumption->meins = (string) ($materialUnits[$consumption->material] ?? '');
                $consumption->plant_code = (string) ($consumption->plant_code ?? $plantCodes[$consumption->plant_id] ?? '');
                $consumption->stock_value = (float) ($stockValues->get($consumption->material.'|'.$consumption->plant_code)?->stock_value ?? 0);
                $consumption->planned_po_mt = (float) ($plannedPurchaseOrders->get($consumption->material.'|'.$consumption->plant_code)?->planned_po_mt ?? 0);
            });
        }

        // Row-level decisions now control the calculation. Keep manual entry tools
        // available so a material assigned to manual can receive its first plan.
        $planningMode = 'manual';

        return Inertia::render('modules/procurement/planning/consumption', [
            'purchases' => $purchases,
            'consumptions' => $consumptions,
            'companies' => $companies,
            'stats' => [
                'purchases' => DB::table('procurement_purchases')->where('status', 1)->count(),
                'purchase_mt' => (float) DB::table('procurement_purchases')->where('status', 1)->sum('quantity_mt'),
                'planned_mt' => (float) $consumptions->sum('quantity_mt'),
                'open_orders' => DB::table('procurement_purchases')->where('status', 1)->whereNotIn('delivery_status', ['received', 'cancelled'])->count(),
            ],
            'filters' => ['search' => $search, 'tab' => $tab, 'month' => $month],
            'availableMonths' => $availableMonths,
            'planningMode' => $planningMode,
            'canManagePurchases' => $r->user()?->can('procurement.purchases.create') ?? false,
            'canManageConsumptions' => $r->user()?->can('procurement.daily_consumption.create') ?? false,
        ]);
    }

    public function referenceData(): JsonResponse
    {
        return response()->json([
            'materials' => DB::table('tbl_materials')->where('status', 1)->orderBy('maktx')->limit(5000)->get(['matnr', 'maktx', 'mtart']),
            'materialStocks' => DB::table('tbl_material_stocks')
                ->where('status', 1)
                ->select('matnr', 'plant_code', DB::raw('MAX(current_stock) as current_stock'))
                ->groupBy('matnr', 'plant_code')
                ->get(),
            'manualPlanningDecisions' => DB::table('procurement_planning_decisions')
                ->where('status', 1)
                ->where('planning_mode', 'manual')
                ->get(['company_id', 'plant_id', 'material', 'planning_month']),
        ]);
    }

    public function vendors(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'plant_code' => ['required', 'string', 'max:30'],
            'material' => ['required', 'string', 'max:50'],
        ]);

        return response()->json([
            'vendors' => DB::table('tbl_vendor_purchase_orders')
                ->where('status', 1)
                ->where('plant_code', $filters['plant_code'])
                ->where('material', $filters['material'])
                ->whereNotNull('vendor_name')
                ->where('vendor_name', '!=', '')
                ->distinct()
                ->orderBy('vendor_name')
                ->pluck('vendor_name'),
        ]);
    }

    public function storePurchase(Request $r): RedirectResponse
    {
        $d = $r->validate(['company_id' => ['required', 'integer', 'exists:tbl_company,id'], 'plant_id' => ['required', 'integer', 'exists:tbl_company,id'], 'consumption_id' => ['nullable', 'integer', 'exists:procurement_daily_consumptions,id'], 'vendor_name' => ['required', 'string', 'max:255'], 'vendor_source' => ['required', Rule::in(['sap', 'local'])], 'material' => ['required', 'string', 'max:50'], 'purchase_date' => ['required', 'date'], 'quantity_mt' => ['required', 'numeric', 'gt:0'], 'expected_delivery_date' => ['required', 'date'], 'delivery_status' => ['required', Rule::in(['planned', 'in_transit', 'partially_received', 'received', 'cancelled'])], 'reference' => ['nullable', 'string', 'max:100'], 'remarks' => ['nullable', 'string', 'max:1000']]);
        DB::table('procurement_purchases')->insert([...$d, 'status' => 1, 'submission_token' => (string) Str::uuid(), 'created_by' => $r->user()?->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Purchase plan created.');
    }

    public function updateDelivery(Request $r, int $purchase): RedirectResponse
    {
        $d = $r->validate(['delivery_status' => ['required', Rule::in(['planned', 'in_transit', 'partially_received', 'received', 'cancelled'])]]);
        DB::table('procurement_purchases')->where('id', $purchase)->update([...$d, 'updated_at' => now()]);

        return back()->with('success', 'Delivery status updated.');
    }

    public function destroyPurchase(int $purchase): RedirectResponse
    {
        DB::table('procurement_purchases')->where('id', $purchase)->delete();

        return back()->with('success', 'Purchase plan deleted.');
    }

    public function storeConsumption(Request $r): RedirectResponse
    {
        $data = $r->validate(['company_id' => ['required', 'integer', 'exists:tbl_company,id'], 'consumption_date' => ['required', 'date'], 'period_type' => ['required', Rule::in(['monthly', 'datewise'])], 'day_count' => ['required', 'integer', 'between:1,31'], 'material' => ['required', 'string', 'max:50', 'exists:tbl_materials,matnr'], 'common_remarks' => ['nullable', 'string', 'max:1000'], 'entries' => ['required', 'array', 'min:1'], 'entries.*.plant_id' => ['required', 'integer', 'exists:tbl_company,id'], 'entries.*.quantity_mt' => ['required', 'numeric', 'gt:0'], 'entries.*.remarks' => ['nullable', 'string', 'max:1000']]);
        $this->assertManualPlanning($data['company_id'], collect($data['entries'])->pluck('plant_id')->all(), $data['material'], $data['consumption_date']);
        $rows = collect($data['entries'])->map(fn ($entry) => ['company_id' => $data['company_id'], 'plant_id' => $entry['plant_id'], 'consumption_date' => $data['consumption_date'], 'effective_to' => null, 'period_type' => $data['period_type'], 'day_count' => $data['day_count'], 'material' => $data['material'], 'quantity_mt' => $entry['quantity_mt'], 'planned_buying_mt' => 0, 'imports_mt' => 0, 'total_buying_mt' => 0, 'remarks' => $entry['remarks'] ?: ($data['common_remarks'] ?? null), 'status' => 1, 'submission_token' => (string) Str::uuid(), 'created_by' => $r->user()?->id, 'created_at' => now(), 'updated_at' => now()])->all();
        DB::table('procurement_daily_consumptions')->insert($rows);

        return back()->with('success', count($rows).' plant consumption entries created.');
    }

    public function destroyConsumption(int $consumption): RedirectResponse
    {
        $plan = DB::table('procurement_daily_consumptions')->where('id', $consumption)->where('status', 1)->first();
        abort_unless($plan, 404);
        $this->assertManualPlanning((int) $plan->company_id, [(int) $plan->plant_id], $plan->material, $plan->consumption_date);
        DB::table('procurement_daily_consumptions')->where('id', $consumption)->update(['status' => 0, 'updated_at' => now()]);

        return back()->with('success', 'Consumption plan moved to inactive status.');
    }

    public function updateConsumption(Request $request, int $consumption): RedirectResponse
    {
        abort_unless(DB::table('procurement_daily_consumptions')->where('id', $consumption)->where('status', 1)->exists(), 404);
        $data = $request->validate(['company_id' => ['required', 'integer', 'exists:tbl_company,id'], 'consumption_date' => ['required', 'date'], 'period_type' => ['required', Rule::in(['monthly', 'datewise'])], 'day_count' => ['required', 'integer', 'between:1,31'], 'material' => ['required', 'string', 'max:50', 'exists:tbl_materials,matnr'], 'common_remarks' => ['nullable', 'string', 'max:1000'], 'entries' => ['required', 'array', 'size:1'], 'entries.0.plant_id' => ['required', 'integer', 'exists:tbl_company,id'], 'entries.0.quantity_mt' => ['required', 'numeric', 'gt:0'], 'entries.0.remarks' => ['nullable', 'string', 'max:1000']]);
        $entry = $data['entries'][0];
        $this->assertManualPlanning($data['company_id'], [(int) $entry['plant_id']], $data['material'], $data['consumption_date']);
        DB::table('procurement_daily_consumptions')->where('id', $consumption)->update(['company_id' => $data['company_id'], 'plant_id' => $entry['plant_id'], 'consumption_date' => $data['consumption_date'], 'period_type' => $data['period_type'], 'day_count' => $data['day_count'], 'material' => $data['material'], 'quantity_mt' => $entry['quantity_mt'], 'remarks' => $entry['remarks'] ?: ($data['common_remarks'] ?? null), 'updated_at' => now()]);

        return back()->with('success', 'Consumption plan updated.');
    }

    public function bulkConsumptions(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'exists:procurement_daily_consumptions,id'], 'action' => ['required', Rule::in(['delete'])]]);
        DB::table('procurement_daily_consumptions')->whereIn('id', $data['ids'])->get()
            ->each(fn ($plan) => $this->assertManualPlanning((int) $plan->company_id, [(int) $plan->plant_id], $plan->material, $plan->consumption_date));
        DB::table('procurement_daily_consumptions')->whereIn('id', $data['ids'])->update(['status' => 0, 'updated_at' => now()]);

        return back()->with('success', count($data['ids']).' consumption plans moved to inactive status.');
    }

    private function assertManualPlanning(int $companyId, array $plantIds, string $material, string $date): void
    {
        $month = CarbonImmutable::parse($date)->startOfMonth()->toDateString();
        $plantIds = collect($plantIds)->map(fn ($plantId) => (int) $plantId)->unique()->values();
        $manualPlantCount = DB::table('procurement_planning_decisions')
            ->where('company_id', $companyId)
            ->whereIn('plant_id', $plantIds)
            ->where('material', $material)
            ->whereDate('planning_month', $month)
            ->where('planning_mode', 'manual')
            ->where('status', 1)
            ->distinct('plant_id')
            ->count('plant_id');

        abort_unless($manualPlantCount === $plantIds->count(), 422, 'Manual consumption can only be changed for materials assigned to Manual planning.');
    }

    public function emptyConsumptions(): RedirectResponse
    {
        $deleted = DB::transaction(function (): array {
            return [
                'MOU plans' => DB::table('procurement_mou_plans')->delete(),
                'purchases' => DB::table('procurement_purchases')->delete(),
                'balance results' => DB::table('procurement_balance_results')->delete(),
                'consumption plans' => DB::table('procurement_daily_consumptions')->delete(),
                'planning decisions' => DB::table('procurement_planning_decisions')->delete(),
                'vendor purchase orders' => DB::table('tbl_vendor_purchase_orders')->delete(),
                'material stocks' => DB::table('tbl_material_stocks')->delete(),
            ];
        });

        $count = array_sum($deleted);

        return back()->with('success', $count.' procurement record(s) permanently deleted.');
    }
}
