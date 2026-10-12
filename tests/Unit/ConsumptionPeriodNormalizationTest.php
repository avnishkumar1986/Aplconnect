<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Modules\Procurement\Http\Controllers\ProcurementController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ConsumptionPeriodNormalizationTest extends TestCase
{
    public function test_monthly_form_value_is_saved_as_a_daily_rate_and_period_total(): void
    {
        $result = $this->normalize(Carbon::parse('2026-10-06'), null, 20);

        $this->assertSame(26, $result['day_count']);
        $this->assertSame(20.0, $result['daily_consumption_mt']);
        $this->assertSame(520.0, $result['quantity_mt']);
    }

    public function test_datewise_form_value_is_applied_to_every_selected_day(): void
    {
        $result = $this->normalize(Carbon::parse('2026-10-06'), Carbon::parse('2026-10-10'), 12);

        $this->assertSame(5, $result['day_count']);
        $this->assertSame(12.0, $result['daily_consumption_mt']);
        $this->assertSame(60.0, $result['quantity_mt']);
    }

    public function test_balance_uses_only_selected_datewise_days_in_the_month(): void
    {
        $method = new ReflectionMethod(ProcurementController::class, 'datewiseMonthlyMetrics');
        $metrics = $method->invoke(new ProcurementController, collect([
            (object) [
                'consumption_date' => '2026-10-07',
                'effective_to' => '2026-10-31',
                'daily_consumption_mt' => 20,
            ],
        ]), Carbon::parse('2026-10-01'));

        $this->assertSame(25, $metrics['working_days']);
        $this->assertSame(500.0, $metrics['expected_consumption']);
    }

    public function test_monthly_rate_applies_only_before_first_datewise_change(): void
    {
        $method = new ReflectionMethod(ProcurementController::class, 'datewiseMonthlyMetrics');
        $metrics = $method->invoke(new ProcurementController, collect([
            (object) ['id' => 16, 'consumption_date' => '2026-10-15', 'effective_to' => '2026-10-21', 'daily_consumption_mt' => 11],
            (object) ['id' => 17, 'consumption_date' => '2026-10-22', 'effective_to' => '2026-10-31', 'daily_consumption_mt' => 14],
        ]), Carbon::parse('2026-10-01'), (object) [
            'consumption_date' => '2026-10-07',
            'daily_consumption_mt' => 10,
        ]);

        $this->assertSame(25, $metrics['working_days']);
        $this->assertSame(297.0, $metrics['expected_consumption']);
    }

    public function test_monthly_rate_does_not_continue_after_datewise_plan_starts(): void
    {
        $method = new ReflectionMethod(ProcurementController::class, 'datewiseMonthlyMetrics');
        $metrics = $method->invoke(new ProcurementController, collect([
            (object) ['id' => 15, 'consumption_date' => '2026-11-01', 'effective_to' => '2026-11-14', 'daily_consumption_mt' => 12],
        ]), Carbon::parse('2026-11-01'), (object) [
            'consumption_date' => '2026-10-07',
            'daily_consumption_mt' => 10,
        ]);

        $this->assertSame(14, $metrics['working_days']);
        $this->assertSame(168.0, $metrics['expected_consumption']);
    }

    private function normalize(Carbon $from, ?Carbon $until, float $dailyQuantity): array
    {
        $method = new ReflectionMethod(ProcurementController::class, 'normalizeConsumptionPeriod');

        return $method->invoke(new ProcurementController, [], $from, $until, $dailyQuantity);
    }
}
