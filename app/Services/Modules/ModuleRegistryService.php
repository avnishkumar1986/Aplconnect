<?php

namespace App\Services\Modules;

use App\Contracts\ModuleServiceContract;
use App\Models\ModulePackage;
use Illuminate\Support\Facades\Storage;
use Modules\CorporateChat\Services\CorporateChatService;
use Modules\GroupOverview\Services\GroupOverviewService;
use Modules\Procurement\Services\ProcurementService;
use Modules\ProductionCapacity\Services\ProductionCapacityService;
use Modules\Sales\Services\SalesService;
use Modules\SalesDistribution\Services\SalesDistributionService;
use Modules\Support\Services\SupportService;

class ModuleRegistryService
{
    /** @var array<string,class-string<ModuleServiceContract>> */
    private array $services = [
        'group-overview' => GroupOverviewService::class,
        'procurement' => ProcurementService::class,
        'production-capacity' => ProductionCapacityService::class,
        'sales' => SalesService::class,
        'sales-distribution' => SalesDistributionService::class,
        'corporate-chat' => CorporateChatService::class,
        'support' => SupportService::class,
    ];

    /** @var array<string,string> */
    private array $serviceFiles = [
        'group-overview' => 'Modules/GroupOverview/Services/GroupOverviewService.php',
        'procurement' => 'Modules/Procurement/Services/ProcurementService.php',
        'production-capacity' => 'Modules/ProductionCapacity/Services/ProductionCapacityService.php',
        'sales' => 'Modules/Sales/Services/SalesService.php',
        'sales-distribution' => 'Modules/SalesDistribution/Services/SalesDistributionService.php',
        'corporate-chat' => 'Modules/CorporateChat/Services/CorporateChatService.php',
        'support' => 'Modules/Support/Services/SupportService.php',
    ];

    /** @var array<string,array{module_path:string,sections:list<string>}> */
    private array $moduleDefinitions = [
        'group-overview' => [
            'module_path' => 'Modules/GroupOverview',
            'sections' => ['Dashboard'],
        ],
        'procurement' => [
            'module_path' => 'Modules/Procurement',
            'sections' => ['Dashboard', 'Planning / Consumption', 'Planning / Balance Sheet', 'Planning / Material Inventory'],
        ],
        'production-capacity' => [
            'module_path' => 'Modules/ProductionCapacity',
            'sections' => ['Dashboard'],
        ],
        'sales' => [
            'module_path' => 'Modules/Sales',
            'sections' => ['Dashboard'],
        ],
        'sales-distribution' => [
            'module_path' => 'Modules/SalesDistribution',
            'sections' => ['Dashboard'],
        ],
        'corporate-chat' => [
            'module_path' => 'Modules/CorporateChat',
            'sections' => ['Conversations'],
        ],
        'support' => [
            'module_path' => 'Modules/Support',
            'sections' => ['Support Desk', 'Ticket Management', 'Internal Notes', 'Reports'],
        ],
    ];

    public function isInstalled(ModulePackage $module): bool
    {
        if ($module->source !== 'service') {
            return filled($module->package_path) && Storage::disk('local')->exists($module->package_path);
        }

        $serviceFile = $this->serviceFiles[$module->slug] ?? null;

        return $serviceFile !== null && is_file(base_path($serviceFile));
    }

    public function resolve(ModulePackage $module): ?ModuleServiceContract
    {
        $class = $this->services[$module->slug] ?? null;
        $serviceFile = $this->serviceFiles[$module->slug] ?? null;

        return $class && $serviceFile && $this->isInstalled($module) && class_exists($class)
            ? app($class)
            : null;
    }

    /** @return array<string,mixed>|null */
    public function details(ModulePackage $module): ?array
    {
        $service = $this->resolve($module);

        if ($service) {
            return [...$service->metadata(), 'health' => $service->health()];
        }

        $definition = $this->moduleDefinitions[$module->slug] ?? null;
        if (! $definition) {
            return null;
        }

        $serviceClass = $this->services[$module->slug];

        return [
            ...$definition,
            'service_class' => $serviceClass,
            'capabilities' => $module->manifest['capabilities'] ?? [],
            'health' => [
                'status' => 'missing',
                'message' => "Module not present at {$definition['module_path']}.",
            ],
        ];
    }
}
