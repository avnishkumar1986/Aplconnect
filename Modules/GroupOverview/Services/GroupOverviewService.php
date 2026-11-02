<?php

namespace Modules\GroupOverview\Services;

use App\Contracts\ModuleServiceContract;

class GroupOverviewService implements ModuleServiceContract
{
    public function metadata(): array
    {
        return [
            'key' => 'group-overview',
            'name' => 'Group Overview',
            'version' => '1.0.0',
            'description' => 'Group-level overview dashboard module.',
            'module_path' => 'Modules/GroupOverview',
            'service_class' => self::class,
            'sections' => ['Dashboard'],
            'capabilities' => ['group overview dashboard'],
        ];
    }

    public function health(): array
    {
        return ['status' => 'available', 'message' => 'Group Overview service is available.'];
    }
}
