<?php

use App\Services\MaterialStockService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(MaterialStockService::class);
        DB::transaction(function () use ($service) {
            DB::table('tbl_materials')->orderBy('id')->chunkById(250, function ($rows) use ($service) {
                $records = $rows->map(fn ($row) => (array) $row)->all();
                $service->importMaterials($records);
                $plantRows = array_values(array_filter($records, fn ($row) => filled($row['plant'])));
                if ($plantRows) $service->importStocks($plantRows);
            });
            // Plant stock is authoritative when the old tables overlap.
            DB::table('tbl_material_stocks')->orderBy('id')->chunkById(500, function ($rows) use ($service) {
                $service->importStocks($rows->map(fn ($row) => (array) $row)->all());
            });
            DB::table('api_integrations')->whereIn('tbl_name', ['tbl_materials', 'tbl_material_stocks'])
                ->update(['tbl_name' => MaterialStockService::STOCK_TABLE, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Original tables are retained. Do not discard consolidated stock on rollback.
        DB::table('api_integrations')->where('code', 'stock')
            ->update(['tbl_name' => 'tbl_material_stocks', 'updated_at' => now()]);
        DB::table('api_integrations')->where('code', 'Material')
            ->update(['tbl_name' => 'tbl_materials', 'updated_at' => now()]);
    }
};
