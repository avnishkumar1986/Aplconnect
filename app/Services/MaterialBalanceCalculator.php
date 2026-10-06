<?php

namespace App\Services;

class MaterialBalanceCalculator
{
    public function calculate(?float $opening, float $plannedBuying, float $imports, float $dailyConsumption, int $workingDays): array
    {
        if ($workingDays < 1 || $workingDays > 31) throw new \InvalidArgumentException('Working days must be between 1 and 31.');
        $totalBuying = round($plannedBuying + $imports, 3);
        $required = round($dailyConsumption * $workingDays, 3);
        $availability = $opening === null ? null : round($opening + $totalBuying, 3);
        $closing = $availability === null ? null : round($availability - $required, 3);
        $grossShortage = $opening === null ? null : max(0, round($required - $opening, 3));
        return [
            'opening' => $opening, 'cover_days' => $opening === null || $dailyConsumption <= 0 ? null : round($opening / $dailyConsumption, 3),
            'to_buy' => round($plannedBuying, 3), 'sap_buying' => round($imports, 3), 'local_buying' => round($plannedBuying, 3),
            'total_buying' => $totalBuying, 'availability' => $availability,
            'daily_consumption' => $dailyConsumption, 'working_days' => $workingDays, 'expected_consumption' => $required,
            'closing' => $closing, 'gross_shortage' => $grossShortage,
            'extra_required' => $closing === null ? null : max(0, -$closing),
        ];
    }
}
