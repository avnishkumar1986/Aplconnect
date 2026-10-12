<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ConsumptionRequirementService
{
    public function calculate(array $data): array
    {
        $days = (int) $data['day_count'];
        if ($days < 1) throw new InvalidArgumentException('Day count must be greater than zero.');
        $quantity = round((float) $data['quantity_mt'], 3);
        // New records store business codes, while older records and imports may
        // still contain tbl_company IDs. Resolve both representations here so
        // stock and purchase calculations remain correct during migration.
        $plantReference = (string) $data['plant_id'];
        $plant = DB::table('tbl_company')
            ->where('company_code', $plantReference)
            ->when(ctype_digit($plantReference), fn ($query) => $query->orWhere('id', (int) $plantReference))
            ->select('id', 'company_code')
            ->first();
        $plantCode = (string) ($plant->company_code ?? $plantReference);
        $plantDatabaseId = $plant->id ?? (ctype_digit($plantReference) ? (int) $plantReference : null);
        if (array_key_exists('opening_stock_mt', $data)) {
            $stockMt = $data['opening_stock_mt'] === null ? null : (float) $data['opening_stock_mt'];
        } else {
            $stock = DB::table(app(MaterialStockService::class)->readTable())->where('matnr', $data['material'])
                ->where('plant_code', $plantCode)->where('status', 1)->first();
            // A missing stock record means zero; an unsupported unit cannot be converted to MT.
            $stockMt = $stock ? $this->toMetricTonnes((float) $stock->current_stock, $stock->meins) : 0.0;
        }

        $consumptionStart = \Illuminate\Support\Carbon::parse($data['consumption_date']);
        // Purchasing is planned monthly even when consumption is split into date-wise periods.
        $start = $consumptionStart->copy()->startOfMonth()->toDateString();
        $end = $consumptionStart->copy()->endOfMonth()->toDateString();
        $purchaseQuery = Schema::hasTable('procurement_purchases') ? DB::table('procurement_purchases')
            ->where('plant_id', $plantDatabaseId)->where('material', $data['material'])->where('status', 1)
            ->when(Schema::hasColumn('procurement_purchases', 'delivery_status'), fn ($query) => $query->where('delivery_status', '<>', 'cancelled')) : null;
        if ($purchaseQuery) {
            if (Schema::hasColumn('procurement_purchases', 'consumption_id') && ! empty($data['id'])) {
                $purchaseQuery->where('consumption_id', $data['id']);
            } else {
                if (Schema::hasColumn('procurement_purchases', 'consumption_id')) $purchaseQuery->whereNull('consumption_id');
                $orderedDate = Schema::hasColumn('procurement_purchases', 'purchase_date') ? 'purchase_date' : 'expected_delivery_date';
                $purchaseQuery->whereBetween($orderedDate, [$start, $end]);
            }
        }
        // API-synchronized POs are mapped by material, plant and planning month.
        // Manual POs remain linked directly to their consumption plan.
        $manualReferences = $purchaseQuery ? (clone $purchaseQuery)->pluck('reference')->filter()->values()->all() : [];
        $imports = 0.0;
        $includeApiImports = ! array_key_exists('_include_api_imports', $data) || (bool) $data['_include_api_imports'];
        $vendorImportColumns = ['plant_code', 'material_code', 'delivery_date', 'mt_quantity', 'purchase_order', 'status'];
        $canReadApiImports = Schema::hasTable('tbl_vendors')
            && collect($vendorImportColumns)->every(fn ($column) => Schema::hasColumn('tbl_vendors', $column));
        if ($includeApiImports && $canReadApiImports && $plantCode) {
            $importQuery = DB::table('tbl_vendors')->where('status', 1)
                ->where('material_code', $data['material'])->where('plant_code', $plantCode)
                ->when($manualReferences !== [], fn ($query) => $query->whereNotIn('purchase_order', $manualReferences))
                ->whereNotNull('mt_quantity');
            // Only the live month receives overdue open SAP items. Future
            // months retain their own delivery window so a PO is never counted
            // again in every subsequent month.
            if ($consumptionStart->isSameMonth(now())) {
                $importQuery->whereDate('delivery_date', '<=', $end);
            } else {
                $importQuery->whereBetween('delivery_date', [$start, $end]);
            }
            $imports = (float) $importQuery->sum('mt_quantity');
        }
        $plannedBuying = $purchaseQuery ? (float) (clone $purchaseQuery)->sum('quantity_mt') : 0.0;
        $daily = array_key_exists('daily_consumption_mt', $data)
            ? round((float) $data['daily_consumption_mt'], 6)
            : round($quantity / $days, 6);
        $balance = app(MaterialBalanceCalculator::class)->calculate($stockMt, $plannedBuying, $imports, $daily, $days);
        $result = [
            'current_stock_mt' => $stockMt === null ? null : round($stockMt, 3),
            'daily_consumption_mt' => $daily,
            'extra_required_mt' => $balance['extra_required'],
            'stock_calculated_at' => now(),
        ];
        if (Schema::hasColumn('procurement_daily_consumptions', 'total_buying_mt')) {
            $result += [
                'planned_buying_mt' => $balance['local_buying'], 'imports_mt' => $balance['sap_buying'],
                'total_buying_mt' => $balance['total_buying'], 'availability_mt' => $balance['availability'],
                'projected_closing_mt' => $balance['closing'], 'stock_cover_days' => $balance['cover_days'],
            ];
        }
        return $result;

    }

    public function toMetricTonnes(float $quantity, ?string $unit): ?float
    {
        return match (strtoupper(trim((string) $unit))) {
            'KG', 'KGS', 'KGM' => $quantity / 1000,
            'G', 'GR', 'GRAM', 'GRAMS' => $quantity / 1000000,
            'MT', 'T', 'TO', 'TON', 'TONS', 'TONNE', 'TONNES' => $quantity,
            default => null,
        };
    }

    public function refresh(?array $materialCodes = null, bool $activeOnly = true): void
    {
        // Stock imports can also run before this feature's migration is installed.
        if (! Schema::hasColumn('procurement_daily_consumptions', 'extra_required_mt')) return;
        $apiImportsAllocated = [];
        $query = DB::table('procurement_daily_consumptions')
            ->when($activeOnly, fn ($q) => $q->where('status', 1))
            ->when($materialCodes !== null, fn ($q) => $q->whereIn('material', $materialCodes));
        $hasPeriodType = Schema::hasColumn('procurement_daily_consumptions', 'period_type');
        $hasEffectiveTo = Schema::hasColumn('procurement_daily_consumptions', 'effective_to');
        $query->orderBy('consumption_date')
            ->when(Schema::hasColumn('procurement_daily_consumptions', 'planning_sequence'), fn ($q) => $q->orderBy('planning_sequence'))
            ->orderBy('id')
            ->chunk(250, function ($rows) use (&$apiImportsAllocated, $hasPeriodType, $hasEffectiveTo) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $periodStart = \Illuminate\Support\Carbon::parse($row->consumption_date)->startOfDay();
                    $isDatewise = $hasPeriodType && (string) ($row->period_type ?? '') === 'datewise';
                    $periodEnd = $isDatewise && $hasEffectiveTo && ! empty($row->effective_to)
                        ? \Illuminate\Support\Carbon::parse($row->effective_to)->startOfDay()
                        : $periodStart->copy()->endOfMonth()->startOfDay();
                    $data['day_count'] = $hasPeriodType
                        ? max(1, (int) round($periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay())) + 1)
                        : max(1, (int) ($row->day_count ?? 1));
                    if (array_key_exists('daily_consumption_mt', $data)) {
                        $data['quantity_mt'] = round((float) $data['daily_consumption_mt'] * $data['day_count'], 3);
                    }
                    $planKey = (string) $row->plant_id.'|'.(string) $row->material;
                    // Every planning record must show the current live stock
                    // from the material/plant stock table. Projected closing
                    // belongs to the plan calculation and must not overwrite
                    // the next record's Current Stock value.
                    unset($data['opening_stock_mt']);
                    $importKey = $planKey.'|'.$periodStart->format('Y-m');
                    $data['_include_api_imports'] = ! isset($apiImportsAllocated[$importKey]);
                    $calculation = $this->calculate($data);
                    DB::table('procurement_daily_consumptions')->where('id', $row->id)
                        ->update($calculation + [
                            'day_count' => $data['day_count'],
                            'quantity_mt' => $data['quantity_mt'],
                        ]);
                    $apiImportsAllocated[$importKey] = true;
                }
            });
    }
}
