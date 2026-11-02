<?php

namespace Modules\SalesDistribution\Services;

use App\Contracts\ModuleServiceContract;

class SalesDistributionService implements ModuleServiceContract
{
    public function metadata(): array
    {
        return [
            'key' => 'sales-distribution',
            'name' => 'Sales & Distribution',
            'version' => '1.0.0',
            'description' => 'Sales planning, order fulfilment and distribution operations dashboard.',
            'module_path' => 'Modules/SalesDistribution',
            'service_class' => self::class,
            'sections' => ['Dashboard'],
            'capabilities' => ['sales performance', 'order fulfilment', 'dispatch tracking', 'regional distribution'],
        ];
    }

    public function health(): array
    {
        return ['status' => 'available', 'message' => 'Sales & Distribution service is available.'];
    }
}
