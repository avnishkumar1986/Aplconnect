<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MaterialStockService
{
    public const MATERIAL_TABLE = 'tbl_materials';
    public const STOCK_TABLE = 'tbl_material_stocks';
    public const COMBINED_VIEW = 'vw_material_stock_combined';

    public function readTable(): string
    {
        try {
            DB::table(self::COMBINED_VIEW)->limit(1)->get();
            return self::COMBINED_VIEW;
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'The consolidated material stock view '.self::COMBINED_VIEW.' is unavailable. Run the database migrations.',
                previous: $exception
            );
        }
    }

    public function stockByPlantQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table($this->readTable())->where('plant_code', '<>', '')->where('status', 1)
            ->select('matnr', 'plant_code', 'current_stock', 'meins');
    }

    public function materialCodes(bool $activeOnly = true)
    {
        return DB::table($this->readTable())->when($activeOnly, fn ($q) => $q->where('status', 1))
            ->where('matnr', '<>', '')->distinct()->orderBy('matnr')->pluck('matnr');
    }

    // Master updates change descriptive fields, never a plant's stock quantities.
    public function importMaterials(array $records): int
    {
        $saved = 0;
        DB::transaction(function () use ($records, &$saved) {
            foreach ($records as $record) {
                if (! is_array($record)) continue;
                $values = $this->normalize($record);
                $code = trim((string) $this->value($values, ['matnr', 'Material_Code', 'Material']));
                if ($code === '') throw new RuntimeException('Material API record is missing its material code.');
                $metadata = $this->metadata($values);
                $metadata['updated_at'] = now();
                if (Schema::hasTable(self::MATERIAL_TABLE)) {
                    $master = DB::table(self::MATERIAL_TABLE)->where('matnr', $code);
                    if ($master->exists()) {
                        $master->update($metadata);
                    } else {
                        DB::table(self::MATERIAL_TABLE)->insert($metadata + [
                            'matnr' => $code, 'plant' => null, 'current_stock' => 0,
                            'stock_value' => 0, 'created_at' => now(),
                        ]);
                    }
                }
                $saved++;
            }
        });
        return $saved;
    }

    public function importStocks(array $records, ?string $requestedMaterial = null): int
    {
        $rows = [];
        $timestamp = now()->toDateTimeString();
        foreach ($records as $record) {
            if (! is_array($record)) throw new RuntimeException('Invalid stock API record.');
            $values = $this->normalize($record);
            $code = trim((string) $this->value($values, ['Material_Code', 'matnr', 'Material']));
            $plant = trim((string) $this->value($values, ['Plant', 'plant_code']));
            if ($code === '' || $plant === '') throw new RuntimeException('Stock API record is missing material or plant.');
            if ($requestedMaterial !== null && $code !== trim($requestedMaterial)) {
                throw new RuntimeException('SAP stock material filter mismatch for '.$requestedMaterial);
            }
            $rows[$code.'|'.$plant] = [
                'matnr' => $code, 'plant_code' => $plant,
                'maktx' => $this->value($values, ['Material_Desc', 'maktx', 'material_desc']),
                'current_stock' => $this->number($values, ['Current_Stock']),
                'stock_value' => $this->number($values, ['Stock_Value']),
                'status' => $this->value($values, ['status']) ?? 1,
                'synced_at' => $this->value($values, ['synced_at']) ?? $timestamp,
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ];
        }
        if (! $rows) return 0;
        DB::transaction(function () use ($rows) {
            if (Schema::hasTable(self::STOCK_TABLE)) {
                $sourceRows = array_map(fn ($row) => [
                    'matnr' => $row['matnr'], 'plant_code' => $row['plant_code'],
                    'material_desc' => $row['maktx'], 'current_stock' => $row['current_stock'],
                    'stock_value' => $row['stock_value'], 'status' => $row['status'],
                    'synced_at' => $row['synced_at'], 'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ], array_values($rows));
                foreach (array_chunk($sourceRows, 500) as $chunk) {
                    DB::table(self::STOCK_TABLE)->upsert($chunk, ['matnr', 'plant_code'], [
                        'material_desc', 'current_stock', 'stock_value', 'status', 'synced_at', 'updated_at',
                    ]);
                }
            }
            $catalog = DB::table(self::MATERIAL_TABLE)->whereIn('matnr', array_unique(array_column($rows, 'matnr')))
                ->selectRaw('matnr, MAX(maktx) AS maktx, MAX(mtart) AS mtart, MAX(matkl) AS matkl, MAX(meins) AS meins')
                ->groupBy('matnr')->get()->keyBy('matnr');
            foreach ($rows as $row) {
                $master = $catalog->get($row['matnr']);
                if ($master || ! Schema::hasTable(self::MATERIAL_TABLE)) continue;
                DB::table(self::MATERIAL_TABLE)->insert([
                    'matnr' => $row['matnr'], 'maktx' => $row['maktx'] ?: $row['matnr'],
                    'plant' => null, 'current_stock' => 0, 'stock_value' => 0,
                    'status' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            app(ConsumptionRequirementService::class)->refresh(array_unique(array_column($rows, 'matnr')));
        });
        return count($rows);
    }

    private function normalize(array $record): array
    {
        $values = [];
        foreach ($record as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $values[strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string) $key))] = $value;
            }
        }
        return $values;
    }

    private function value(array $values, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            $key = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $alias));
            if (array_key_exists($key, $values)) return $values[$key];
        }
        return null;
    }

    private function number(array $values, array $aliases): mixed
    {
        $value = $this->value($values, $aliases);
        if ($value === null || $value === '') return 0;
        if (! is_numeric($value)) throw new RuntimeException('Invalid numeric stock value.');
        return $value;
    }

    private function metadata(array $values): array
    {
        $row = [];
        foreach (['maktx' => ['maktx', 'Material_Desc', 'material_desc'],
            'mtart' => ['mtart', 'MaterialType'], 'matkl' => ['matkl', 'MaterialGroup'],
            'meins' => ['meins', 'BaseUnit'], 'status' => ['status']] as $column => $aliases) {
            foreach ($aliases as $alias) {
                $key = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $alias));
                if (array_key_exists($key, $values)) { $row[$column] = $values[$key]; break; }
            }
        }
        return $row;
    }
}
