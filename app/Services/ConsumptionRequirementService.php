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
        $plantCode = DB::table('tbl_company')->where('id', $data['plant_id'])->value('company_code');
        if (array_key_exists('opening_stock_mt', $data)) {
            $stockMt = $data['opening_stock_mt'] === null ? null : (float) $data['opening_stock_mt'];
        } else {
            $stock = DB::table(app(MaterialStockService::class)->readTable())->where('matnr', $data['material'])
                ->where('plant_code', $plantCode)->where('status', 1)->first();
            // A missing stock record means zero; an unsupported unit cannot be converted to MT.
            $stockMt = $stock ? $this->toMetricTonnes((float) $stock->current_stock, $stock->meins) : 0.0;
        }

        $consumptionStart = \Illuminate\Support\Carbon::parse($data['consumption_date']);
        $start = ($data['period_type'] ?? 'monthly') === 'datewise'
            ? $consumptionStart->toDateString()
            : $consumptionStart->copy()->startOfMonth()->toDateString();
        $end = ! empty($data['effective_to'])
            ? \Illuminate\Support\Carbon::parse($data['effective_to'])->toDateString()
            : \Illuminate\Support\Carbon::parse($start)->endOfMonth()->toDateString();
        $purchaseQuery = Schema::hasTable('procurement_purchases') ? DB::table('procurement_purchases')
            ->where('plant_id', $data['plant_id'])->where('material', $data['material'])->where('status', 1)
            ->when(Schema::hasColumn('procurement_purchases', 'delivery_status'), fn ($query) => $query->whereIn('delivery_status', ['planned', 'in_transit', 'partially_received'])) : null;
        if ($purchaseQuery) {
            if (Schema::hasColumn('procurement_purchases', 'consumption_id') && ! empty($data['id'])) {
                $purchaseQuery->where('consumption_id', $data['id']);
            } else {
                if (Schema::hasColumn('procurement_purchases', 'consumption_id')) $purchaseQuery->whereNull('consumption_id');
                $orderedDate = Schema::hasColumn('procurement_purchases', 'purchase_date') ? 'purchase_date' : 'expected_delivery_date';
                $purchaseQuery->whereBetween($orderedDate, [$start, $end]);
            }
        }
        // API-synchronized POs are mapped by material, plant and delivery date.
        // Date-wise plans therefore receive only POs delivered inside their
        // exact effective range; manual POs remain linked by consumption_id.
        $manualReferences = $purchaseQuery ? (clone $purchaseQuery)->pluck('reference')->filter()->values()->all() : [];
        $imports = Schema::hasTable('tbl_vendors') && $plantCode
            ? (float) DB::table('tbl_vendors')->where('status', 1)
                ->where('material_code', $data['material'])->where('plant_code', $plantCode)
                ->whereBetween('delivery_date', [$start, $end])
                ->when($manualReferences !== [], fn ($query) => $query->whereNotIn('purchase_order', $manualReferences))
                ->whereNotNull('mt_quantity')->sum('mt_quantity')
            : 0.0;
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
        $openingByBatch = [];
        $query = DB::table('procurement_daily_consumptions')
            ->when($activeOnly, fn ($q) => $q->where('status', 1))
            ->when($materialCodes !== null, fn ($q) => $q->whereIn('material', $materialCodes));
        if (Schema::hasColumn('procurement_daily_consumptions', 'planning_batch')) {
            $query->orderBy('planning_batch')->orderBy('planning_sequence');
        }
        $query->orderBy('consumption_date')->orderBy('id')
            ->chunk(250, function ($rows) use (&$openingByBatch) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    if ((int) $row->day_count < 1) {
                        $date = \Illuminate\Support\Carbon::parse($row->consumption_date);
                        $data['day_count'] = $date->daysInMonth - $date->day + 1;
                    }
                    $batchKey = ($row->planning_batch ?? null)
                        ? (string) $row->planning_batch
                        : 'single:'.$row->id;
                    if ((int) ($row->planning_sequence ?? 1) > 1 && array_key_exists($batchKey, $openingByBatch)) {
                        $data['opening_stock_mt'] = $openingByBatch[$batchKey];
                    }
                    $calculation = $this->calculate($data);
                    DB::table('procurement_daily_consumptions')->where('id', $row->id)
                        ->update($calculation + ['day_count' => $data['day_count']]);
                    $openingByBatch[$batchKey] = $calculation['projected_closing_mt'] ?? null;
                }
            });
    }
}
