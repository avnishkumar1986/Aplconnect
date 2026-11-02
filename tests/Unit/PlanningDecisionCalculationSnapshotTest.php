<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Procurement\Http\Controllers\Planning\PlanningDecisionController;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    foreach (['procurement_balance_results', 'vw_procurement_purchase_receipts', 'procurement_daily_consumptions', 'procurement_planning_decisions', 'tbl_material_stocks', 'tbl_materials', 'tbl_company'] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::create('tbl_company', function (Blueprint $t): void {
        $t->id(); $t->string('company_code'); $t->string('company_name'); $t->integer('record_type');
        $t->string('parent_company_id')->nullable(); $t->boolean('status'); $t->timestamps();
    });
    Schema::create('tbl_materials', function (Blueprint $t): void {
        $t->id(); $t->string('matnr'); $t->string('mtart')->nullable(); $t->string('meins')->nullable();
        $t->string('maktx'); $t->boolean('status'); $t->timestamps();
    });
    Schema::create('tbl_material_stocks', function (Blueprint $t): void {
        $t->id(); $t->string('matnr'); $t->string('plant_code'); $t->decimal('current_stock', 20, 3);
        $t->decimal('stock_value', 20, 3); $t->decimal('auto_monthly_consumption', 20, 3);
        $t->decimal('auto_perday_consumption', 20, 3); $t->boolean('status'); $t->timestamps();
    });
    Schema::create('procurement_planning_decisions', function (Blueprint $t): void {
        $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('plant_id'); $t->string('material');
        $t->date('planning_month'); $t->string('planning_mode'); $t->boolean('status'); $t->timestamps();
    });
    Schema::create('procurement_daily_consumptions', function (Blueprint $t): void {
        $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('plant_id'); $t->string('material');
        $t->date('consumption_date'); $t->date('effective_to')->nullable(); $t->string('period_type');
        $t->decimal('quantity_mt', 20, 3); $t->unsignedInteger('day_count'); $t->decimal('planned_buying_mt', 20, 3);
        $t->boolean('status'); $t->uuid('submission_token'); $t->timestamps();
    });
    Schema::create('vw_procurement_purchase_receipts', function (Blueprint $t): void {
        $t->id(); $t->unsignedBigInteger('plant_id'); $t->string('material'); $t->date('expected_delivery_date');
        $t->decimal('quantity_mt', 20, 3); $t->string('delivery_status'); $t->boolean('status');
    });
    Schema::create('procurement_balance_results', function (Blueprint $t): void {
        $t->id(); $t->string('material'); $t->unsignedBigInteger('plant_id'); $t->date('month');
        $t->json('calculations'); $t->text('remarks')->nullable(); $t->timestamps();
        $t->unique(['material', 'plant_id', 'month']);
    });

    $now = now();
    $this->companyId = DB::table('tbl_company')->insertGetId([
        'company_code' => '1000', 'company_name' => 'Test Company', 'record_type' => 1,
        'status' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->plantId = DB::table('tbl_company')->insertGetId([
        'company_code' => '1100', 'company_name' => 'Test Plant', 'record_type' => 2,
        'parent_company_id' => '1000', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('tbl_materials')->insert([
        'matnr' => 'RAW-001', 'mtart' => 'ZRAW', 'meins' => 'KG', 'maktx' => 'Test Raw Material',
        'status' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('tbl_material_stocks')->insert([
        'matnr' => 'RAW-001', 'plant_code' => '1100', 'current_stock' => 5000,
        'stock_value' => 10000, 'auto_monthly_consumption' => 3100, 'auto_perday_consumption' => 100,
        'status' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
});

it('stores synchronized stock and automatic consumption for an auto lock', function (): void {
    $decisionId = snapshotDecision('auto');
    invokeSnapshot($decisionId);
    $c = savedSnapshot();
    expect($c)->planning_mode->toBe('auto')->opening_stock_mt->toEqual(5.0)
        ->daily_consumption_mt->toEqual(0.1)->day_count->toBe(31)->total_required_mt->toEqual(3.1)
        ->total_availability_mt->toEqual(5.0)->projected_closing_mt->toEqual(1.9);
});

it('stores manual consumption with automatically picked stock and purchase orders', function (): void {
    DB::table('procurement_daily_consumptions')->insert([
        'company_id' => $this->companyId, 'plant_id' => $this->plantId, 'material' => 'RAW-001',
        'consumption_date' => '2026-10-01', 'effective_to' => '2026-10-31', 'period_type' => 'monthly',
        'quantity_mt' => 2, 'day_count' => 10, 'planned_buying_mt' => 1, 'status' => 1,
        'submission_token' => fake()->uuid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('vw_procurement_purchase_receipts')->insert([
        'plant_id' => $this->plantId, 'material' => 'RAW-001', 'expected_delivery_date' => '2026-10-15',
        'quantity_mt' => 4, 'delivery_status' => 'planned', 'status' => 1,
    ]);
    $decisionId = snapshotDecision('manual');
    invokeSnapshot($decisionId);
    $c = savedSnapshot();
    expect($c)->planning_mode->toBe('manual')->opening_stock_mt->toEqual(5.0)
        ->daily_consumption_mt->toEqual(2.0)->day_count->toBe(10)->total_required_mt->toEqual(20.0)
        ->planned_buying_mt->toEqual(1.0)->purchase_order_mt->toEqual(4.0)
        ->total_availability_mt->toEqual(10.0)->remaining_shortage_mt->toEqual(10.0);
});

it('stores a zero-consumption snapshot when a manual decision has no consumption plan', function (): void {
    $decisionId = snapshotDecision('manual');

    invokeSnapshot($decisionId);

    $c = savedSnapshot();
    expect($c)->planning_mode->toBe('manual')
        ->daily_consumption_mt->toEqual(0.0)
        ->total_required_mt->toEqual(0.0)
        ->planned_buying_mt->toEqual(0.0)
        ->opening_stock_mt->toEqual(5.0)
        ->projected_closing_mt->toEqual(5.0);
});

function snapshotDecision(string $mode): int
{
    return DB::table('procurement_planning_decisions')->insertGetId([
        'company_id' => test()->companyId, 'plant_id' => test()->plantId, 'material' => 'RAW-001',
        'planning_month' => '2026-10-01', 'planning_mode' => $mode, 'status' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function invokeSnapshot(int $decisionId): void
{
    $method = new ReflectionMethod(PlanningDecisionController::class, 'persistLockedCalculation');
    $method->invoke(new PlanningDecisionController, $decisionId);
}

function savedSnapshot(): object
{
    return json_decode((string) DB::table('procurement_balance_results')->where('material', 'RAW-001')->value('calculations'), false, 512, JSON_THROW_ON_ERROR);
}
