<?php

namespace Modules\ProductionCapacity\Services;

use App\Contracts\ModuleServiceContract;

class ProductionCapacityService implements ModuleServiceContract
{
    public function metadata(): array
    {
        return [
            'key' => 'production-capacity',
            'name' => 'Production & Capacity',
            'version' => '1.0.0',
            'description' => 'Production output, plan achievement, capacity utilization and lost-time dashboard.',
            'module_path' => 'Modules/ProductionCapacity',
            'service_class' => self::class,
            'sections' => ['Dashboard'],
            'capabilities' => ['production output', 'capacity utilization', 'lost line hours', 'production scorecard'],
        ];
    }

    public function health(): array
    {
        return ['status' => 'available', 'message' => 'Production & Capacity service is available.'];
    }
}
