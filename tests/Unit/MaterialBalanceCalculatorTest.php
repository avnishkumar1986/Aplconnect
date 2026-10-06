<?php

namespace Tests\Unit;

use App\Services\MaterialBalanceCalculator;
use PHPUnit\Framework\TestCase;

class MaterialBalanceCalculatorTest extends TestCase
{
    public function test_sheet_kml_example_and_negative_stock_rollover(): void
    {
        $calculator = new MaterialBalanceCalculator;
        $first = $calculator->calculate(67, 371, 0, 30, 17);
        $this->assertSame(438.0, $first['availability']);
        $this->assertSame(510.0, $first['expected_consumption']);
        $this->assertSame(-72.0, $first['closing']);
        $this->assertSame(72.0, $first['extra_required']);
        $next = $calculator->calculate($first['closing'], 0, 0, 30, 31);
        $this->assertSame(-1002.0, $next['closing']);
        $this->assertSame(1002.0, $next['extra_required']);
    }

    public function test_planned_buying_and_imports_are_added_once(): void
    {
        $result = (new MaterialBalanceCalculator)->calculate(1326, 105, 861.5, 110, 31);
        $this->assertSame(966.5, $result['total_buying']);
        $this->assertSame(2292.5, $result['availability']);
        $this->assertSame(-1117.5, $result['closing']);
        $this->assertSame(105.0, $result['to_buy']);
    }

    public function test_unknown_stock_remains_unknown_across_the_month(): void
    {
        $result = (new MaterialBalanceCalculator)->calculate(null, 100, 200, 10, 30);
        $this->assertNull($result['closing']);
        $this->assertNull($result['extra_required']);
        $this->assertNull($result['cover_days']);
    }

    public function test_zero_consumption_has_no_division_error_and_no_shortage(): void
    {
        $result = (new MaterialBalanceCalculator)->calculate(3466, 0, 0, 0, 3);
        $this->assertSame(3466.0, $result['closing']);
        $this->assertSame(0, $result['extra_required']);
        $this->assertNull($result['cover_days']);
    }
}
