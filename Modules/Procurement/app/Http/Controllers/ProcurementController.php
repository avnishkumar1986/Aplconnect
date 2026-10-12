<?php

namespace Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MaterialStockService;
use App\Services\ConsumptionRequirementService;
use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Procurement\Services\MaterialBalanceExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\Process;

class ProcurementController extends Controller
{
    private const PLANNING_MATERIAL_TYPES = ['ZRAW', 'YRAW'];
    private const PROCUREMENT_DATA_TABLES = [
        'procurement_balance_results',
        'procurement_mou_plans',
        'procurement_plan_histories',
        'procurement_purchases',
        'procurement_daily_consumptions',
    ];
    private const DELIVERY_STATUSES = [
        'planned' => 'Purchase Order Done',
        'in_transit' => 'In Transit',
        'partially_received' => 'Partially Received',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    public function overview(Request $request)
    {
        Gate::authorize('procurement.overview.view');
        $companies = $this->companies('procurement.overview.all_companies');
        $companyIds = $companies->pluck('id');
        $companyCodes = $companies->pluck('company_code');
        $plants = $this->plants($companyIds)->get();
        $selectedPlant = (string) $request->input('plant', '');
        if ($selectedPlant !== '' && ! $plants->contains(fn ($plant) => (string) $plant->company_code === $selectedPlant)) {
            $selectedPlant = '';
        }
        $selectedPlants = $selectedPlant === ''
            ? $plants
            : $plants->filter(fn ($plant) => (string) $plant->company_code === $selectedPlant)->values();
        $selectedPlantIds = $selectedPlants->pluck('id');
        $selectedPlantCodes = $selectedPlants->pluck('company_code');
        $materials = DB::table(app(MaterialStockService::class)->readTable())
            ->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)
            ->whereIn('plant_code', $selectedPlantCodes)->whereNotNull('matnr')->where('matnr', '<>', '')
            ->selectRaw('matnr as code, MAX(maktx) as name')->groupBy('matnr')->orderBy('name')->get();
        $defaultMaterial = $materials->contains(fn ($material) => $material->code === 'RMRESPVCPP')
            ? 'RMRESPVCPP'
            : (string) ($materials->first()?->code ?? '');
        $selectedMaterial = (string) $request->input('material', $defaultMaterial);
        if (! $materials->contains(fn ($material) => $material->code === $selectedMaterial)) $selectedMaterial = (string) ($materials->first()?->code ?? '');
        $stats = [
            'companies' => $companyIds->count(),
            'plants' => $selectedPlants->count(),
            'purchases' => DB::table('procurement_purchases')->whereIn('company_id', $companyIds)->whereIn('plant_id', $selectedPlantIds)->where('status', 1)->when($selectedMaterial !== '', fn ($query) => $query->where('material', $selectedMaterial))->count(),
            'purchase_mt' => DB::table('procurement_purchases')->whereIn('company_id', $companyIds)->whereIn('plant_id', $selectedPlantIds)->where('status', 1)->when($selectedMaterial !== '', fn ($query) => $query->where('material', $selectedMaterial))->sum('quantity_mt'),
            'planned_consumption_mt' => DB::table('procurement_daily_consumptions')->whereIn('company_id', $companyCodes)->whereIn('plant_id', $selectedPlantCodes)->where('status', 1)->when($selectedMaterial !== '', fn ($query) => $query->where('material', $selectedMaterial))->sum('quantity_mt'),
        ];

        $chartStart = now()->startOfMonth();
        $chartEnd = $chartStart->copy()->addMonths(5)->endOfMonth();
        $purchaseByMonth = DB::table('procurement_purchases')
            ->whereIn('company_id', $companyIds)->whereIn('plant_id', $selectedPlantIds)->where('status', 1)
            ->when($selectedMaterial !== '', fn ($query) => $query->where('material', $selectedMaterial))
            ->whereBetween('purchase_date', [$chartStart->toDateString(), $chartEnd->toDateString()])
            ->selectRaw("DATE_FORMAT(purchase_date, '%Y-%m') as month_key, SUM(quantity_mt) as total")
            ->groupBy('month_key')->pluck('total', 'month_key');
        $consumptionByMonth = DB::table('procurement_daily_consumptions')
            ->whereIn('company_id', $companyCodes)->whereIn('plant_id', $selectedPlantCodes)->where('status', 1)
            ->when($selectedMaterial !== '', fn ($query) => $query->where('material', $selectedMaterial))
            ->whereBetween('consumption_date', [$chartStart->toDateString(), $chartEnd->toDateString()])
            ->selectRaw("DATE_FORMAT(consumption_date, '%Y-%m') as month_key, SUM(quantity_mt) as total")
            ->groupBy('month_key')->pluck('total', 'month_key');
        $months = collect(range(0, 5))->map(fn ($offset) => $chartStart->copy()->addMonths($offset));
        $chart = [
            'months' => $months->map(fn ($month) => [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'purchases' => (float) ($purchaseByMonth[$month->format('Y-m')] ?? 0),
                'consumption' => (float) ($consumptionByMonth[$month->format('Y-m')] ?? 0),
            ])->values(),
            'plants' => DB::table('procurement_daily_consumptions as consumption')
                ->leftJoin('tbl_company as plant', 'plant.company_code', '=', 'consumption.plant_id')
                ->whereIn('consumption.company_id', $companyCodes)->whereIn('consumption.plant_id', $selectedPlantCodes)->where('consumption.status', 1)
                ->when($selectedMaterial !== '', fn ($query) => $query->where('consumption.material', $selectedMaterial))
                ->selectRaw('consumption.plant_id, COALESCE(MAX(plant.company_name), consumption.plant_id) as plant_name, SUM(consumption.quantity_mt) as total')
                ->groupBy('consumption.plant_id')->orderByDesc('total')->limit(8)->get()
                ->map(fn ($plant) => ['name' => $plant->plant_name, 'code' => $plant->plant_id, 'total' => (float) $plant->total]),
        ];

        return view('procurement::overview', compact('stats', 'chart', 'plants', 'materials', 'selectedPlant', 'selectedMaterial'));
    }

    public function materialBalance(Request $request)
    {
        Gate::authorize('procurement.overview.view');
        $companies = $this->companies('procurement.overview.all_companies');
        $filterPlants = $this->plants($companies->pluck('id'))->orderBy('plant.company_name')->get();
        $selectedCompany = (string) $request->input('company', $companies->first()?->id ?? '');
        if ($selectedCompany !== '' && ! $companies->contains(fn ($company) => (string) $company->id === $selectedCompany)) $selectedCompany = '';
        $companyPlants = $selectedCompany === '' ? $filterPlants : $filterPlants->where('company_id', (int) $selectedCompany)->values();
        $selectedPlant = (string) $request->input('plant', '');
        if ($selectedPlant !== '' && ! $companyPlants->contains(fn ($plant) => (string) $plant->id === $selectedPlant)) $selectedPlant = '';
        $allPlants = $selectedPlant === '' ? $companyPlants : $companyPlants->where('id', (int) $selectedPlant)->values();
        $plantCodes = $allPlants->pluck('company_code')->filter()->values();
        $plantIds = $allPlants->pluck('id');

        $catalog = DB::table(app(MaterialStockService::class)->readTable())->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)->whereNotNull('matnr')->where('matnr', '<>', '')
            ->whereIn('plant_code', $plantCodes);
        $filterMaterials = (clone $catalog)
            ->select('matnr', DB::raw('MAX(maktx) AS maktx'), DB::raw('MAX(matkl) AS category_name'))->groupBy('matnr')->orderBy('maktx')->get();
        $materials = $filterMaterials->values();
        $selectedMaterial = (string) $request->input('material', $materials->first()?->matnr);
        if (! $materials->contains('matnr', $selectedMaterial)) $selectedMaterial = (string) $materials->first()?->matnr;
        if ($selectedMaterial !== '') {
            app(ConsumptionRequirementService::class)->refresh([$selectedMaterial]);
        }

