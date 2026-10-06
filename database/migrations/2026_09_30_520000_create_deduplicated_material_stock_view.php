<?php

use App\Services\MaterialStockService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(MaterialStockService::MATERIAL_TABLE)
            || ! Schema::hasTable(MaterialStockService::STOCK_TABLE)) return;

        DB::statement('DROP VIEW IF EXISTS '.MaterialStockService::COMBINED_VIEW);
        DB::statement(<<<'SQL'
CREATE VIEW vw_material_stock_combined AS
SELECT
    s.matnr,
    s.plant_code,
    COALESCE(NULLIF(s.material_desc, ''), m.maktx, s.matnr) AS maktx,
    m.mtart,
    m.matkl,
    m.meins,
    s.current_stock,
    s.stock_value,
    CASE WHEN COALESCE(m.status, 1) = 1 AND s.status = 1 THEN 1 ELSE 0 END AS status,
    s.synced_at,
    m.updated_at AS material_updated_at,
    s.updated_at AS stock_updated_at
FROM tbl_material_stocks s
LEFT JOIN tbl_materials m
    ON m.id = (
        SELECT MAX(m2.id)
        FROM tbl_materials m2
        WHERE m2.matnr = s.matnr
    )
UNION ALL
SELECT
    m.matnr,
    '' AS plant_code,
    m.maktx,
    m.mtart,
    m.matkl,
    m.meins,
    0 AS current_stock,
    0 AS stock_value,
    m.status,
    NULL AS synced_at,
    m.updated_at AS material_updated_at,
    NULL AS stock_updated_at
FROM tbl_materials m
WHERE m.id = (
    SELECT MAX(m2.id)
    FROM tbl_materials m2
    WHERE m2.matnr = m.matnr
)
AND NOT EXISTS (
    SELECT 1 FROM tbl_material_stocks s WHERE s.matnr = m.matnr
)
SQL);

        DB::table('api_integrations')->where('code', 'Material')->update([
            'tbl_name' => MaterialStockService::MATERIAL_TABLE, 'updated_at' => now(),
        ]);
        DB::table('api_integrations')->where('code', 'stock')->update([
            'tbl_name' => MaterialStockService::STOCK_TABLE, 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS '.MaterialStockService::COMBINED_VIEW);
    }
};
