<?php

namespace Tests\Feature;

use App\Models\Login;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Procurement\Http\Controllers\ProcurementController;
use Tests\TestCase;

class PlanningInputsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); });
        Schema::create('model_has_roles', function (Blueprint $t) { $t->unsignedBigInteger('role_id'); $t->unsignedBigInteger('model_id'); $t->string('model_type'); });
        Schema::create('model_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('model_id'); $t->string('model_type'); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('role_id'); $t->unsignedBigInteger('permission_id'); });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::create('tbl_company', function (Blueprint $t) {
            $t->increments('id'); $t->string('company_code'); $t->string('company_name');
            $t->integer('record_type'); $t->integer('Status'); $t->string('parent_company_id')->nullable();
        });
        Schema::create('procurement_daily_consumptions', function (Blueprint $t) {
            $t->id(); $t->integer('company_id'); $t->integer('plant_id'); $t->string('material');
            $t->date('consumption_date'); $t->integer('day_count'); $t->decimal('quantity_mt', 14, 3);
            $t->decimal('current_stock_mt', 20, 3)->nullable(); $t->decimal('daily_consumption_mt', 20, 6);
            $t->decimal('extra_required_mt', 20, 3)->nullable(); $t->timestamp('stock_calculated_at')->nullable();
            $t->string('remarks')->nullable(); $t->boolean('status'); $t->timestamps();
        });
        Schema::create('tbl_materials', function (Blueprint $t) {
            $t->id(); $t->string('matnr')->unique(); $t->string('maktx')->nullable();
            $t->string('mtart')->nullable(); $t->string('matkl')->nullable(); $t->string('meins');
            $t->boolean('status')->default(true); $t->timestamps();
        });
        Schema::create('tbl_material_stocks', function (Blueprint $t) {
            $t->id(); $t->string('matnr'); $t->string('plant_code'); $t->string('material_desc')->nullable();
            $t->decimal('current_stock', 20, 3); $t->decimal('stock_value', 20, 3)->default(0);
            $t->boolean('status'); $t->timestamp('synced_at')->nullable(); $t->timestamps();
            $t->unique(['matnr', 'plant_code']);
        });
        DB::statement('CREATE VIEW vw_material_stock_combined AS SELECT m.matnr, s.plant_code, m.maktx, m.mtart, m.matkl, m.meins, s.current_stock, s.stock_value, s.status, s.synced_at FROM tbl_material_stocks s JOIN tbl_materials m ON m.matnr = s.matnr');
        Schema::create('tbl_vendors', function (Blueprint $t) {
            $t->id(); $t->string('vendor_name'); $t->string('vendor_code')->nullable();
            $t->string('material_code'); $t->string('plant_code'); $t->boolean('status');
        });
        (require base_path('Modules/Procurement/database/migrations/2026_09_30_500000_create_supply_planning_details.php'))->up();
        DB::table('tbl_company')->insert([
            ['id' => 1, 'company_code' => '1000', 'company_name' => 'Company', 'record_type' => 1, 'Status' => 1, 'parent_company_id' => null],
            ['id' => 2, 'company_code' => '1200', 'company_name' => 'Plant', 'record_type' => 2, 'Status' => 1, 'parent_company_id' => '1000'],
        ]);
        DB::table('procurement_daily_consumptions')->insert(['id' => 1, 'company_id' => 1, 'plant_id' => 2, 'material' => 'TEST',
            'consumption_date' => '2026-10-01', 'day_count' => 31, 'quantity_mt' => 310, 'daily_consumption_mt' => 10, 'status' => 1]);
        DB::table('tbl_materials')->insert(['matnr' => 'TEST', 'meins' => 'MT', 'status' => 1]);
        DB::table('tbl_material_stocks')->insert(['matnr' => 'TEST', 'plant_code' => '1200', 'current_stock' => 15, 'status' => 1]);
        $user = new Login; $user->id = 1;
        Auth::setUser($user);
        Gate::before(fn ($user) => $user->id === 1);
    }

    public function test_plan_save_recalculates_and_retains_original_and_new_versions(): void
    {
        $request = Request::create('/', 'PUT', ['daily_consumption_mt' => 10, 'day_count' => 2, 'version' => 1, 'remarks' => 'Updated']);
        app(ProcurementController::class)->updateConsumptionPlan($request, 1);
        $row = DB::table('procurement_daily_consumptions')->first();
        $this->assertEquals(20, $row->quantity_mt);
        $this->assertEquals(-5, $row->projected_closing_mt);
        $this->assertEquals(5, $row->extra_required_mt);
        $this->assertSame(2, DB::table('procurement_plan_histories')->count());
        $original = json_decode(DB::table('procurement_plan_histories')->where('version', 1)->value('snapshot'), true);
        $this->assertEquals(310, $original['quantity_mt']);
        try {
            app(ProcurementController::class)->updateConsumptionPlan($request, 1);
            $this->fail('A stale version should be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_mou_save_updates_same_vendor_without_duplicate_rows(): void
    {
        DB::table('tbl_vendors')->insert(['vendor_name' => 'Supplier', 'vendor_code' => 'V1', 'material_code' => 'TEST', 'plant_code' => 'DIFFERENT-PLANT', 'status' => 1]);
        $controller = app(ProcurementController::class);
        $controller->storeConsumptionMou(Request::create('/', 'POST', ['vendor_name' => 'Supplier', 'quantity_mt' => 25]), 1);
        $controller->storeConsumptionMou(Request::create('/', 'POST', ['vendor_name' => 'Supplier', 'quantity_mt' => 30]), 1);
        $this->assertSame(1, DB::table('procurement_mou_plans')->count());
        $this->assertEquals(30, DB::table('procurement_mou_plans')->value('quantity_mt'));
    }

    public function test_user_without_edit_permission_cannot_save_plan_inputs(): void
    {
        $user = new Login; $user->id = 2;
        Auth::setUser($user);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(ProcurementController::class)->updateConsumptionPlan(Request::create('/', 'PUT', ['daily_consumption_mt' => 10, 'day_count' => 2, 'version' => 1]), 1);
    }

    public function test_consumption_can_be_removed_with_its_planning_history(): void
    {
        app(ProcurementController::class)->updateConsumptionPlan(
            Request::create('/', 'PUT', ['daily_consumption_mt' => 10, 'day_count' => 2, 'version' => 1]),
            1
        );
        $this->assertDatabaseCount('procurement_plan_histories', 2);

        app(ProcurementController::class)->destroyConsumption(1);

        $this->assertDatabaseMissing('procurement_daily_consumptions', ['id' => 1]);
        $this->assertDatabaseCount('procurement_plan_histories', 0);
    }
}
