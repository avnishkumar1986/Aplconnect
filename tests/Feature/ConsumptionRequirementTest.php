<?php

namespace Tests\Feature;

use App\Services\ConsumptionRequirementService;
use App\Services\MaterialStockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumptionRequirementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('tbl_company', function (Blueprint $t) {
            $t->id(); $t->string('company_code');
        });
        Schema::create('tbl_materials', function (Blueprint $t) {
            $t->id(); $t->string('matnr')->unique(); $t->string('maktx')->nullable();
            $t->string('mtart')->nullable(); $t->string('matkl')->nullable(); $t->string('meins')->nullable();
            $t->boolean('status')->default(true); $t->timestamps();
        });
        Schema::create('tbl_material_stocks', function (Blueprint $t) {
            $t->id(); $t->string('matnr'); $t->string('plant_code');
            $t->string('material_desc')->nullable();
            $t->decimal('current_stock', 20, 3); $t->decimal('stock_value', 20, 3)->default(0);
            $t->boolean('status')->default(true); $t->timestamp('synced_at')->nullable();
            $t->timestamps(); $t->unique(['matnr', 'plant_code']);
        });
        DB::statement('CREATE VIEW vw_material_stock_combined AS SELECT s.matnr, s.plant_code, COALESCE(s.material_desc,m.maktx,s.matnr) AS maktx, m.mtart, m.matkl, m.meins, s.current_stock, s.stock_value, CASE WHEN m.status=1 AND s.status=1 THEN 1 ELSE 0 END AS status, s.synced_at, m.updated_at AS material_updated_at, s.updated_at AS stock_updated_at FROM tbl_material_stocks s LEFT JOIN tbl_materials m ON m.matnr=s.matnr');
        Schema::create('procurement_daily_consumptions', function (Blueprint $t) {
            $t->id(); $t->integer('plant_id'); $t->string('material');
            $t->date('consumption_date'); $t->integer('day_count')->nullable();
            $t->decimal('quantity_mt', 14, 3); $t->boolean('status')->default(true);
            $t->decimal('current_stock_mt', 20, 3)->nullable();
            $t->decimal('daily_consumption_mt', 20, 6)->default(0);
            $t->decimal('extra_required_mt', 20, 3)->nullable();
            $t->decimal('planned_buying_mt', 20, 3)->default(0);
            $t->decimal('imports_mt', 20, 3)->default(0);
            $t->decimal('total_buying_mt', 20, 3)->default(0);
            $t->decimal('availability_mt', 20, 3)->nullable();
            $t->decimal('projected_closing_mt', 20, 3)->nullable();
            $t->decimal('stock_cover_days', 20, 3)->nullable();
            $t->timestamp('stock_calculated_at')->nullable();
        });
        DB::table('tbl_company')->insert(['id' => 1, 'company_code' => '1200']);
        DB::table('tbl_materials')->insert(['matnr' => 'TEST', 'maktx' => 'Test', 'mtart' => 'ZRAW', 'matkl' => 'GROUP', 'meins' => 'KG', 'status' => 1]);
    }

    private function data(): array
    {
        return ['plant_id' => 1, 'material' => 'TEST', 'quantity_mt' => 100,
            'day_count' => 31, 'consumption_date' => '2026-10-01'];
    }

    private function stock(float $quantity, string $unit = 'KG', string $plant = '1200'): void
    {
        DB::table('tbl_materials')->where('matnr', 'TEST')->update(['meins' => $unit]);
        DB::table('tbl_material_stocks')->insert(['matnr' => 'TEST',
            'plant_code' => $plant, 'current_stock' => $quantity]);
    }

    public function test_kilogram_stock_is_converted_and_other_plants_are_excluded(): void
    {
        $this->stock(13);
        $this->stock(900000, 'KG', '1900');
        $result = app(ConsumptionRequirementService::class)->calculate($this->data());
        $this->assertEquals(0.013, $result['current_stock_mt']);
        $this->assertEquals(99.987, $result['extra_required_mt']);
        $this->assertEquals(3.225806, $result['daily_consumption_mt']);
    }

    public function test_material_plant_lookup_uses_combined_view_when_legacy_table_is_missing(): void
    {
        DB::statement('DROP VIEW vw_material_stock_combined');
        DB::statement(<<<'SQL'
CREATE VIEW vw_material_stock_combined AS
SELECT 'RMRESPVCPP' AS matnr, '1200' AS plant_code, 'KG' AS meins,
       15000 AS current_stock, 1 AS status
SQL);

        $result = app(ConsumptionRequirementService::class)->calculate(array_replace($this->data(), [
            'material' => 'RMRESPVCPP',
        ]));

        $this->assertEquals(15, $result['current_stock_mt']);
        $this->assertEquals(85, $result['extra_required_mt']);
    }

    public function test_missing_stock_defaults_to_zero(): void
    {
        $result = app(ConsumptionRequirementService::class)->calculate($this->data());
        $this->assertEquals(0, $result['current_stock_mt']);
        $this->assertEquals(100, $result['extra_required_mt']);
    }

    public function test_sufficient_stock_has_no_extra_requirement(): void
    {
        $this->stock(120, 'MT');
        $this->assertEquals(0, app(ConsumptionRequirementService::class)->calculate($this->data())['extra_required_mt']);
    }

    public function test_non_weight_units_do_not_generate_incorrect_requirements(): void
    {
        $this->stock(75, 'PC');
        $result = app(ConsumptionRequirementService::class)->calculate($this->data());
        $this->assertNull($result['current_stock_mt']);
        $this->assertNull($result['extra_required_mt']);
    }

    public function test_stock_import_refreshes_saved_active_calculations_only(): void
    {
        $this->stock(75000);
        $service = app(ConsumptionRequirementService::class);
        $initial = $service->calculate($this->data());
        $active = DB::table('procurement_daily_consumptions')->insertGetId($this->data() + $initial);
        $inactive = DB::table('procurement_daily_consumptions')->insertGetId($this->data() + $initial + ['status' => 0]);
        app(MaterialStockService::class)->importStocks([
            ['Material_Code' => 'TEST', 'Plant' => '1200', 'Current_Stock' => 90000],
        ]);
        $this->assertEquals(10, DB::table('procurement_daily_consumptions')->where('id', $active)->value('extra_required_mt'));
        $this->assertEquals(90, DB::table('procurement_daily_consumptions')->where('id', $active)->value('current_stock_mt'));
        $this->assertEquals(25, DB::table('procurement_daily_consumptions')->where('id', $inactive)->value('extra_required_mt'));
    }

    public function test_only_active_purchases_and_imports_in_the_plan_period_reduce_shortage(): void
    {
        Schema::create('procurement_purchases', function (Blueprint $t) {
            $t->id(); $t->integer('plant_id'); $t->string('material');
            $t->date('expected_delivery_date'); $t->decimal('quantity_mt', 14, 3); $t->boolean('status');
            $t->string('reference')->nullable(); $t->string('delivery_status')->default('planned');
        });
        Schema::create('tbl_vendors', function (Blueprint $t) {
            $t->id(); $t->string('plant_code'); $t->string('material_code');
            $t->date('delivery_date'); $t->decimal('mt_quantity', 14, 3); $t->boolean('status'); $t->string('purchase_order');
        });
        $this->stock(20, 'MT');
        foreach ([['2026-10-12', 15, 1, 1], ['2026-11-01', 1000, 1, 1], ['2026-10-12', 1000, 0, 1], ['2026-10-12', 1000, 1, 2]] as [$date, $quantity, $status, $plant]) {
            DB::table('procurement_purchases')->insert(['plant_id' => $plant, 'material' => 'TEST', 'expected_delivery_date' => $date, 'quantity_mt' => $quantity, 'status' => $status]);
        }
        DB::table('procurement_purchases')->insert(['plant_id' => 1, 'material' => 'TEST', 'expected_delivery_date' => '2026-10-15', 'quantity_mt' => 25, 'reference' => 'PO1', 'status' => 1]);
        DB::table('tbl_vendors')->insert(['plant_code' => '1200', 'material_code' => 'TEST', 'delivery_date' => '2026-10-15', 'mt_quantity' => 25, 'purchase_order' => 'PO1', 'status' => 1]);
        DB::table('tbl_vendors')->insert(['plant_code' => '1200', 'material_code' => 'TEST', 'delivery_date' => '2026-10-15', 'mt_quantity' => 900, 'purchase_order' => 'UNSELECTED', 'status' => 1]);
        DB::table('tbl_vendors')->insert(['plant_code' => '1900', 'material_code' => 'TEST', 'delivery_date' => '2026-10-15', 'mt_quantity' => 1000, 'purchase_order' => 'PO1', 'status' => 1]);
        $result = app(ConsumptionRequirementService::class)->calculate($this->data());
        $this->assertEquals(0, $result['extra_required_mt']);
        $this->assertEquals(900, $result['imports_mt']);
    }

    public function test_zero_days_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ConsumptionRequirementService::class)->calculate(array_replace($this->data(), ['day_count' => 0]));
    }

    public function test_explicit_daily_consumption_is_multiplied_by_working_days(): void
    {
        $this->stock(0, 'MT');
        $result = app(ConsumptionRequirementService::class)->calculate([
            'plant_id' => 1,
            'material' => 'TEST',
            'quantity_mt' => 620,
            'daily_consumption_mt' => 20,
            'day_count' => 31,
            'consumption_date' => '2026-10-01',
        ]);

        $this->assertEquals(20, $result['daily_consumption_mt']);
        $this->assertEquals(620, $result['extra_required_mt']);
    }

    public function test_consecutive_month_uses_previous_closing_as_opening_stock(): void
    {
        $this->stock(50, 'MT');
        $service = app(ConsumptionRequirementService::class);
        $first = $service->calculate([
            'plant_id' => 1, 'material' => 'TEST', 'quantity_mt' => 100,
            'daily_consumption_mt' => 10, 'day_count' => 10, 'consumption_date' => '2026-10-22',
        ]);
        $second = $service->calculate([
            'plant_id' => 1, 'material' => 'TEST', 'quantity_mt' => 300,
            'daily_consumption_mt' => 10, 'day_count' => 30, 'consumption_date' => '2026-11-01',
            'opening_stock_mt' => $first['projected_closing_mt'],
        ]);

        $this->assertEquals(-50, $first['projected_closing_mt']);
        $this->assertEquals(-350, $second['projected_closing_mt']);
        $this->assertEquals(350, $second['extra_required_mt']);
    }
}
