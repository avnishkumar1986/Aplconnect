<?php

namespace Modules\Procurement\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use RuntimeException;
use Inertia\Inertia;
use Inertia\Response;

class PlanningDecisionController extends Controller
{
    private const COMPANY_MATERIAL_TYPES = ['1000' => 'ZRAW', '5000' => 'YRAW'];

    public function index(Request $request): Response
    {
        $this->releaseExpiredLocks();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->string('month'))
            ? (string) $request->string('month')
            : now('Asia/Kolkata')->format('Y-m');
        $companyId = $request->integer('company_id');
        $plantId = $request->integer('plant_id');
        $material = trim((string) $request->string('material'));
        $search = trim((string) $request->string('search'));

        $companies = DB::table('tbl_company')->where('status', 1)->where('record_type', 1)
            ->whereIn('company_code', array_keys(self::COMPANY_MATERIAL_TYPES))
            ->orderBy('company_name')->get(['id', 'company_code', 'company_name']);
        $selectedCompany = $companies->firstWhere('id', $companyId) ?? $companies->first();
        $companyId = (int) ($selectedCompany->id ?? 0);
        $plants = DB::table('tbl_company as plant')->join('tbl_company as company', function ($join): void {
            $join->on('company.company_code', '=', 'plant.parent_company_id')->where('company.record_type', 1);
        })->where('plant.status', 1)->where('plant.record_type', 2)
            ->whereIn('company.company_code', array_keys(self::COMPANY_MATERIAL_TYPES))
            ->whereRaw("LOWER(TRIM(plant.company_name)) NOT IN ('head office', 'other plant')")
            ->orderBy('plant.company_name')->get(['plant.id', 'plant.company_code', 'plant.company_name', 'company.id as company_id']);
        $companyPlants = $plants->where('company_id', $companyId);
        $selectedPlant = $companyPlants->firstWhere('id', $plantId);
        $plantId = (int) ($selectedPlant->id ?? 0);
        $materialType = self::COMPANY_MATERIAL_TYPES[$selectedCompany->company_code ?? ''] ?? null;
        $decisions = DB::table('tbl_materials as material')
            ->crossJoin('tbl_company as selected_plant')
            ->leftJoin('procurement_planning_decisions as decision', function ($join) use ($companyId, $month): void {
                $join->on('decision.material', '=', 'material.matnr')
                    ->on('decision.plant_id', '=', 'selected_plant.id')
                    ->where('decision.company_id', $companyId)
                    ->whereDate('decision.planning_month', $month.'-01');
            })
            ->where('material.status', 1)
            ->where('selected_plant.status', 1)
            ->where('selected_plant.record_type', 2)
            ->where('selected_plant.parent_company_id', $selectedCompany->company_code ?? '')
            ->whereRaw("LOWER(TRIM(selected_plant.company_name)) NOT IN ('head office', 'other plant')")
            ->when($plantId > 0, fn ($query) => $query->where('selected_plant.id', $plantId))
            ->when($materialType, fn ($query) => $query->whereRaw('UPPER(TRIM(material.mtart)) = ?', [$materialType]))
            ->when($material !== '', fn ($query) => $query->where('material.matnr', $material))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('material.matnr', 'like', "%{$search}%")
                ->orWhere('material.maktx', 'like', "%{$search}%")))
            ->select(
                'decision.id', 'decision.planning_mode', 'decision.planning_month', 'decision.locked_at', 'selected_plant.id as plant_id',
                'selected_plant.company_code as plant_code', 'selected_plant.company_name as plant_name',
                'material.matnr as material', 'material.maktx as material_name', 'material.mtart',
            )
            ->selectRaw('? as company_id, ? as company_code, ? as company_name', [
                $companyId, $selectedCompany->company_code ?? '', $selectedCompany->company_name ?? '',
            ])
            ->orderBy('selected_plant.company_name')->orderBy('material.maktx')
            ->paginate(50)->withQueryString();

        return Inertia::render('modules/procurement/planning/planning-decisions', [
            'companies' => $companies, 'plants' => $plants, 'decisions' => $decisions,
            'filters' => ['month' => $month, 'company_id' => $companyId ?: null, 'plant_id' => $plantId ?: null, 'material' => $material ?: null, 'search' => $search],
            'canManage' => $request->user()?->can('procurement.planning_decisions.create') ?? false,
            'canLock' => $request->user()?->can('procurement.planning_decisions.edit') ?? false,
            'canUnlock' => $this->isSuperAdmin($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->releaseExpiredLocks();
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.plant_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'selections.*.material' => ['required', 'string', 'max:50', 'exists:tbl_materials,matnr'],
            'planning_month' => ['required', 'date_format:Y-m'],
            'planning_mode' => ['required', Rule::in(['auto', 'manual'])],
        ]);
        $company = DB::table('tbl_company')->where('id', $data['company_id'])->where('record_type', 1)->first(['company_code']);
        abort_unless($company, 422, 'The selected company is invalid.');
        $selections = $this->validateSelections($company->company_code, $data['selections']);

        $now = now();
        $month = CarbonImmutable::parse($data['planning_month'].'-01')->toDateString();
        foreach ($selections as $selection) {
            $locked = DB::table('procurement_planning_decisions')
                ->where('company_id', $data['company_id'])->where('plant_id', $selection['plant_id'])
                ->whereDate('planning_month', $month)->where('material', $selection['material'])
                ->whereNotNull('locked_at')->exists();
            abort_if($locked, 422, 'Locked planning decisions cannot be changed.');
        }
        $rows = collect($selections)->map(fn (array $selection) => [
            'company_id' => $data['company_id'], 'plant_id' => $selection['plant_id'], 'material' => $selection['material'],
            'planning_month' => $month, 'planning_mode' => $data['planning_mode'], 'status' => 1,
            'created_by' => $request->user()?->id, 'created_at' => $now, 'updated_at' => $now,
        ])->all();
        DB::table('procurement_planning_decisions')->upsert(
            $rows,
            ['company_id', 'plant_id', 'material', 'planning_month'],
            ['planning_mode', 'status', 'updated_at'],
        );

        return back()->with('success', count($rows).' planning decision(s) saved.');
    }

    public function destroy(int $decision): RedirectResponse
    {
        $this->releaseExpiredLocks();
        $deleted = DB::table('procurement_planning_decisions')->where('id', $decision)->whereNull('locked_at')->delete();
        abort_unless($deleted === 1, 422, 'Locked planning decisions cannot be removed.');

        return back()->with('success', 'Planning decision removed.');
    }

    public function lock(Request $request, int $decision): RedirectResponse
    {
        $this->releaseExpiredLocks();
        DB::transaction(function () use ($request, $decision): void {
            $updated = DB::table('procurement_planning_decisions')->where('id', $decision)->whereNull('locked_at')->update([
                'locked_at' => now(), 'locked_by' => $request->user()?->id, 'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['lock' => 'This planning decision is already locked.']);
            }
            $this->persistLockedCalculation($decision);
        });

        return back()->with('success', 'Planning decision locked.');
    }

    public function unlock(Request $request, int $decision): RedirectResponse
    {
        abort_unless($this->isSuperAdmin($request), 403);

        DB::table('procurement_planning_decisions')->where('id', $decision)->update([
            'locked_at' => null, 'locked_by' => null,
            'unlocked_at' => now(), 'unlocked_by' => $request->user()?->id, 'updated_at' => now(),
        ]);

        return back()->with('success', 'Planning decision unlocked.');
    }

    public function lockAll(Request $request): RedirectResponse
    {
        $this->releaseExpiredLocks();
        $data = $this->validateBulkLock($request);
        $companyCode = DB::table('tbl_company')->where('id', $data['company_id'])->where('record_type', 1)->value('company_code');
        if (! $companyCode) {
            throw ValidationException::withMessages(['lock' => 'The selected company is invalid.']);
        }
        $selections = $this->validateSelections($companyCode, $data['selections']);
        $now = now();
        DB::table('procurement_planning_decisions')->insertOrIgnore(collect($selections)->map(fn (array $selection) => [
            'company_id' => $data['company_id'],
            'plant_id' => $selection['plant_id'],
            'material' => $selection['material'],
            'planning_month' => $data['planning_month'].'-01',
            'planning_mode' => 'auto',
            'status' => 1,
            'created_by' => $request->user()?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
        $count = 0;
        $alreadyLocked = 0;
        foreach ($selections as $selection) {
            $decision = DB::table('procurement_planning_decisions')
                ->where('company_id', $data['company_id'])->where('plant_id', $selection['plant_id'])
                ->whereDate('planning_month', $data['planning_month'].'-01')->where('material', $selection['material'])
                ->first(['id', 'locked_at']);
            if (! $decision) {
                continue;
            }
            if ($decision->locked_at !== null) {
                $alreadyLocked++;

                continue;
            }

            $locked = DB::transaction(function () use ($decision, $request): bool {
                $updated = DB::table('procurement_planning_decisions')
                    ->where('id', $decision->id)
                    ->whereNull('locked_at')
                    ->update([
                        'locked_at' => now(),
                        'locked_by' => $request->user()?->id,
                        'updated_at' => now(),
                    ]);
                if ($updated !== 1) {
                    return false;
                }

                $this->persistLockedCalculation((int) $decision->id);

                return true;
            });
            if ($locked) {
                $count++;
            } else {
                $alreadyLocked++;
            }
        }

        return back()->with(
            'success',
            $count.' planning decision(s) locked. '.$alreadyLocked.' already locked.',
        );
    }

    public function unlockAll(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperAdmin($request), 403);

        $data = $this->validateBulkLock($request);
        $companyCode = DB::table('tbl_company')->where('id', $data['company_id'])->where('record_type', 1)->value('company_code');
        abort_unless($companyCode, 422, 'The selected company is invalid.');
        $selections = $this->validateSelections($companyCode, $data['selections']);
        $count = 0;
        foreach ($selections as $selection) {
            $count += DB::table('procurement_planning_decisions')
                ->where('company_id', $data['company_id'])->where('plant_id', $selection['plant_id'])
                ->whereDate('planning_month', $data['planning_month'].'-01')->where('material', $selection['material'])
                ->whereNotNull('locked_at')->update([
                    'locked_at' => null, 'locked_by' => null,
                    'unlocked_at' => now(), 'unlocked_by' => $request->user()?->id, 'updated_at' => now(),
                ]);
        }

        return back()->with('success', $count.' planning decision(s) unlocked.');
    }

    private function validateBulkLock(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'planning_month' => ['required', 'date_format:Y-m'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.plant_id' => ['required', 'integer', 'exists:tbl_company,id'],
            'selections.*.material' => ['required', 'string', 'max:50', 'exists:tbl_materials,matnr'],
        ]);
    }

    private function persistLockedCalculation(int $decisionId): void
    {
        $decision = DB::table('procurement_planning_decisions')->where('id', $decisionId)->first();
        if (! $decision) {
            throw new RuntimeException('The planning decision could not be loaded.');
        }

        $month = CarbonImmutable::parse($decision->planning_month)->startOfMonth();
        $monthEnd = $month->endOfMonth();
        $plantCode = DB::table('tbl_company')->where('id', $decision->plant_id)->value('company_code');
        $materialUnit = strtoupper(trim((string) DB::table('tbl_materials')->where('matnr', $decision->material)->value('meins')));
        $stock = DB::table('tbl_material_stocks')
            ->where('status', 1)
            ->where('plant_code', $plantCode)
            ->where('matnr', $decision->material)
            ->selectRaw('COALESCE(SUM(current_stock), 0) as opening_stock, COALESCE(SUM(auto_perday_consumption), 0) as auto_perday_consumption')
            ->first();
        $openingStock = $this->quantityToMt((float) ($stock?->opening_stock ?? 0), $materialUnit);

        $manualPlan = DB::table('procurement_daily_consumptions')
            ->where('status', 1)
            ->where('company_id', $decision->company_id)
            ->where('plant_id', $decision->plant_id)
            ->where('material', $decision->material)
            ->whereDate('consumption_date', '<=', $monthEnd->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $month->toDateString()))
            ->orderByDesc('consumption_date')
            ->first();
        $dailyConsumption = $decision->planning_mode === 'auto'
            ? $this->quantityToMt((float) ($stock?->auto_perday_consumption ?? 0), $materialUnit)
            : (float) ($manualPlan?->quantity_mt ?? 0);
        $isStartMonth = $manualPlan && CarbonImmutable::parse($manualPlan->consumption_date)->format('Y-m') === $month->format('Y-m');
        $days = $decision->planning_mode === 'auto'
            ? $monthEnd->day
            : ($manualPlan?->period_type === 'datewise' ? 1 : ($isStartMonth ? max(1, (int) $manualPlan->day_count) : $monthEnd->day));
        $plannedBuying = (float) ($manualPlan?->planned_buying_mt ?? 0);
        $purchaseOrders = (float) DB::table('vw_procurement_purchase_receipts')
            ->where('status', 1)
            ->where('delivery_status', '!=', 'cancelled')
            ->where('plant_id', $decision->plant_id)
            ->where('material', $decision->material)
            ->whereBetween('expected_delivery_date', [$month->toDateString(), $monthEnd->toDateString()])
            ->sum('quantity_mt');
        $totalBuying = $plannedBuying + $purchaseOrders;
        $totalRequired = $dailyConsumption * $days;
        $availability = $openingStock + $totalBuying;
        $closingStock = $availability - $totalRequired;
        $calculatedAt = now();

        DB::table('procurement_balance_results')->updateOrInsert(
            ['material' => $decision->material, 'plant_id' => $decision->plant_id, 'month' => $month->toDateString()],
            [
                'calculations' => json_encode([
                    'planning_mode' => $decision->planning_mode,
                    'opening_stock_mt' => $openingStock,
                    'daily_consumption_mt' => $dailyConsumption,
                    'day_count' => $days,
                    'total_required_mt' => $totalRequired,
                    'planned_buying_mt' => $plannedBuying,
                    'purchase_order_mt' => $purchaseOrders,
                    'total_buying_mt' => $totalBuying,
                    'total_availability_mt' => $availability,
                    'projected_closing_mt' => $closingStock,
                    'gross_shortage_mt' => max($totalRequired - $openingStock, 0),
                    'remaining_shortage_mt' => max($totalRequired - $availability, 0),
                    'cover_days' => $dailyConsumption > 0 ? $openingStock / $dailyConsumption : 0,
                    'locked_decision_id' => $decisionId,
                    'calculated_at' => $calculatedAt->toISOString(),
                ], JSON_THROW_ON_ERROR),
                'remarks' => 'Automatically stored when the '.strtoupper((string) $decision->planning_mode).' planning decision was locked.',
                'created_at' => $calculatedAt,
                'updated_at' => $calculatedAt,
            ],
        );
    }

    private function quantityToMt(float $quantity, string $unit): float
    {
        return match ($unit) {
            'KG', 'KGM' => $quantity / 1000,
            'MT', 'MTN', 'MTO', 'TON', 'TO', 'T' => $quantity,
            default => $quantity / 1000,
        };
    }

    private function validateSelections(string $companyCode, array $selections): array
    {
        $selections = collect($selections)->unique(fn (array $item) => $item['plant_id'].'|'.$item['material'])->values();
        $plantIds = $selections->pluck('plant_id')->unique()->values();
        $validPlantCount = DB::table('tbl_company')
            ->where('status', 1)
            ->where('record_type', 2)
            ->where('parent_company_id', $companyCode)
            ->whereRaw("LOWER(TRIM(company_name)) NOT IN ('head office', 'other plant')")
            ->whereIn('id', $plantIds)->count();
        abort_unless($validPlantCount === $plantIds->count(), 422, 'One or more selected plants are invalid.');

        $materials = $selections->pluck('material')->unique()->values();
        $materialType = self::COMPANY_MATERIAL_TYPES[$companyCode] ?? null;
        $validMaterialCount = DB::table('tbl_materials')->whereIn('matnr', $materials)->where('status', 1)
            ->whereRaw('UPPER(TRIM(mtart)) = ?', [$materialType])->count();
        abort_unless($validMaterialCount === $materials->count(), 422, 'One or more materials do not belong to the selected company.');

        return $selections->map(fn (array $item) => [
            'plant_id' => (int) $item['plant_id'],
            'material' => (string) $item['material'],
        ])->all();
    }

    private function releaseExpiredLocks(): void
    {
        DB::table('procurement_planning_decisions')
            ->whereDate('planning_month', '<', now('Asia/Kolkata')->startOfMonth()->toDateString())
            ->whereNotNull('locked_at')
            ->update([
                'locked_at' => null,
                'locked_by' => null,
                'updated_at' => now(),
            ]);
    }

    private function isSuperAdmin(Request $request): bool
    {
        return $request->user()?->getRoleNames()->contains(
            fn (string $role): bool => preg_match('/^super\s*admin(?:\b|\()/i', $role) === 1,
        ) ?? false;
    }
}