        $plannedPeriods = DB::table('procurement_daily_consumptions')->where('material', $selectedMaterial)->where('status', 1)
            ->whereIn('plant_id', $plantCodes)->orderBy('consumption_date')->get(['consumption_date', 'effective_to', 'period_type']);
        $plannedMonths = $plannedPeriods->flatMap(function ($row) {
            $start = Carbon::parse($row->consumption_date)->startOfMonth();
            $end = $row->period_type === 'datewise' && $row->effective_to
                ? Carbon::parse($row->effective_to)->startOfMonth()
                : $start->copy();
            $months = [];
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) $months[] = $cursor->copy();
            return $months;
        })->unique(fn ($date) => $date->format('Y-m'))->sortBy(fn ($date) => $date->format('Y-m'))->values();
        if ($plannedMonths->isEmpty()) $plannedMonths = collect([now()->startOfMonth()]);
        // Display only months that have an active consumption entry for the
        // selected material and plants.
        $months = $plannedMonths;
        $horizon = $months->count();
        $startMonth = $months->first()->copy()->startOfMonth();
        $rangeEnd = $months->last()->copy()->endOfMonth();

        $consumptions = DB::table('procurement_daily_consumptions')->where('material', $selectedMaterial)->where('status', 1)
            ->whereIn('plant_id', $plantCodes)->whereDate('consumption_date', '<=', $rangeEnd->toDateString())->get();
        $localPurchases = DB::table('procurement_purchases as purchase')
            ->leftJoin('procurement_daily_consumptions as ordered_plan', 'ordered_plan.id', '=', 'purchase.consumption_id')
            ->where('purchase.material', $selectedMaterial)->where('purchase.status', 1)
            ->where('purchase.delivery_status', '<>', 'cancelled')
            ->whereIn('purchase.plant_id', $plantIds)
            ->where(function ($query) use ($startMonth, $rangeEnd) {
                $query->whereBetween('ordered_plan.consumption_date', [$startMonth->toDateString(), $rangeEnd->toDateString()])
                    ->orWhere(function ($legacy) use ($startMonth, $rangeEnd) {
                        $legacy->whereNull('purchase.consumption_id')
                            ->whereBetween('purchase.purchase_date', [$startMonth->toDateString(), $rangeEnd->toDateString()]);
                    });
            })
            ->select('purchase.*', 'ordered_plan.consumption_date as ordered_plan_date')->get();
        $apiPurchases = DB::table('tbl_vendors')->where('status', 1)
            ->where('material_code', $selectedMaterial)->whereIn('plant_code', $plantCodes)
            // SAP can still return open purchase-order items whose requested
            // delivery date is before the planning horizon. Keep those overdue
            // receipts and carry them into the first visible planning month.
            ->whereNotNull('delivery_date')->whereDate('delivery_date', '<=', $rangeEnd->toDateString())
            ->whereNotNull('mt_quantity')->where('mt_quantity', '>', 0)->get();
        $mouPlanning = DB::table('procurement_mou_plans as mou')->join('procurement_daily_consumptions as consumption', 'consumption.id', '=', 'mou.consumption_id')
            ->where('consumption.status', 1)->where('consumption.material', $selectedMaterial)->whereIn('consumption.plant_id', $plantCodes)
            ->whereBetween('consumption.consumption_date', [$startMonth->toDateString(), $rangeEnd->toDateString()])
            ->select('mou.vendor_name')->selectRaw("DATE_FORMAT(consumption.consumption_date, '%Y-%m') as month_key")
            ->selectRaw('SUM(mou.quantity_mt) as quantity_mt')
            ->groupBy('mou.vendor_name', 'month_key')->orderBy('mou.vendor_name')->orderBy('month_key')->get();

        $plants = $allPlants->values();

        $savedRemarks = DB::table('procurement_balance_results')
            ->where('material', $selectedMaterial)
            ->whereIn('plant_id', $plantIds)
            ->whereBetween('month', [$startMonth->toDateString(), $rangeEnd->toDateString()])
            ->get(['plant_id', 'month', 'remarks'])
            ->mapWithKeys(function ($row) {
                $remarks = json_decode((string) $row->remarks, true);
                if (! is_array($remarks)) $remarks = filled($row->remarks) ? ['opening' => $row->remarks] : [];
                return [Carbon::parse($row->month)->format('Y-m').'|'.$row->plant_id => $remarks];
            });

        $matrix = [];
        $stockService = app(ConsumptionRequirementService::class);
        $stocks = app(MaterialStockService::class)->stockByPlantQuery()->where('matnr', $selectedMaterial)->whereIn('plant_code', $plantCodes)->get()
            ->mapWithKeys(fn ($stock) => [$stock->plant_code => $stockService->toMetricTonnes((float) $stock->current_stock, $stock->meins)]);
        $carry = $plants->mapWithKeys(fn ($plant) => [$plant->id => $stocks->has($plant->company_code) ? $stocks[$plant->company_code] : 0.0])->all();
        foreach ($months as $month) {
            $monthKey = $month->format('Y-m');
            foreach ($plants as $plant) {
                $applicableConsumptions = $consumptions->filter(fn ($row) => (string) $row->plant_id === (string) $plant->company_code
                        && (string) $row->consumption_date <= $month->copy()->endOfMonth()->toDateString()
                        && (empty($row->effective_to) || (string) $row->effective_to >= $month->copy()->startOfMonth()->toDateString()));
                $datewiseConsumptions = $applicableConsumptions->filter(fn ($row) => (string) ($row->period_type ?? '') === 'datewise');
                $monthlyConsumption = $applicableConsumptions->reject(fn ($row) => (string) ($row->period_type ?? '') === 'datewise')
                    ->sortByDesc('consumption_date')->first();
                $workingDays = $month->daysInMonth;
                $expectedConsumption = 0.0;
                // A carried monthly rate fills only the dates before the first
                // explicit date-wise change. Once date-wise planning starts,
                // the old monthly rate no longer continues after that point.
                if ($datewiseConsumptions->isNotEmpty()) {
                    $datewiseMetrics = $this->datewiseMonthlyMetrics($datewiseConsumptions, $month, $monthlyConsumption);
                    $workingDays = $datewiseMetrics['working_days'];
                    $expectedConsumption = $datewiseMetrics['expected_consumption'];
                } elseif ($monthlyConsumption) {
                    // Derive the inclusive calendar-day count from the effective
                    // date. This also corrects older rows that stored a full
                    // month's day_count even when their plan began mid-month.
                    $workingDays = str_starts_with((string) $monthlyConsumption->consumption_date, $monthKey)
                        ? (int) round(Carbon::parse($monthlyConsumption->consumption_date)
                            ->startOfDay()
                            ->diffInDays($month->copy()->endOfMonth()->startOfDay())) + 1
                        : $month->daysInMonth;
                    $expectedConsumption = (float) $monthlyConsumption->daily_consumption_mt * $workingDays;
                }
                $dailyConsumption = $workingDays > 0 ? $expectedConsumption / $workingDays : 0;
                // Allocate a PO to the monthly plan from which it was ordered.
                // ETA remains descriptive and must not move supply into another month.
                $local = $localPurchases->filter(function ($row) use ($plant, $monthKey) {
                    $orderedFor = $row->ordered_plan_date ?: $row->purchase_date;
                    return (int) $row->plant_id === (int) $plant->id
                        && str_starts_with((string) $orderedFor, $monthKey);
                })->values();
                $formReceipts = $local;
                $localSupplyOrders = $local->map(fn ($purchase) => (object) [
                    'source' => ($purchase->vendor_source ?? 'sap') === 'local' ? 'Local purchase' : 'Manual purchase plan',
                    'vendor_name' => $purchase->vendor_name, 'vendor_code' => null,
                    'purchase_order' => $purchase->reference, 'purchase_order_item' => null,
                    'delivery_date' => $purchase->expected_delivery_date, 'mt_quantity' => (float) $purchase->quantity_mt,
                    'delivery_status_label' => self::DELIVERY_STATUSES[$purchase->delivery_status] ?? null,
                ]);
                $manualReferences = $local->pluck('reference')->filter()->all();
                $firstPlanningMonthKey = $startMonth->format('Y-m');
                $api = $apiPurchases->filter(function ($row) use ($plant, $monthKey, $firstPlanningMonthKey, $manualReferences) {
                    $deliveryMonthKey = substr((string) $row->delivery_date, 0, 7);
                    $allocatedMonthKey = $deliveryMonthKey < $firstPlanningMonthKey ? $firstPlanningMonthKey : $deliveryMonthKey;
                    if ((string) $row->plant_code !== (string) $plant->company_code || $allocatedMonthKey !== $monthKey) return false;
                    if ($row->purchase_order && in_array($row->purchase_order, $manualReferences, true)) return false;
                    return true;
                })->values();
                $apiSupplyOrders = $api->map(fn ($purchase) => (object) [
                    'source' => 'API purchase order',
                    'vendor_name' => $purchase->vendor_name, 'vendor_code' => $purchase->vendor_code,
                    'purchase_order' => $purchase->purchase_order, 'purchase_order_item' => $purchase->purchase_order_item,
                    'delivery_date' => $purchase->delivery_date, 'mt_quantity' => (float) $purchase->mt_quantity,
                    'delivery_status_label' => $purchase->release_state ?: 'API synchronized',
                ]);
                $supplyOrders = $localSupplyOrders->concat($apiSupplyOrders)->sortBy('delivery_date')->values();
                $cell = app(\App\Services\MaterialBalanceCalculator::class)->calculate($carry[$plant->id] ?? null,
                    (float) $local->sum('quantity_mt'), (float) $api->sum('mt_quantity'), $dailyConsumption, $workingDays);
                $remarks = $savedRemarks->get($monthKey.'|'.$plant->id, []);
                $matrix[$monthKey][$plant->id] = $cell + ['orders' => $supplyOrders, 'sap_orders' => $api, 'local_orders' => $formReceipts, 'remarks' => $remarks];
                $key = ['material' => $selectedMaterial, 'plant_id' => $plant->id, 'month' => $month->toDateString()];
                $existing = DB::table('procurement_balance_results')->where($key)->exists();
                DB::table('procurement_balance_results')->updateOrInsert($key, [
                    'calculations' => json_encode($cell, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ] + ($existing ? [] : ['created_at' => now()]));
                $carry[$plant->id] = $cell['closing'];
            }
        }

        $previewId = session('material_balance_preview');
        return response()
            ->view('procurement::material-balance', compact('companies', 'filterPlants', 'selectedCompany', 'selectedPlant', 'filterMaterials', 'materials', 'selectedMaterial', 'horizon', 'months', 'plants', 'matrix', 'mouPlanning', 'previewId'))
            // This report is rebuilt from live consumption and purchase data.
            // Never allow navigation or an intermediary cache to restore an
            // earlier calculation after a plan has been edited.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function updateMaterialBalanceRemarks(Request $request)
    {
        Gate::authorize('procurement.daily_consumption.edit');

        $data = $request->validate([
            'material' => ['required', 'string', 'max:40'],
            'plant_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'month' => ['required', 'date_format:Y-m'],
            'row_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_:-]+$/'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth()->toDateString();
        $key = ['material' => $data['material'], 'plant_id' => $data['plant_id'], 'month' => $month];
        $existing = DB::table('procurement_balance_results')->where($key)->first();
        abort_unless($existing, 404);

        $remarks = json_decode((string) $existing->remarks, true);
        if (! is_array($remarks)) $remarks = filled($existing->remarks) ? ['opening' => $existing->remarks] : [];
        if (filled($data['remarks'] ?? null)) $remarks[$data['row_key']] = trim($data['remarks']);
        else unset($remarks[$data['row_key']]);

        DB::table('procurement_balance_results')->where('id', $existing->id)->update([
            'remarks' => $remarks === [] ? null : json_encode($remarks, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Material balance remarks updated successfully.');
    }

    public function exportMaterialBalanceExcel(Request $request, MaterialBalanceExporter $exporter)
    {
        Gate::authorize('procurement.overview.view');
        $data = $this->materialBalance($request)->getOriginalContent()->getData();
        $spreadsheet = $exporter->spreadsheet($data);
        $fileName = 'material-balance-'.$data['selectedMaterial'].'-'.$data['months']->first()->format('Y-m').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function exportMaterialBalancePdf(Request $request)
    {
        Gate::authorize('procurement.overview.view');
        $data = $this->materialBalance($request)->getOriginalContent()->getData();
        $fileName = 'material-balance-'.$data['selectedMaterial'].'-'.$data['months']->first()->format('Y-m').'.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('procurement::material-balance-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($fileName);
    }

    public function importMaterialBalance(Request $request)
    {
        Gate::authorize('procurement.overview.view');
        $validated = $request->validate([
            'balance_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $spreadsheet = IOFactory::load($validated['balance_file']->getRealPath());
        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Html($spreadsheet);
        $writer->setSheetIndex(0);
        ob_start();
        $writer->save('php://output');
        $html = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        $previewId = (string) Str::uuid();
        Storage::disk('local')->put('material-balance-previews/'.$previewId.'.html', $html);
        session(['material_balance_preview' => $previewId]);

        return redirect()->route('admin.procurement.material-balance', $request->only('company', 'plant', 'material_group', 'material', 'month', 'horizon'))
            ->with('success', 'Excel sheet uploaded. The formatted preview is shown below the live plan.');
    }

    public function materialBalanceImportPreview(string $preview)
    {
        Gate::authorize('procurement.overview.view');
        abort_unless(hash_equals((string) session('material_balance_preview'), $preview), 403);
        $path = 'material-balance-previews/'.$preview.'.html';
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:;",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function materials(Request $request)
    {
        Gate::authorize('procurement.all_materials.view');
        $companies = $this->companies('procurement.all_materials.all_companies');
        $companyIds = $companies->pluck('id');
        if ($request->boolean('datatable')) return $this->materialsDataTable($request, $companyIds);

        $plants = $this->plants($companyIds)->get();
        $stockOptions = DB::table(app(MaterialStockService::class)->readTable())->whereIn('plant_code', $plants->pluck('company_code'))->where('status', 1)->orderBy('maktx')->get(['matnr', 'maktx', 'matkl']);
        return view('procurement::materials', [
            'isMaster' => $request->routeIs('admin.materials.index'), 'companies' => $companies, 'plants' => $plants,
            'groups' => $stockOptions->pluck('matkl')->filter()->unique()->sort()->values(),
            'materialOptions' => $stockOptions->pluck('maktx', 'matnr')->all(),
        ]);
    }

    public function truncateProcurementData()
    {
        Gate::authorize('procurement.all_materials.all_companies');

        DB::transaction(function (): void {
            foreach (self::PROCUREMENT_DATA_TABLES as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'status')) {
                    $values = ['status' => 0];
                    if (Schema::hasColumn($table, 'updated_at')) {
                        $values['updated_at'] = now();
                    }
                    DB::table($table)->where('status', '<>', 0)->update($values);
                }
            }
        });

        return redirect()->route('admin.materials.index')
            ->with('success', 'Active procurement data was cleared successfully. Records were retained with status 0.');
    }

    public function dumpProcurementData()
    {
        Gate::authorize('procurement.all_materials.all_companies');

        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}", []);
        abort_unless(($connection['driver'] ?? null) === 'mysql', 422, 'SQL dump is available only for the MySQL connection.');

        $binary = dirname(base_path(), 2).DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysqldump.exe';
        if (! is_file($binary)) {
            $binary = 'mysqldump';
        }

        $filters = [
            'procurement_daily_consumptions' => 'status = 1',
            'procurement_purchases' => 'status = 1',
            'procurement_plan_histories' => 'consumption_id IN (SELECT id FROM procurement_daily_consumptions WHERE status = 1)',
            'procurement_mou_plans' => 'consumption_id IN (SELECT id FROM procurement_daily_consumptions WHERE status = 1)',
            'procurement_balance_results' => "EXISTS (SELECT 1 FROM procurement_daily_consumptions c JOIN tbl_company consumption_plant ON consumption_plant.company_code = c.plant_id WHERE c.status = 1 AND c.material = procurement_balance_results.material AND consumption_plant.id = procurement_balance_results.plant_id AND DATE_FORMAT(c.consumption_date, '%Y-%m') = DATE_FORMAT(procurement_balance_results.month, '%Y-%m'))",
        ];
        $filters = collect($filters)->filter(fn ($where, $table) => Schema::hasTable($table))->all();
        abort_if($filters === [], 422, 'No procurement tables are available to back up.');

        $commonCommand = [
            $binary,
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.(string) ($connection['username'] ?? ''),
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--skip-lock-tables',
            '--add-drop-table',
        ];

        try {
            $dump = "-- Active procurement records only (status = 1)\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            foreach ($filters as $table => $where) {
                $process = new Process([
                    ...$commonCommand,
                    '--where='.$where,
                    (string) ($connection['database'] ?? ''),
                    $table,
                ], base_path(), [
                    'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
                ]);
                $process->setTimeout(300)->run();
                if (! $process->isSuccessful()) {
                    report(new \RuntimeException('Procurement SQL dump failed for '.$table.': '.$process->getErrorOutput()));
                    return back()->with('error', 'Unable to create the procurement SQL dump. Please check the database service.');
                }
                $dump .= $process->getOutput()."\n";
            }
            $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'Unable to create the procurement SQL dump.');
        }

        $filename = 'procurement-active-dump-'.now()->format('Y-m-d-His').'.sql';
        return response($dump, 200, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function vendors(Request $request)
    {
        Gate::authorize('procurement.all_materials.view');
        $companies = $this->companies('procurement.all_materials.all_companies');
        $plants = $this->plants($companies->pluck('id'))->get();
        $plantCodes = $plants->pluck('company_code');
        $catalogTable = app(MaterialStockService::class)->readTable();

        $catalog = DB::table($catalogTable)->whereIn('plant_code', $plantCodes)
            ->select('matnr', 'plant_code', 'maktx', 'matkl')->get();
        $summaryQuery = DB::table('tbl_vendors')->whereIn('plant_code', $plantCodes)->where('status', 1);
        $summary = (object) [
            'companies' => (clone $summaryQuery)->whereNotNull('company_code')->distinct()->count('company_code'),
            'plants' => (clone $summaryQuery)->whereNotNull('plant_code')->distinct()->count('plant_code'),
            'materials' => (clone $summaryQuery)->distinct()->count('material_code'),
            'vendors' => (clone $summaryQuery)->whereNotNull('vendor_code')->distinct()->count('vendor_code'),
            'last_synced_at' => (clone $summaryQuery)->max('synced_at'),
        ];

        $query = DB::table('tbl_vendors as vendor')
            ->leftJoin('tbl_company as company', function ($join) {
                $join->on('company.company_code', '=', 'vendor.company_code')->where('company.record_type', 1);
            })
            ->leftJoin('tbl_company as plant', function ($join) {
                $join->on('plant.company_code', '=', 'vendor.plant_code')->where('plant.record_type', '<>', 1);
            })
            ->leftJoin($catalogTable.' as material', function ($join) {
                $join->on('material.matnr', '=', 'vendor.material_code')
                    ->on('material.plant_code', '=', 'vendor.plant_code');
            })
            ->whereIn('vendor.plant_code', $plantCodes)
            ->when($request->filled('company'), fn ($q) => $q->where('vendor.company_code', (string) $request->input('company')))
            ->when($request->filled('plant'), fn ($q) => $q->where('vendor.plant_code', (string) $request->input('plant')))
            ->when($request->filled('material_group'), fn ($q) => $q->where('material.matkl', (string) $request->input('material_group')))
            ->when($request->filled('material'), fn ($q) => $q->where('vendor.material_code', (string) $request->input('material')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.trim((string) $request->input('search')).'%';
                $q->where(fn ($where) => $where->where('vendor.vendor_code', 'like', $like)
                    ->orWhere('vendor.vendor_name', 'like', $like)
                    ->orWhere('vendor.purchase_order', 'like', $like)
                    ->orWhere('vendor.material_code', 'like', $like)
                    ->orWhere('material.maktx', 'like', $like));
            })
            ->select(
                'vendor.company_code', 'company.company_name', 'vendor.plant_code',
                'plant.company_name as plant_name', 'material.matkl as material_group',
                'vendor.material_code', 'material.maktx as material_name',
                'vendor.vendor_code', 'vendor.vendor_name'
            )
            ->selectRaw('COUNT(DISTINCT vendor.purchase_order) AS purchase_order_count')
            ->selectRaw('SUM(COALESCE(vendor.mt_quantity, 0)) AS total_quantity_mt')
            ->selectRaw('MAX(vendor.synced_at) AS last_synced_at')
            ->selectRaw('MAX(vendor.status) AS status')
            ->groupBy(
                'vendor.company_code', 'company.company_name', 'vendor.plant_code', 'plant.company_name',
                'material.matkl', 'vendor.material_code', 'material.maktx',
                'vendor.vendor_code', 'vendor.vendor_name'
            )
            ->orderBy('company.company_name')->orderBy('plant.company_name')
            ->orderBy('material.matkl')->orderBy('material.maktx')->orderBy('vendor.vendor_name');

        return view('procurement::vendors', [
            'vendors' => $query->paginate(config('app.table_page_length'))->withQueryString(),
            'companies' => $companies,
            'plants' => $plants,
            'groups' => $catalog->pluck('matkl')->filter()->unique()->sort()->values(),
            'materials' => $catalog->unique(fn ($row) => $row->plant_code.'|'.$row->matnr)->values(),
            'summary' => $summary,
        ]);
    }

    private function materialsDataTable(Request $request, $companyIds)
    {
        $query = DB::table(app(MaterialStockService::class)->readTable().' as stock')
            ->leftJoin('tbl_company as plant', 'plant.company_code', '=', 'stock.plant_code')
            ->whereIn('stock.plant_code', $this->plants($companyIds)->pluck('plant.company_code'));
        $total = (clone $query)->count();
        $query->when($request->filled('company_id'), fn ($q) => $q->whereIn('stock.plant_code',
                $this->plants($companyIds->filter(fn ($id) => (int) $id === $request->integer('company_id')))->pluck('plant.company_code')))
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant.id', $request->integer('plant_id')))
            ->when($request->filled('material_group'), fn ($q) => $q->where('stock.matkl', (string) $request->input('material_group')))
            ->when($request->filled('material'), fn ($q) => $q->where('stock.matnr', (string) $request->input('material')));

        $search = trim((string) data_get($request->input('search'), 'value', ''));
        if ($search !== '') {
            $query->where(function ($where) use ($search) {
                $like = '%'.$search.'%';
                $where->where('stock.matnr', 'like', $like)
                    ->orWhere('stock.maktx', 'like', $like)
                    ->orWhere('stock.mtart', 'like', $like)
                    ->orWhere('stock.matkl', 'like', $like)
                    ->orWhere('stock.meins', 'like', $like)
                    ->orWhere('stock.plant_code', 'like', $like)
                    ->orWhere('plant.company_name', 'like', $like);
            });
        }
        $filtered = (clone $query)->count();
        // Responsive control and S.NO. occupy the first two client columns.
        $orderColumns = ['stock.matnr', 'stock.matnr', 'stock.maktx', 'stock.mtart', 'stock.matkl', 'stock.meins', 'plant.company_name', 'stock.current_stock', 'stock.stock_value', 'stock.status', DB::raw('COALESCE(stock.stock_updated_at, stock.material_updated_at)')];
        $orderIndex = min(10, max(0, (int) data_get($request->input('order'), '0.column', 10)));
        $direction = data_get($request->input('order'), '0.dir') === 'desc' ? 'desc' : 'asc';
        $length = min(100, max(10, (int) $request->input('length', 10)));
        $start = max(0, (int) $request->input('start', 0));

        $showActions = $request->input('context') === 'master';
        $rows = $query->select('stock.*', 'plant.company_name as plant_name')
            ->orderBy($orderColumns[$orderIndex], $direction)->offset($start)->limit($length)->get()
            ->map(fn ($row, $index) => [
                'sno' => $start + $index + 1,
                'key' => $row->matnr,
                'label' => $row->maktx,
                'mtart' => $row->mtart,
                'matkl' => $row->matkl,
                'meins' => $row->meins,
                'plant_code' => $row->plant_code,
                'plant_name' => $row->plant_name ?: '—',
                'current_stock' => number_format((float) $row->current_stock, 3),
                'stock_value' => number_format((float) $row->stock_value, 3),
                'status' => $row->status ? 'Active' : 'Inactive',
                'updated_at' => ($row->stock_updated_at ?? $row->material_updated_at ?? null)
                    ? Carbon::parse($row->stock_updated_at ?? $row->material_updated_at)->timestamp : 0,
                'remarks' => $row->remarks ?? '-',
                'edit_url' => $showActions && Gate::allows('procurement.all_materials.edit') ? route('admin.procurement.materials.edit', [$row->matnr, 'return_to' => 'master']) : null,
                'delete_url' => $showActions && Gate::allows('procurement.all_materials.delete') ? route('admin.procurement.materials.destroy', $row->matnr) : null,
            ]);

        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows]);
    }

    public function editMaterial(string $material)
    {
        Gate::authorize('procurement.all_materials.edit');
        $record = DB::table(MaterialStockService::MATERIAL_TABLE)->where('matnr', $material)->first();
        abort_unless($record, 404);
        return view('procurement::material-form', compact('record'));
    }

    public function updateMaterial(Request $request, string $material)
    {
        Gate::authorize('procurement.all_materials.edit');
        abort_unless(DB::table(MaterialStockService::MATERIAL_TABLE)->where('matnr', $material)->exists(), 404);
        $data = $request->validate([
            'maktx' => ['required', 'string', 'max:255'],
            'mtart' => ['nullable', 'string', 'max:20'],
            'matkl' => ['nullable', 'string', 'max:40'],
            'meins' => ['nullable', 'string', 'max:10'],
            'status' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($material, $data) {
            DB::table(MaterialStockService::MATERIAL_TABLE)->where('matnr', $material)->update($data + [
                'updated_at' => now(),
            ]);

            // Plant stock descriptions take precedence in the consolidated
            // material view, so keep every plant row aligned with the master.
            DB::table(MaterialStockService::STOCK_TABLE)->where('matnr', $material)->update([
                'material_desc' => $data['maktx'],
                'updated_at' => now(),
            ]);

            if (Schema::hasColumn('procurement_daily_consumptions', 'material_group')) {
                DB::table('procurement_daily_consumptions')->where('material', $material)->update([
                    'material_group' => $data['matkl'] ?: null,
                    'updated_at' => now(),
                ]);
            }
        });

        // Unit, status, stock description and group changes can affect every
        // downstream planning figure for this material.
        app(ConsumptionRequirementService::class)->refresh([$material], false);

        return redirect()->route($request->input('return_to') === 'master' ? 'admin.materials.index' : 'admin.procurement.materials')
            ->with('success', 'Material and all related planning records updated successfully.');
    }

    public function destroyMaterial(string $material)
    {
        Gate::authorize('procurement.all_materials.delete');
        abort_unless(DB::table(MaterialStockService::MATERIAL_TABLE)->where('matnr', $material)->exists(), 404);
        DB::transaction(function () use ($material) {
            DB::table(MaterialStockService::STOCK_TABLE)->where('matnr', $material)->delete();
            DB::table(MaterialStockService::MATERIAL_TABLE)->where('matnr', $material)->delete();
        });
        return back()->with('success', 'Material deleted.');
    }

    public function purchases(Request $request)
    {
        Gate::authorize('procurement.purchases.view');
        $companies = $this->companies('procurement.purchases.all_companies');
        $companyIds = $companies->pluck('id');
        $plants = $this->plants($companyIds)->get();
        $entries = $this->purchaseQuery($companyIds)
            ->when($request->filled('company_id'), fn ($q) => $q->where('purchase.company_id', $request->integer('company_id')))
            ->when($request->filled('plant_id'), fn ($q) => $q->where('purchase.plant_id', $request->integer('plant_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->string('search')).'%';
                $q->where(fn ($x) => $x->where('purchase.vendor_name', 'like', $term)->orWhere('purchase.reference', 'like', $term)->orWhere('purchase.material', 'like', $term));
            })->orderByDesc('purchase.purchase_date')->orderByDesc('purchase.id')->get();
        $entries->transform(function ($entry) {
            $entry->current_stock_mt = $entry->stock_unit === null && $entry->current_stock == 0
                ? 0 : app(ConsumptionRequirementService::class)->toMetricTonnes((float) $entry->current_stock, $entry->stock_unit);
            return $entry;
        });
        return view('procurement::purchases', ['entries' => $entries, 'materials' => $this->materialsMap(), 'deliveryStatuses' => self::DELIVERY_STATUSES]);
    }

    public function createPurchase(Request $request)
    {
        Gate::authorize('procurement.purchases.create');
        $companies = $this->companies('procurement.purchases.all_companies');
        $plants = $this->plants($companies->pluck('id'))->get();
        $materials = DB::table(app(MaterialStockService::class)->readTable())->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)->whereIn('plant_code', $plants->pluck('company_code'))->whereNotNull('matkl')->where('matkl', '<>', '')
            ->select('matnr', DB::raw('MAX(maktx) AS maktx'), 'matkl', 'plant_code')
            ->groupBy('matnr', 'matkl', 'plant_code')->orderBy('plant_code')->orderBy('matkl')->orderBy('maktx')->get();
        $vendors = $this->purchaseVendors();

        $prefill = $request->validate([
            'company_id' => ['nullable', 'integer'], 'plant_id' => ['nullable', 'integer'],
            'material_group' => ['nullable', 'string', 'max:40'],
            'material' => ['nullable', 'string', Rule::exists(app(MaterialStockService::class)->readTable(), 'matnr')->where(fn ($query) => $query->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES))],
            'quantity_mt' => ['nullable', 'numeric', 'min:0.001'],
        ]);
        if (empty($prefill['company_id']) && $companies->isNotEmpty()) {
            $prefill['company_id'] = (int) $companies->first()->id;
        }
        if (! empty($prefill['company_id'])) abort_unless($companies->contains('id', (int) $prefill['company_id']), 403);
        if (! empty($prefill['plant_id'])) {
            $plant = $plants->firstWhere('id', (int) $prefill['plant_id']);
            abort_unless($plant && (int) $plant->company_id === (int) ($prefill['company_id'] ?? 0), 403);
        }
        $prefill['company_code'] = empty($prefill['company_id']) ? null : $companies->firstWhere('id', (int) $prefill['company_id'])?->company_code;
        $prefill['plant_code'] = empty($prefill['plant_id']) ? null : $plants->firstWhere('id', (int) $prefill['plant_id'])?->company_code;
        if (! empty($prefill['material'])) {
            $prefill['material_group'] = $materials->firstWhere('matnr', $prefill['material'])?->matkl;
        }

        return view('procurement::purchase-form', [
            'prefill' => $prefill,
            'stockMap' => $this->stockMap($plants),
            'companies' => $companies,
            'plants' => $plants,
            'materials' => $materials,
            'groups' => $materials->pluck('matkl')->unique()->values(),
            'vendors' => $vendors,
            'deliveryStatuses' => self::DELIVERY_STATUSES,
            'token' => (string) Str::uuid(),
        ]);
    }

    public function storePurchase(Request $request)
    {
        Gate::authorize('procurement.purchases.create');
        $request->merge(['purchase_date' => now()->toDateString()]);
        $linkedPlan = $request->filled('return_to_consumption')
            ? DB::table('procurement_daily_consumptions')->where('id', $request->integer('return_to_consumption'))->first(['id', 'consumption_date'])
            : null;
        $deliveryDateRules = ['required', 'date'];
        if ($linkedPlan) {
            $linkedPlan = DB::table('procurement_daily_consumptions')->where('id', $linkedPlan->id)->first(['id', 'consumption_date', 'effective_to', 'period_type']);
            $planningDate = Carbon::parse($linkedPlan->consumption_date);
            $deliveryDateRules[] = 'after_or_equal:'.$planningDate->copy()->startOfMonth()->toDateString();
            $deliveryDateRules[] = 'before_or_equal:'.$planningDate->copy()->endOfMonth()->toDateString();
        } else {
            $deliveryDateRules[] = 'after_or_equal:purchase_date';
        }
        $isLocalVendor = $request->input('vendor_name') === '__other__';
        $vendorRule = Rule::exists('tbl_vendors', 'vendor_name')->where(function ($query) use ($request, $linkedPlan) {
            $query->where('status', 1);
            if (! $linkedPlan) $query->where('material_code', $request->input('material'));
        });
        $vendorRules = ['required', 'string', 'max:255'];
        if (! $isLocalVendor) $vendorRules[] = $vendorRule;
        $data = $request->validate([
            'company_id' => ['required', 'string', 'exists:tbl_company,company_code'],
            'plant_id' => ['required', 'string', 'exists:tbl_company,company_code'],
            'vendor_name' => $vendorRules,
            'local_vendor_name' => [$isLocalVendor ? 'required' : 'nullable', 'string', 'max:255'],
            'material_group' => ['required', 'string', 'max:40'],
            'material' => ['required', Rule::exists(app(MaterialStockService::class)->readTable(), 'matnr')->where(fn ($query) => $query->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)->where('matkl', $request->input('material_group')))],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'quantity_mt' => ['required', 'numeric', 'min:0.001'],
            'expected_delivery_date' => $deliveryDateRules,
            'delivery_status' => ['required', Rule::in(array_keys(self::DELIVERY_STATUSES))],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('procurement_purchases', 'reference')],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'submission_token' => ['required', 'uuid', 'unique:procurement_purchases,submission_token'],
            'return_to_consumption' => ['nullable', 'integer', 'exists:procurement_daily_consumptions,id'],
        ]);
        if ($isLocalVendor) $data['vendor_name'] = trim((string) $data['local_vendor_name']);
        $data['vendor_source'] = $isLocalVendor ? 'local' : 'sap';
        unset($data['local_vendor_name']);
        $returnToConsumption = $data['return_to_consumption'] ?? null;
        unset($data['return_to_consumption'], $data['material_group']);
        $data['consumption_id'] = $returnToConsumption;
        $data['company_id'] = (int) DB::table('tbl_company')->where('company_code', $data['company_id'])->value('id');
        $data['plant_id'] = (int) DB::table('tbl_company')->where('company_code', $data['plant_id'])->value('id');
        $this->authorizeCompanyPlant((int) $data['company_id'], (int) $data['plant_id'], 'procurement.purchases.all_companies');
        DB::transaction(function () use (&$data, $returnToConsumption) {
            $purchaseId = DB::table('procurement_purchases')->insertGetId($data + [
                'status' => 1, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (empty($data['reference'])) {
                $data['reference'] = 'MPO-'.str_pad((string) $purchaseId, 8, '0', STR_PAD_LEFT);
                DB::table('procurement_purchases')->where('id', $purchaseId)->update([
                    'reference' => $data['reference'], 'updated_at' => now(),
                ]);
            }
        });

        app(ConsumptionRequirementService::class)->refresh([$data['material']]);
        if ($returnToConsumption) {
            return redirect()->route('admin.procurement.consumptions.show', $returnToConsumption)->with('success', 'Supplier receipt saved successfully.');
        }
        return redirect()->route('admin.procurement.overview')->with('success', 'Purchase saved successfully.');
    }

    public function updatePurchaseDeliveryStatus(Request $request, int $purchase)
    {
        Gate::authorize('procurement.purchases.create');
        $data = $request->validate(['delivery_status' => ['required', Rule::in(array_keys(self::DELIVERY_STATUSES))]]);
        $record = DB::table('procurement_purchases')->where('id', $purchase)->first();
        abort_unless($record, 404);
        $this->authorizeCompanyPlant((int) $record->company_id, (int) $record->plant_id, 'procurement.purchases.all_companies');
        DB::table('procurement_purchases')->where('id', $purchase)->update($data + ['updated_at' => now()]);
        app(ConsumptionRequirementService::class)->refresh([$record->material]);
        return back()->with('success', 'Supplier delivery status updated.');
    }

    public function editPurchase(int $purchase)
    {
        Gate::authorize('procurement.purchases.create');
        $record = DB::table('procurement_purchases')->where('id', $purchase)->where('status', 1)->first();
        abort_unless($record, 404);
        $this->authorizeCompanyPlant((int) $record->company_id, (int) $record->plant_id, 'procurement.purchases.all_companies');
        $vendors = $this->purchaseVendors(null, true);
        return view('procurement::purchase-edit-form', [
            'record' => $record,
            'vendors' => $vendors,
            'deliveryStatuses' => self::DELIVERY_STATUSES,
        ]);
    }

    public function updatePurchase(Request $request, int $purchase)
    {
        Gate::authorize('procurement.purchases.create');
        $record = DB::table('procurement_purchases')->where('id', $purchase)->where('status', 1)->first();
        abort_unless($record, 404);
        $this->authorizeCompanyPlant((int) $record->company_id, (int) $record->plant_id, 'procurement.purchases.all_companies');
        $deliveryRules = ['required', 'date'];
        if ($record->consumption_id) {
            $plan = DB::table('procurement_daily_consumptions')->where('id', $record->consumption_id)->first(['consumption_date', 'effective_to', 'period_type']);
            abort_unless($plan, 422, 'The linked consumption plan is unavailable.');
            $planningDate = Carbon::parse($plan->consumption_date);
            $deliveryRules[] = 'after_or_equal:'.$planningDate->copy()->startOfMonth()->toDateString();
            $deliveryRules[] = 'before_or_equal:'.$planningDate->copy()->endOfMonth()->toDateString();
        }
        $isLocalVendor = $request->input('vendor_name') === '__other__';
        $vendorRules = ['required', 'string', 'max:255'];
        if (! $isLocalVendor) $vendorRules[] = Rule::exists('tbl_vendors', 'vendor_name')->where(fn ($query) => $query->where('status', 1));
        $data = $request->validate([
            'vendor_name' => $vendorRules,
            'local_vendor_name' => [$isLocalVendor ? 'required' : 'nullable', 'string', 'max:255'],
            'quantity_mt' => ['required', 'numeric', 'min:0.001'],
            'expected_delivery_date' => $deliveryRules,
            'delivery_status' => ['required', Rule::in(array_keys(self::DELIVERY_STATUSES))],
        ]);
        if ($isLocalVendor) $data['vendor_name'] = trim((string) $data['local_vendor_name']);
        $data['vendor_source'] = $isLocalVendor ? 'local' : 'sap';
        unset($data['local_vendor_name']);
        DB::table('procurement_purchases')->where('id', $record->id)->update($data + ['updated_at' => now()]);
        app(ConsumptionRequirementService::class)->refresh([$record->material]);
        $redirect = $record->consumption_id
            ? route('admin.procurement.consumptions.show', $record->consumption_id)
            : route('admin.procurement.overview');
        return redirect($redirect)->with('success', 'Supplier receipt updated successfully.');
    }

    public function destroyPurchase(int $purchase)
    {
        Gate::authorize('procurement.purchases.create');
        $record = DB::table('procurement_purchases')->where('id', $purchase)->where('status', 1)->first();
        abort_unless($record, 404);
        $this->authorizeCompanyPlant((int) $record->company_id, (int) $record->plant_id, 'procurement.purchases.all_companies');
        DB::table('procurement_purchases')->where('id', $record->id)->delete();
        app(ConsumptionRequirementService::class)->refresh([$record->material]);
        return back()->with('success', 'Supplier receipt deleted and shortage recalculated.');
    }

    public function syncPurchaseVendors(Request $request)
    {
        Gate::authorize('procurement.purchases.create');
        $data = $request->validate([
            'material' => ['required', 'string', Rule::exists(app(MaterialStockService::class)->readTable(), 'matnr')->where(fn ($query) => $query->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES))],
        ]);

        $exitCode = Artisan::call('sap:sync-vendors', ['material' => $data['material']]);
        $message = trim(Artisan::output());
        if ($exitCode !== 0) {
            return response()->json(['message' => $message ?: 'Unable to import vendors from SAP.'], 422);
        }

        $vendors = $this->purchaseVendors($data['material']);

        return response()->json(['message' => $message, 'vendors' => $vendors]);
    }

    public function consumptions(Request $request)
    {
        Gate::authorize('procurement.daily_consumption.view');
        $companies = $this->companies('procurement.daily_consumption.all_companies');
        $companyIds = $companies->pluck('id');
        $plants = $this->plants($companyIds)->get();
        // Calculations are refreshed by every create/edit/delete, purchase and
        // stock-sync write path. Keep this read-only listing fast instead of
        // recalculating the complete planning history on every page load.
        $entries = DB::table('procurement_daily_consumptions as consumption')
            ->join('tbl_company as company', 'company.company_code', '=', 'consumption.company_id')
            ->join('tbl_company as plant', 'plant.company_code', '=', 'consumption.plant_id')
            ->leftJoin(app(MaterialStockService::class)->readTable().' as live_stock', function ($join) {
                $join->on('live_stock.matnr', '=', 'consumption.material')
                    ->on('live_stock.plant_code', '=', 'plant.company_code');
            })
            ->leftJoinSub(DB::table('procurement_plan_histories')->select('consumption_id')->selectRaw('MAX(version) as plan_version')->groupBy('consumption_id'), 'history', 'history.consumption_id', '=', 'consumption.id')
            ->whereIn('consumption.company_id', $companies->pluck('company_code'))
            ->where('consumption.status', 1)
            ->when($request->filled('company_id'), fn ($q) => $q->where('consumption.company_id', DB::table('tbl_company')->where('id', $request->integer('company_id'))->value('company_code')))
            ->when($request->filled('plant_id'), fn ($q) => $q->where('consumption.plant_id', DB::table('tbl_company')->where('id', $request->integer('plant_id'))->value('company_code')))
            ->when($request->filled('material'), fn ($q) => $q->where('consumption.material', (string) $request->input('material')))
            ->when($request->filled('material_group'), fn ($q) => $q->whereExists(fn ($s) => $s->selectRaw('1')->from(app(MaterialStockService::class)->readTable().' as material')
                ->whereColumn('material.matnr', 'consumption.material')->where('material.status', 1)->where('material.matkl', (string) $request->input('material_group'))))
            ->select('consumption.*', 'company.company_name', 'plant.company_name as plant_name', 'plant.company_code as plant_code',
                'live_stock.current_stock as database_current_stock', 'live_stock.meins as database_stock_unit')
            ->selectRaw('COALESCE(history.plan_version, 1) as plan_version')
            ->orderByDesc('consumption.consumption_date')->orderByDesc('consumption.id')->get();
        return response()
            ->view('procurement::consumptions', ['companies' => $companies, 'plants' => $plants, 'entries' => $entries, 'materials' => $this->materialsMap(), 'materialGroups' => $this->materialGroupsMap(), 'token' => (string) Str::uuid()])
            // Do not restore a stale browser/proxy snapshot after a write.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function exportConsumptions(Request $request)
    {
        $entries = $this->consumptions($request)->getData()['entries'];
        return response()->streamDownload(function () use ($entries) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Material', 'Company', 'Plant', 'Date', 'Daily Consumption MT', 'Opening Stock MT', 'Days', 'To Buy MT', 'Buying/Imports MT', 'Total Buying MT', 'Total Availability MT', 'Total Required MT', 'Projected Closing MT', 'Shortage MT', 'Suggested Buy MT', 'Cover Days', 'Version', 'Remarks']);
            foreach ($entries as $row) {
                $safe = fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r\n]/', $value) ? "'".$value : $value;
                fputcsv($output, array_map($safe, [$row->material, $row->company_name, $row->plant_code.' - '.$row->plant_name, Carbon::parse($row->consumption_date)->format('d.m.Y'),
                    (float) $row->daily_consumption_mt, $row->current_stock_mt === null ? null : (float) $row->current_stock_mt, $row->day_count,
                    (float) $row->planned_buying_mt, (float) $row->imports_mt, (float) $row->total_buying_mt, $row->availability_mt === null ? null : (float) $row->availability_mt,
                    (float) $row->quantity_mt, $row->projected_closing_mt === null ? null : (float) $row->projected_closing_mt,
                    $row->extra_required_mt === null ? null : (float) $row->extra_required_mt, $row->extra_required_mt === null ? null : (float) $row->extra_required_mt,
                    $row->stock_cover_days === null ? null : (float) $row->stock_cover_days, $row->plan_version, $row->remarks]));
            }
            fclose($output);
        }, 'supply-planning-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function showConsumption(int $consumption)
    {
        Gate::authorize('procurement.daily_consumption.view');
        $record = $this->scopedConsumption($consumption);
        if ($record->status) {
            app(ConsumptionRequirementService::class)->refresh([$record->material]);
            $record = $this->scopedConsumption($consumption);
        }
        $history = DB::table('procurement_plan_histories')->where('consumption_id', $consumption)->orderByDesc('version')->get();
        $version = (int) ($history->first()?->version ?? 1);
        $vendors = $this->purchaseVendors(null, true);
        $purchaseToken = (string) Str::uuid();
        $receipts = collect();
        if (Gate::allows('procurement.purchases.view') && $this->companies('procurement.purchases.all_companies')->contains('company_code', (string) $record->company_id)) {
            $manualReceipts = DB::table('procurement_purchases')
                ->where('consumption_id', $record->id)
                ->where('status', 1)
                ->orderBy('purchase_date')->orderBy('id')
                ->get()->each(fn ($row) => $row->source_type = ($row->vendor_source ?? 'sap') === 'local' ? 'local' : 'manual');
            $periodStart = Carbon::parse($record->consumption_date)->startOfDay();
            $periodEnd = ($record->period_type ?? 'monthly') === 'datewise' && $record->effective_to
                ? Carbon::parse($record->effective_to)->endOfDay() : $periodStart->copy()->endOfMonth();
            $manualReferences = $manualReceipts->pluck('reference')->filter()->all();
            $apiReceipts = DB::table('tbl_vendors')->where('status', 1)
                ->where('material_code', $record->material)->where('plant_code', $record->plant_code)
                ->whereBetween('delivery_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->when($manualReferences !== [], fn ($query) => $query->whereNotIn('purchase_order', $manualReferences))
                ->whereNotNull('mt_quantity')->where('mt_quantity', '>', 0)->get()->map(fn ($row) => (object) [
                    'id' => 'api-'.$row->id, 'source_type' => 'api', 'vendor_name' => $row->vendor_name,
                    'reference' => $row->purchase_order.($row->purchase_order_item ? '/'.$row->purchase_order_item : ''),
                    'quantity_mt' => $row->mt_quantity, 'expected_delivery_date' => $row->delivery_date,
                    'delivery_status' => 'api', 'release_state' => $row->release_state,
                ]);
            $receipts = $manualReceipts->concat($apiReceipts)->sortBy('expected_delivery_date')->values();
        }
        $purchaseMapping = DB::table('procurement_purchases')
            ->where('status', 1)->whereNotNull('consumption_id')
            ->select('consumption_id', 'vendor_name')
            ->selectRaw('SUM(quantity_mt) as ordered_quantity_mt')
            ->selectRaw("GROUP_CONCAT(DISTINCT reference ORDER BY reference SEPARATOR ', ') as purchase_orders")
            ->groupBy('consumption_id', 'vendor_name');
        $mouPlans = DB::table('procurement_mou_plans as mou')
            ->leftJoinSub($purchaseMapping, 'purchase_map', function ($join) {
                $join->on('purchase_map.consumption_id', '=', 'mou.consumption_id')
                    ->on('purchase_map.vendor_name', '=', 'mou.vendor_name');
            })
            ->where('mou.consumption_id', $consumption)
            ->select('mou.*', 'purchase_map.ordered_quantity_mt', 'purchase_map.purchase_orders')
            ->orderBy('mou.vendor_name')->get();
        $deliveryStatuses = self::DELIVERY_STATUSES;
        return view('procurement::consumption-plan', compact('record', 'history', 'version', 'vendors', 'receipts', 'mouPlans', 'purchaseToken', 'deliveryStatuses'));
    }

    public function updateConsumptionPlan(Request $request, int $consumption)
    {
        Gate::authorize('procurement.daily_consumption.edit');
        $record = $this->scopedConsumption($consumption);
        abort_unless($record->status, 422, 'Only an active plan can be updated.');
        $data = $request->validate([
            'daily_consumption_mt' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'day_count' => ['required', 'integer', 'min:1', 'max:31'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($consumption, $data) {
            $row = DB::table('procurement_daily_consumptions')->where('id', $consumption)->lockForUpdate()->first();
            abort_unless($row && $row->status, 422, 'This plan is no longer active.');
            $version = (int) (DB::table('procurement_plan_histories')->where('consumption_id', $consumption)->max('version') ?? 1);
            abort_unless($version === (int) $data['version'], 409, 'The plan changed. Reload the page before saving.');
            if ($version === 1 && ! DB::table('procurement_plan_histories')->where('consumption_id', $consumption)->exists()) {
                $this->recordPlanSnapshot($consumption, 1, (array) $row);
            }
            $values = [
                // Persist the edited rate itself. Previously only quantity_mt
                // was changed, so the next full refresh recalculated the plan
                // from the old daily_consumption_mt value.
                'daily_consumption_mt' => round((float) $data['daily_consumption_mt'], 6),
                'day_count' => (int) $data['day_count'],
                'quantity_mt' => round((float) $data['daily_consumption_mt'] * (int) $data['day_count'], 3),
                'remarks' => $data['remarks'] ?? null,
            ];
            $values += app(ConsumptionRequirementService::class)->calculate(array_replace((array) $row, $values));
            $values['updated_at'] = now();
            DB::table('procurement_daily_consumptions')->where('id', $consumption)->update($values);
            $this->recordPlanSnapshot($consumption, $version + 1, array_replace((array) $row, $values));
        });
        $this->refreshMaterialCalculations([$record->material]);
        return redirect()->route('admin.procurement.consumptions.show', $consumption)->with('success', 'Plan inputs saved and requirements recalculated.');
    }

    public function storeConsumptionMou(Request $request, int $consumption)
    {
        Gate::authorize('procurement.daily_consumption.edit');
        $record = $this->scopedConsumption($consumption);
        abort_unless($record->status, 422, 'Only an active plan can be updated.');
        $data = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255', Rule::exists('tbl_vendors', 'vendor_name')->where(fn ($q) => $q->where('status', 1))],
            'quantity_mt' => ['required', 'numeric', 'min:0.001', 'max:99999999999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $vendorCode = DB::table('tbl_vendors')->where('vendor_name', $data['vendor_name'])
            ->where('status', 1)->orderByDesc('synced_at')->orderByDesc('id')->value('vendor_code');
        DB::transaction(function () use ($consumption, $data, $vendorCode) {
            $row = DB::table('procurement_daily_consumptions')->where('id', $consumption)->lockForUpdate()->first();
            abort_unless($row && $row->status, 422, 'This plan is no longer active.');
            $key = ['consumption_id' => $consumption, 'vendor_name' => $data['vendor_name']];
            $existing = DB::table('procurement_mou_plans')->where($key)->exists();
            $values = ['quantity_mt' => $data['quantity_mt'], 'remarks' => $data['remarks'] ?? null, 'vendor_code' => $vendorCode, 'updated_at' => now()];
            if (! $existing) $values += ['created_at' => now(), 'created_by' => auth()->id()];
            DB::table('procurement_mou_plans')->updateOrInsert($key, $values);
        });
        return redirect()->route('admin.procurement.consumptions.show', $consumption)->with('success', 'MOU lifting plan saved.');
    }

    private function scopedConsumption(int $consumption): object
    {
        $record = DB::table('procurement_daily_consumptions')->where('id', $consumption)->where('status', 1)->first();
        abort_unless($record, 404);

        $company = DB::table('tbl_company')->where('company_code', (string) $record->company_id)
            ->when(is_numeric($record->company_id), fn ($query) => $query->orWhere('id', (int) $record->company_id))
            ->first();
        $plant = DB::table('tbl_company')->where('company_code', (string) $record->plant_id)
            ->when(is_numeric($record->plant_id), fn ($query) => $query->orWhere('id', (int) $record->plant_id))
            ->first();
        abort_unless($company && $plant, 404);

        $record->company_code = $company->company_code;
        $record->company_name = $company->company_name;
        $record->plant_code = $plant->company_code;
        $record->plant_name = $plant->company_name;
        abort_unless($this->companies('procurement.daily_consumption.all_companies')->contains('company_code', (string) $company->company_code), 403);
        return $record;
    }

    private function recordPlanSnapshot(int $consumption, int $version, array $data): void
    {
        DB::table('procurement_plan_histories')->insert([
            'consumption_id' => $consumption, 'version' => $version,
            'snapshot' => json_encode(\Illuminate\Support\Arr::only($data, ['consumption_date', 'day_count', 'quantity_mt', 'current_stock_mt', 'daily_consumption_mt', 'extra_required_mt', 'planned_buying_mt', 'imports_mt', 'total_buying_mt', 'availability_mt', 'projected_closing_mt', 'stock_cover_days', 'remarks']), JSON_THROW_ON_ERROR),
            'created_by' => auth()->id(), 'created_at' => now(),
        ]);
    }

    public function createConsumption()
    {
        Gate::authorize('procurement.daily_consumption.create');
        $companies = $this->companies('procurement.daily_consumption.all_companies');
        $plants = $this->plants($companies->pluck('id'))->get()
            ->reject(function ($plant): bool {
                $code = trim((string) ($plant->company_code ?? ''));
                $name = strtolower(trim((string) ($plant->name ?? '')));

                return $code === '0' || in_array($name, ['head office', 'other plant'], true);
            })
            ->values();
        $materials = DB::table(app(MaterialStockService::class)->readTable())->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)->whereIn('plant_code', $plants->pluck('company_code'))
            ->whereNotNull('matkl')->where('matkl', '<>', '')
            ->select('matnr', DB::raw('MAX(maktx) AS maktx'), 'matkl', 'plant_code')
            ->groupBy('matnr', 'matkl', 'plant_code')->orderBy('plant_code')->orderBy('matkl')->orderBy('maktx')->get();

        return view('procurement::consumption-batch-form', [
            'companies' => $companies,
            'plants' => $plants,
            'materials' => $materials,
            'groups' => $materials->pluck('matkl')->unique()->values(),
            'token' => (string) Str::uuid(),
            'stockMap' => $this->stockMap($plants),
            'monthlyNextDates' => $this->monthlyNextDateMap($plants),
            'record' => null,
        ]);
    }

    public function editConsumption(int $consumption)
    {
        Gate::authorize('procurement.daily_consumption.edit');
        $record = DB::table('procurement_daily_consumptions')->where('id', $consumption)->where('status', 1)->first();
        abort_unless($record, 404);
        $companies = $this->companies('procurement.daily_consumption.all_companies');
        abort_unless($companies->contains('company_code', (string) $record->company_id), 403);
        $plants = $this->plants($companies->pluck('id'))->get();
        $materials = DB::table(app(MaterialStockService::class)->readTable())->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)->whereNotNull('matkl')->where('matkl', '<>', '')
            ->select('matnr', DB::raw('MAX(maktx) AS maktx'), 'matkl', 'plant_code')
            ->groupBy('matnr', 'matkl', 'plant_code')->orderBy('maktx')->get();
        return view('procurement::consumption-form', ['companies' => $companies, 'plants' => $plants, 'materials' => $materials, 'groups' => collect(), 'token' => null, 'record' => $record, 'stockMap' => $this->stockMap($plants), 'monthlyNextDates' => []]);
    }

    public function updateConsumption(Request $request, int $consumption)
    {
        Gate::authorize('procurement.daily_consumption.edit');
        $record = DB::table('procurement_daily_consumptions')->where('id', $consumption)->where('status', 1)->first();
        abort_unless($record, 404);
        $data = $this->validatedConsumption($request);
        unset($data['_period_rows']);
        unset($data['planning_months']);
        $data['status'] = $request->boolean('status');
        $this->authorizeCompanyPlantCodes((string) $data['company_id'], (string) $data['plant_id'], 'procurement.daily_consumption.all_companies');
        $this->ensureMonthlyConsumptionIsUnique($data, $consumption);
        $data += app(ConsumptionRequirementService::class)->calculate($data);
        DB::table('procurement_daily_consumptions')->where('id', $consumption)->update($data + ['updated_at' => now()]);
        $this->refreshMaterialCalculations([$record->material, $data['material']]);
        return redirect()->to(route('admin.procurement.consumptions').'?refreshed='.now()->getTimestamp())
            ->with('success', 'Daily consumption updated successfully. All dependent calculations were refreshed.');
    }

    public function destroyConsumption(int $consumption)
    {
        Gate::authorize('procurement.daily_consumption.delete');
        $record = DB::table('procurement_daily_consumptions')->where('id', $consumption)->where('status', 1)->first();
        abort_unless($record, 404);
        $companyCode = DB::table('tbl_company')
            ->where('company_code', (string) $record->company_id)
            ->when(is_numeric($record->company_id), fn ($query) => $query->orWhere('id', (int) $record->company_id))
            ->value('company_code');
        abort_unless($companyCode && $this->companies('procurement.daily_consumption.all_companies')->contains('company_code', (string) $companyCode), 403);
        DB::table('procurement_daily_consumptions')->where('id', $consumption)->update([
            'status' => 0,
            'updated_at' => now(),
        ]);
        $this->refreshMaterialCalculations([$record->material]);
        return back()->with('success', 'Daily consumption deactivated and all dependent calculations refreshed.');
    }

    public function storeConsumption(Request $request)
    {
        Gate::authorize('procurement.daily_consumption.create');
        if ($request->boolean('batch_mode')) {
            $rows = $this->validatedBatchConsumptionRows($request);
            DB::transaction(function () use ($rows): void {
                $batch = (string) Str::uuid();
                foreach ($rows as $index => $row) {
                    $this->ensureMonthlyConsumptionIsUnique($row);
                    $row['submission_token'] = $index === 0 ? $row['submission_token'] : (string) Str::uuid();
                    $row += ['planning_batch' => $batch, 'planning_sequence' => $index + 1, 'planning_months' => 1];
                    DB::table('procurement_daily_consumptions')->where([
                        'plant_id' => $row['plant_id'], 'consumption_date' => $row['consumption_date'],
                        'material' => $row['material'], 'status' => 1,
                    ])->update(['status' => 0, 'superseded_at' => now(), 'updated_at' => now()]);
                    DB::table('procurement_daily_consumptions')->insert($row + [
                        'status' => 1, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
            $this->refreshMaterialCalculations($rows->pluck('material')->all());
            return redirect()->route('admin.procurement.consumptions')->with('success', 'Consumption entries for all selected plants were saved successfully.');
        }

        $data = $this->validatedConsumption($request, true);
        $this->authorizeCompanyPlantCodes((string) $data['company_id'], (string) $data['plant_id'], 'procurement.daily_consumption.all_companies');
        DB::transaction(function () use ($data) {
            $rows = $data['_period_rows'] ?? [$data];
            $batch = (string) Str::uuid();
            foreach ($rows as $index => $row) {
                unset($row['_period_rows']);
                $this->ensureMonthlyConsumptionIsUnique($row);
                if ($index > 0 && isset($row['submission_token'])) $row['submission_token'] = (string) Str::uuid();
                $row += ['planning_batch' => $batch, 'planning_sequence' => $index + 1, 'planning_months' => 1];
                DB::table('procurement_daily_consumptions')->where([
                    'plant_id' => $row['plant_id'], 'consumption_date' => $row['consumption_date'], 'material' => $row['material'], 'status' => 1,
                ])->update(['status' => 0, 'superseded_at' => now(), 'updated_at' => now()]);
                DB::table('procurement_daily_consumptions')->insert($row + [
                    'status' => 1, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $this->refreshMaterialCalculations([$data['material']]);

        return redirect()->route('admin.procurement.consumptions')->with('success', 'Consumption entries saved and all dependent calculations refreshed successfully.');
    }

    private function validatedBatchConsumptionRows(Request $request)
    {
        $normaliseDate = function ($value) {
            if (! is_string($value) || trim($value) === '') return $value;
            foreach (['d-m-Y', 'Y-m-d'] as $format) {
                try { return Carbon::createFromFormat($format, trim($value))->toDateString(); } catch (\Throwable) {}
            }
            return $value;
        };
        $periods = collect($request->input('periods', []))->map(function ($period) use ($normaliseDate) {
            if (! is_array($period)) return $period;
            $period['from'] = $normaliseDate($period['from'] ?? null);
            $period['until'] = $normaliseDate($period['until'] ?? null);
            return $period;
        })->values()->all();
        $request->merge(['monthly_date' => $normaliseDate($request->input('monthly_date')), 'periods' => $periods]);
        $type = (string) $request->input('period_type');
        $data = $request->validate([
            'submission_token' => ['required', 'uuid', 'unique:procurement_daily_consumptions,submission_token'],
            'company_id' => ['required', 'string', 'exists:tbl_company,company_code'],
            'material' => ['required', 'string'],
            'period_type' => ['required', Rule::in(['monthly', 'datewise'])],
            'monthly_date' => [$type === 'monthly' ? 'required' : 'nullable', 'date', ...($type === 'monthly' ? ['after_or_equal:today'] : [])],
            'periods' => [$type === 'datewise' ? 'required' : 'nullable', 'array', 'max:24'],
            'periods.*.from' => [$type === 'datewise' ? 'required' : 'nullable', 'date', ...($type === 'datewise' ? ['after_or_equal:today'] : [])],
            'periods.*.until' => [$type === 'datewise' ? 'required' : 'nullable', 'date'],
            'plant_entries' => ['required', 'array'],
            'plant_entries.*.selected' => ['nullable', 'boolean'],
            'plant_entries.*.monthly' => ['nullable', 'numeric', 'min:0.001'],
            'plant_entries.*.periods' => ['nullable', 'array'],
            'plant_entries.*.periods.*' => ['nullable', 'numeric', 'min:0.001'],
            'plant_entries.*.remarks' => ['nullable', 'string', 'max:500'],
            'common_remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $selected = collect($data['plant_entries'])->filter(fn ($entry) => (bool) ($entry['selected'] ?? false));
        if ($selected->isEmpty()) throw ValidationException::withMessages(['plant_entries' => 'Select at least one applicable plant.']);

        $periodCollection = collect($data['periods'] ?? []);
        if ($type === 'datewise') {
            if ($periodCollection->isEmpty()) throw ValidationException::withMessages(['periods' => 'Add at least one date-wise period.']);
            $periodCollection->each(function ($period, $index) use ($periodCollection): void {
                $from = Carbon::parse($period['from'])->startOfDay();
                $until = Carbon::parse($period['until'])->startOfDay();
                if ($until->lt($from)) throw ValidationException::withMessages(["periods.{$index}.until" => 'Effective until must be on or after Effective from.']);
                if ($index && ! $from->equalTo(Carbon::parse($periodCollection[$index - 1]['until'])->addDay()->startOfDay())) {
                    throw ValidationException::withMessages(["periods.{$index}.from" => 'Each period must start the day after the previous period ends.']);
                }
            });
        }

        $company = DB::table('tbl_company')->where('company_code', $data['company_id'])->first();
        $materialMaster = DB::table(app(MaterialStockService::class)->readTable())
            ->where('matnr', $data['material'])
            ->where('status', 1)
            ->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)
            ->first();
        if (! $materialMaster) throw ValidationException::withMessages(['material' => 'The selected material is not available.']);
        $rows = collect();
        foreach ($selected as $plantCode => $entry) {
            $plant = DB::table('tbl_company')->where('company_code', $plantCode)->where('parent_company_id', $company->company_code)->where('Status', 1)->first();
            if (! $plant) throw ValidationException::withMessages(['plant_entries' => "Plant {$plantCode} is not valid for the selected company."]);
            $material = DB::table(app(MaterialStockService::class)->readTable())->where('matnr', $data['material'])->where('plant_code', $plantCode)->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)->first();
            $remarks = collect([$data['common_remarks'] ?? null, $entry['remarks'] ?? null])->filter()->implode(' | ') ?: null;
            $base = ['company_id' => (string) $company->company_code, 'plant_id' => (string) $plant->company_code, 'material' => $data['material'], 'material_group' => $material->matkl ?? $materialMaster->matkl, 'period_type' => $type, 'remarks' => $remarks, 'submission_token' => $data['submission_token']];
            if ($type === 'monthly') {
                if (empty($entry['monthly'])) throw ValidationException::withMessages(["plant_entries.{$plantCode}.monthly" => "Enter monthly consumption for {$plantCode}."]);
                $rows->push($this->normalizeConsumptionPeriod($base, Carbon::parse($data['monthly_date'])->startOfDay(), null, (float) $entry['monthly']));
                continue;
            }
            foreach ($periodCollection as $index => $period) {
                $quantity = $entry['periods'][$index] ?? null;
                if (empty($quantity)) throw ValidationException::withMessages(["plant_entries.{$plantCode}.periods.{$index}" => "Enter Period ".($index + 1)." consumption for {$plantCode}."]);
                $rows->push($this->normalizeConsumptionPeriod($base, Carbon::parse($period['from'])->startOfDay(), Carbon::parse($period['until'])->startOfDay(), (float) $quantity));
            }
        }
        return $rows;
    }

    private function ensureMonthlyConsumptionIsUnique(array $data, ?int $ignoreId = null): void
    {
        if (($data['period_type'] ?? null) !== 'monthly' || empty($data['consumption_date'])) return;

        $date = Carbon::parse($data['consumption_date']);
        $duplicate = DB::table('procurement_daily_consumptions')
            ->where('company_id', $data['company_id'])
            ->where('plant_id', $data['plant_id'])
            ->where('material', $data['material'])
            ->where('period_type', 'monthly')
            ->where('status', 1)
            ->whereYear('consumption_date', $date->year)
            ->whereMonth('consumption_date', $date->month)
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->lockForUpdate()
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'consumption_date' => 'Monthly consumption already exists for this company, plant, material and month. Select the first day of the next month.',
            ]);
        }
    }

    private function monthlyNextDateMap($plants): array
    {
        if (! Schema::hasTable('procurement_daily_consumptions')
            || ! Schema::hasColumn('procurement_daily_consumptions', 'period_type')) {
            return [];
        }

        $plantCodes = $plants->pluck('company_code')->map(fn ($code) => (string) $code)->values();

        return DB::table('procurement_daily_consumptions')
            ->whereIn('plant_id', $plantCodes)
            ->where('period_type', 'monthly')
            ->where('status', 1)
            ->select('plant_id', 'material', DB::raw('MAX(consumption_date) as latest_date'))
            ->groupBy('plant_id', 'material')
            ->get()
            ->mapWithKeys(function ($row): array {
                $key = $row->plant_id.'|'.$row->material;
                $nextDate = Carbon::parse($row->latest_date)->addMonthNoOverflow()->startOfMonth()->toDateString();
                return [$key => $nextDate];
            })
            ->all();
    }

    private function refreshMaterialCalculations(array $materials): void
    {
        $materials = collect($materials)->filter()->map(fn ($material) => (string) $material)->unique()->values()->all();
        if ($materials === []) return;

        app(ConsumptionRequirementService::class)->refresh($materials);
        if (Schema::hasTable('procurement_balance_results')) {
            DB::table('procurement_balance_results')->whereIn('material', $materials)->delete();
        }
    }

    private function validatedConsumption(Request $request, bool $includeToken = false): array
    {
        $normaliseDate = function ($value) {
            if (! is_string($value) || trim($value) === '') return $value;
            foreach (['d-m-Y', 'Y-m-d'] as $format) {
                try { return Carbon::createFromFormat($format, trim($value))->toDateString(); } catch (\Throwable) {}
            }
            return $value;
        };
        $datewiseEntries = collect($request->input('datewise_entries', []))->map(function ($entry) use ($normaliseDate) {
            if (! is_array($entry)) return $entry;
            $entry['from'] = $normaliseDate($entry['from'] ?? null);
            $entry['until'] = $normaliseDate($entry['until'] ?? null);
            return $entry;
        })->all();
        $request->merge([
            'consumption_date' => $normaliseDate($request->input('consumption_date')),
            'datewise_entries' => $datewiseEntries,
        ]);
        $periodType = (string) $request->input('period_type');
        if ($periodType === 'monthly') {
            $request->merge([
                'consumption_date' => $request->input('consumption_date', now()->toDateString()),
                'effective_to' => null,
            ]);
        }
        $rules = [
            'company_id' => ['required', 'string', 'exists:tbl_company,company_code'],
            'plant_id' => ['required', 'string', 'exists:tbl_company,company_code'],
            'period_type' => ['required', Rule::in(['monthly', 'datewise'])],
            'consumption_date' => [
                $periodType === 'monthly' ? 'required' : 'nullable',
                'date',
                ...($periodType === 'monthly' && $includeToken ? ['after_or_equal:today'] : []),
            ],
            'effective_to' => ['nullable', 'date'],
            'material' => ['required', Rule::exists(app(MaterialStockService::class)->readTable(), 'matnr')->where(fn ($query) => $query
                ->where('status', 1)
                ->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)
                ->where('plant_code', $request->input('plant_id'))) ],
            'daily_consumption_mt' => [$periodType === 'monthly' ? 'required' : 'nullable', 'numeric', 'min:0.001'],
            'datewise_entries' => [$periodType === 'datewise' ? 'required' : 'nullable', 'array', 'min:1', 'max:24'],
            'datewise_entries.*.from' => [$periodType === 'datewise' ? 'required' : 'nullable', 'date', ...($includeToken ? ['after_or_equal:today'] : [])],
            'datewise_entries.*.until' => [$periodType === 'datewise' ? 'required' : 'nullable', 'date'],
            'datewise_entries.*.daily_consumption_mt' => [$periodType === 'datewise' ? 'required' : 'nullable', 'numeric', 'min:0.001'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
        if ($includeToken) $rules['submission_token'] = ['required', 'uuid', 'unique:procurement_daily_consumptions,submission_token'];
        $data = $request->validate($rules);
        if ($periodType === 'datewise') {
            $periods = collect($data['datewise_entries']);
            $periods->each(function (array $period, int $index) use ($periods) {
                $from = Carbon::parse($period['from'])->startOfDay();
                $until = Carbon::parse($period['until'])->startOfDay();
                if ($until->lt($from)) {
                    throw ValidationException::withMessages([
                        "datewise_entries.{$index}.until" => 'Effective until must be on or after Effective from.',
                    ]);
                }
                if ($index === 0) return;
                $previousUntil = Carbon::parse($periods[$index - 1]['until'])->startOfDay();
                if (! $from->equalTo($previousUntil->copy()->addDay())) {
                    throw ValidationException::withMessages([
                        "datewise_entries.{$index}.from" => 'Each new Effective from date must be the day after the previous Effective until date.',
                    ]);
                }
            });
        }
        $data['company_id'] = (string) $data['company_id'];
        $data['material_group'] = DB::table(app(MaterialStockService::class)->readTable())
            ->where('matnr', $data['material'])->where('plant_code', $data['plant_id'])->where('status', 1)->value('matkl');
        $data['plant_id'] = (string) $data['plant_id'];
        $entries = $data['datewise_entries'] ?? [];
        unset($data['datewise_entries']);

        if ($periodType === 'datewise') {
            $rows = collect($entries)->map(function (array $entry) use ($data) {
                $from = Carbon::parse($entry['from'])->startOfDay();
                $until = Carbon::parse($entry['until'])->startOfDay();
                abort_if($until->lt($from), 422, 'Effective until must be on or after effective from.');
                return $this->normalizeConsumptionPeriod($data, $from, $until, (float) $entry['daily_consumption_mt']);
            })->values()->all();
            $result = $rows[0];
            $result['_period_rows'] = $rows;
            return $result;
        }

        return $this->normalizeConsumptionPeriod($data, Carbon::parse($data['consumption_date'])->startOfDay(), null, (float) $data['daily_consumption_mt']);
    }

    private function normalizeConsumptionPeriod(array $data, Carbon $from, ?Carbon $until, float $dailyQuantity): array
    {
        $data['consumption_date'] = $from->toDateString();
        $data['effective_to'] = $until?->toDateString();
        $periodEnd = $until ?: $from->copy()->endOfMonth()->startOfDay();
        $data['day_count'] = (int) round($from->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay())) + 1;
        // The form quantity is a one-day rate. Keep quantity_mt as the full
        // period requirement for shortages and reporting, and persist the
        // entered rate separately for stock-cover calculations.
        $data['daily_consumption_mt'] = round($dailyQuantity, 6);
        $data['quantity_mt'] = round($dailyQuantity * $data['day_count'], 3);
        return $data;
    }

    private function datewiseMonthlyMetrics($entries, Carbon $month, ?object $monthlyFallback = null): array
    {
        $entries = collect($entries)->sortBy([
            ['consumption_date', 'asc'],
            ['id', 'asc'],
        ])->values();
        $monthStart = $month->copy()->startOfMonth()->startOfDay();
        $monthEnd = $month->copy()->endOfMonth()->startOfDay();
        $firstDatewiseStart = $entries->isNotEmpty()
            ? Carbon::parse($entries->first()->consumption_date)->startOfDay()
            : null;
        $fallbackStart = $monthlyFallback
            ? Carbon::parse($monthlyFallback->consumption_date)->startOfDay()
            : null;
        $metrics = ['working_days' => 0, 'expected_consumption' => 0.0];

        // Preserve the full requirement entered for every date-wise period.
        // Covered calendar dates are counted only once below, but two entries
        // sharing a boundary date still contribute both entered quantities.
        foreach ($entries as $row) {
            $from = Carbon::parse($row->consumption_date)->startOfDay();
            $until = Carbon::parse($row->effective_to)->startOfDay();
            $overlapStart = $from->greaterThan($monthStart) ? $from : $monthStart;
            $overlapEnd = $until->lessThan($monthEnd) ? $until : $monthEnd;
            if ($overlapStart->gt($overlapEnd)) continue;
            $overlapDays = (int) round($overlapStart->copy()->startOfDay()->diffInDays($overlapEnd->copy()->startOfDay())) + 1;
            $metrics['expected_consumption'] += (float) $row->daily_consumption_mt * $overlapDays;
        }

        // Calculate one calendar date at a time so overlapping date-wise rows
        // are not double counted. The latest applicable row supplies the rate.
        for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
            $entry = $entries->last(function ($row) use ($date) {
                $from = Carbon::parse($row->consumption_date)->startOfDay();
                $until = Carbon::parse($row->effective_to)->startOfDay();
                return $date->betweenIncluded($from, $until);
            });

            $useMonthlyBeforeDatewise = $monthlyFallback
                && $date->gte($fallbackStart)
                && $date->lt($firstDatewiseStart);
            $dailyRate = $entry ? 0.0
                : ($useMonthlyBeforeDatewise ? (float) $monthlyFallback->daily_consumption_mt : null);
            if ($dailyRate === null) continue;

            $metrics['working_days']++;
            if ($useMonthlyBeforeDatewise) $metrics['expected_consumption'] += $dailyRate;
        }

        return [
            'working_days' => max(1, min(31, (int) $metrics['working_days'])),
            'expected_consumption' => round((float) $metrics['expected_consumption'], 6),
        ];
    }

    private function companies(string $allCompaniesPermission)
    {
        $query = DB::table('tbl_company')->where('record_type', 1)->where('Status', 1)->orderBy('company_name');
        $mapped = collect();
        if (Schema::hasTable('model_has_roles') && Schema::hasTable('role_company')) {
            $roleIds = DB::table('model_has_roles')->where('model_type', get_class(auth()->user()))->where('model_id', auth()->id())->pluck('role_id');
            $mapped = DB::table('role_company')->whereIn('role_id', $roleIds)->pluck('company_id');
        }

        // Explicit role-to-company mappings are always authoritative, even
        // when the role also has an all-companies capability.
        if ($mapped->isNotEmpty()) {
            $query->whereIn('id', $mapped);
        } elseif (! auth()->user()->can($allCompaniesPermission)) {
            $profileCompanyCode = auth()->user()->profile?->company_id;
            $profileCompanyId = $profileCompanyCode
                ? DB::table('tbl_company')->where('company_code', $profileCompanyCode)->where('record_type', 1)->value('id')
                : null;
            $profileCompanyId ? $query->where('id', $profileCompanyId) : $query->whereRaw('1 = 0');
        }
        return $query->get(['id', 'company_code', 'company_name']);
    }

    private function plants($companyIds): Builder
    {
        return DB::table('tbl_company as plant')->join('tbl_company as company', 'company.company_code', '=', 'plant.parent_company_id')
            ->where('plant.record_type', '<>', 1)->where('plant.Status', 1)->whereIn('company.id', $companyIds)
            ->select('plant.id', 'company.id as company_id', 'company.company_code as parent_company_code', 'plant.company_code', 'plant.company_name as name');
    }

    private function purchaseQuery($companyIds): Builder
    {
        return DB::table('procurement_purchases as purchase')->join('tbl_company as company', 'company.id', '=', 'purchase.company_id')
            ->join('tbl_company as plant', 'plant.id', '=', 'purchase.plant_id')->whereIn('purchase.company_id', $companyIds)
            ->leftJoinSub(app(MaterialStockService::class)->stockByPlantQuery(), 'stock', function ($join) {
                $join->on('stock.matnr', '=', 'purchase.material')->on('stock.plant_code', '=', 'plant.company_code');
            })
            ->select('purchase.*', 'company.company_name', 'plant.company_name as plant_name')
            ->selectRaw('COALESCE(stock.current_stock, 0) AS current_stock')->addSelect('stock.meins as stock_unit');
    }

    private function purchaseVendors(?string $material = null, bool $distinctVendors = false)
    {
        return DB::table('tbl_vendors')->where('status', 1)
            ->whereNotNull('vendor_name')->where('vendor_name', '<>', '')
            ->when($material !== null, fn ($q) => $q->where('material_code', $material))
            ->select('id', 'material_code', 'vendor_code', 'vendor_name', 'purchase_order', 'synced_at')
            ->orderBy('vendor_name')->orderByDesc('synced_at')->orderByDesc('id')->get()
            ->groupBy(fn ($row) => json_encode($distinctVendors
                ? [$row->vendor_code, $row->vendor_name]
                : [$row->material_code, $row->vendor_code, $row->vendor_name]))
            ->map(fn ($rows) => (object) [
                'material_code' => $rows->first()->material_code,
                'vendor_code' => $rows->first()->vendor_code,
                'vendor_name' => $rows->first()->vendor_name,
                'purchase_orders' => $rows->pluck('purchase_order')->filter()->first() ?? '',
            ])->values();
    }

    private function stockMap($plants): array
    {
        $stocks = app(MaterialStockService::class)->stockByPlantQuery()
            ->whereIn('plant_code', $plants->pluck('company_code'))->get();
        return $stocks->mapWithKeys(fn ($stock) => [
            $stock->matnr.'|'.$stock->plant_code => app(ConsumptionRequirementService::class)->toMetricTonnes((float) $stock->current_stock, $stock->meins),
        ])->all();
    }

    private function materialsMap(): array
    {
        return DB::table(app(MaterialStockService::class)->readTable())->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)->orderBy('maktx')->pluck('maktx', 'matnr')->all();
    }

    private function materialGroupsMap(): array
    {
        return DB::table(app(MaterialStockService::class)->readTable())->where('status', 1)->whereIn('mtart', self::PLANNING_MATERIAL_TYPES)->whereNotNull('matkl')->where('matkl', '<>', '')
            ->orderBy('matnr')->pluck('matkl', 'matnr')->all();
    }

    private function authorizeCompanyPlant(int $companyId, int $plantId, string $allCompaniesPermission): void
    {
        abort_unless($this->companies($allCompaniesPermission)->contains('id', $companyId), 403);
        $companyCode = DB::table('tbl_company')->where('id', $companyId)->value('company_code');
        abort_unless(DB::table('tbl_company')->where('id', $plantId)->where('parent_company_id', $companyCode)->where('Status', 1)->exists(), 422, 'Select an active plant belonging to the selected company.');
    }

    private function authorizeCompanyPlantCodes(string $companyCode, string $plantCode, string $allCompaniesPermission): void
    {
        abort_unless($this->companies($allCompaniesPermission)->contains('company_code', $companyCode), 403);
        abort_unless(DB::table('tbl_company')->where('company_code', $plantCode)
            ->where('parent_company_id', $companyCode)->where('Status', 1)->exists(), 422,
            'Select an active plant belonging to the selected company.');
    }

}
