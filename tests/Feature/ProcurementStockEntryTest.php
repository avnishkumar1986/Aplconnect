<?php

namespace Tests\Feature;

use App\Models\Login;
use App\Services\MaterialStockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Procurement\Http\Controllers\ProcurementController;
use Tests\TestCase;

class ProcurementStockEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('tbl_company', function (Blueprint $t) {
            $t->id(); $t->string('company_code'); $t->string('company_name');
            $t->string('parent_company_id')->nullable(); $t->integer('record_type'); $t->boolean('Status');
        });
        Schema::create('tbl_materials', function (Blueprint $t) {
            $t->id(); $t->string('matnr')->unique();
            foreach (['maktx', 'mtart', 'matkl', 'meins'] as $column) $t->string($column)->nullable();
            $t->boolean('status')->default(true); $t->timestamps();
        });
        Schema::create('tbl_material_stocks', function (Blueprint $t) {
            $t->id(); $t->string('matnr'); $t->string('plant_code');
            $t->string('material_desc')->nullable();
            $t->decimal('current_stock', 20, 3)->default(0); $t->decimal('stock_value', 20, 3)->default(0);
            $t->boolean('status')->default(true); $t->timestamp('synced_at')->nullable();
            $t->timestamps(); $t->unique(['matnr', 'plant_code']);
        });
        DB::statement('CREATE VIEW vw_material_stock_combined AS SELECT m.matnr, s.plant_code, m.maktx, m.mtart, m.matkl, m.meins, s.current_stock, s.stock_value, s.status, s.synced_at FROM tbl_material_stocks s JOIN tbl_materials m ON m.matnr = s.matnr');
        Schema::create('tbl_vendors', function (Blueprint $t) {
            $t->id(); $t->string('material_code'); $t->string('vendor_code')->nullable();
            $t->string('vendor_name')->nullable(); $t->string('purchase_order'); $t->boolean('status');
        });
        Schema::create('procurement_purchases', function (Blueprint $t) {
            $t->id(); $t->integer('company_id'); $t->integer('plant_id');
            $t->string('material'); $t->string('vendor_name');
            $t->date('purchase_date'); $t->decimal('quantity_mt', 14, 3);
            $t->date('expected_delivery_date'); $t->string('reference')->nullable();
        });
        DB::table('tbl_company')->insert([
            ['id' => 1, 'company_code' => '1000', 'company_name' => 'Company', 'parent_company_id' => null, 'record_type' => 1, 'Status' => 1],
            ['id' => 2, 'company_code' => '1200', 'company_name' => 'Plant', 'parent_company_id' => '1000', 'record_type' => 2, 'Status' => 1],
        ]);
        DB::table('tbl_materials')->insert([
            'matnr' => 'TEST', 'maktx' => 'Test material', 'mtart' => 'ZRAW',
            'matkl' => 'GROUP', 'meins' => 'KG', 'status' => 1,
        ]);
        DB::table('tbl_material_stocks')->insert([
            'matnr' => 'TEST', 'plant_code' => '1200', 'current_stock' => 75000,
        ]);
        $user = \Mockery::mock(Login::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('hasRole')->andReturn(true);
        $user->shouldReceive('can')->andReturn(true);
        $this->actingAs($user);
    }

    public function test_shortage_purchase_prefills_company_plant_material_and_quantity(): void
    {
        $data = app(ProcurementController::class)->createPurchase(Request::create('/', 'GET', [
            'company_id' => 1, 'plant_id' => 2, 'material' => 'TEST', 'quantity_mt' => '25.000',
        ]))->getData();
        $this->assertEquals('GROUP', $data['prefill']['material_group']);
        $this->assertEquals(25, $data['prefill']['quantity_mt']);
        $this->assertEquals(2, $data['prefill']['plant_id']);
        $this->assertEquals(75, $data['stockMap']['TEST|1200']);
    }

    public function test_purchase_prefill_rejects_plant_from_another_company(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ProcurementController::class)->createPurchase(Request::create('/', 'GET', [
            'company_id' => 1, 'plant_id' => 999, 'material' => 'TEST', 'quantity_mt' => 25,
        ]));
    }

    public function test_non_weight_units_are_not_treated_as_metric_tonnes(): void
    {
        DB::table('tbl_materials')->update(['meins' => 'PC']);
        $data = app(ProcurementController::class)->createConsumption()->getData();
        $this->assertNull($data['stockMap']['TEST|1200']);
    }

    public function test_repeated_stock_import_updates_one_material_plant_row(): void
    {
        $records = [['Material_Code' => 'TEST', 'Plant' => '1200', 'Current_Stock' => 90000, 'Stock_Value' => 123]];
        $service = app(MaterialStockService::class);
        $service->importStocks($records);
        $service->importStocks($records);
        $this->assertEquals(1, DB::table('tbl_material_stocks')->count());
        $this->assertEquals(90000, DB::table('tbl_material_stocks')->value('current_stock'));
    }

    public function test_stock_query_excludes_catalog_placeholders_and_inactive_rows(): void
    {
        DB::table('tbl_material_stocks')->insert([
            ['matnr' => 'CATALOG', 'plant_code' => '', 'current_stock' => 100, 'status' => 1],
            ['matnr' => 'INACTIVE', 'plant_code' => '1200', 'current_stock' => 100, 'status' => 0],
        ]);
        $this->assertEquals(['TEST'], app(MaterialStockService::class)->stockByPlantQuery()->pluck('matnr')->all());
    }

    public function test_purchase_table_shows_current_stock_for_the_matching_plant_in_mt(): void
    {
        DB::table('procurement_purchases')->insert([
            'company_id' => 1, 'plant_id' => 2, 'material' => 'TEST', 'vendor_name' => 'Vendor',
            'purchase_date' => '2026-09-30', 'quantity_mt' => 25, 'expected_delivery_date' => '2026-10-01',
        ]);
        DB::table('tbl_material_stocks')->insert([
            'matnr' => 'TEST', 'plant_code' => '1900', 'current_stock' => 900000,
        ]);
        $entries = app(ProcurementController::class)->purchases(Request::create('/', 'GET'))->getData()['entries'];
        $this->assertCount(1, $entries);
        $this->assertEquals(75, $entries->first()->current_stock_mt);
        DB::table('tbl_material_stocks')->where('plant_code', '1200')->delete();
        $entries = app(ProcurementController::class)->purchases(Request::create('/', 'GET'))->getData()['entries'];
        $this->assertEquals(0, $entries->first()->current_stock_mt);
    }

    public function test_purchase_vendor_list_uses_latest_order_for_each_vendor(): void
    {
        DB::table('tbl_vendors')->insert([
            ['material_code' => 'TEST', 'vendor_code' => 'V1', 'vendor_name' => 'Vendor', 'purchase_order' => 'PO1', 'status' => 1],
            ['material_code' => 'TEST', 'vendor_code' => 'V1', 'vendor_name' => 'Vendor', 'purchase_order' => 'PO1', 'status' => 1],
            ['material_code' => 'TEST', 'vendor_code' => 'V1', 'vendor_name' => 'Vendor', 'purchase_order' => 'PO2', 'status' => 1],
        ]);
        $vendors = app(ProcurementController::class)->createPurchase(Request::create('/', 'GET'))->getData()['vendors'];
        $this->assertCount(1, $vendors);
        $this->assertEquals('PO2', $vendors->first()->purchase_orders);
    }
}
