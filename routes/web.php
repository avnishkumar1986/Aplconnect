<?php

use App\Http\Controllers\Admin\ApiIntegrationController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\DesignationLevelController;
use App\Http\Controllers\Admin\EmployeeHierarchyController;
use App\Http\Controllers\Admin\MaterialController;
use App\Http\Controllers\Admin\MaterialStockController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserMasterController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\UserTypeController;
use App\Http\Controllers\DashboardLocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalActivityController;
use App\Http\Controllers\SecurityAuditController;
use App\Models\Company;
use App\Models\Material;
use App\Models\PortalLog;
use App\Models\SecurityFinding;
use App\Models\SecurityScan;
use App\Models\User;
use App\Models\UserLocation;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('dashboard', function (Request $request): Response {
        $requiresProductionSecurity = ! app()->environment(['local', 'testing']);
        $securityChecks = collect([
            [
                'title' => 'Debug mode disabled',
                'description' => 'Disable APP_DEBUG outside local development.',
                'passedDescription' => $requiresProductionSecurity
                    ? 'Debug mode is disabled.'
                    : 'Local development exception; production will require debug mode to be disabled.',
                'severity' => 'high',
                'detected' => $requiresProductionSecurity && (bool) config('app.debug'),
            ],
            [
                'title' => 'HTTPS transport enabled',
                'description' => 'Use HTTPS to protect credentials and portal traffic.',
                'passedDescription' => $requiresProductionSecurity
                    ? 'Portal traffic is protected with HTTPS.'
                    : 'Localhost HTTP is allowed; production will require HTTPS.',
                'severity' => 'high',
                'detected' => $requiresProductionSecurity && ! $request->isSecure(),
            ],
            [
                'title' => 'Secure session cookies enabled',
                'description' => 'Enable SESSION_SECURE_COOKIE when the portal uses HTTPS.',
                'passedDescription' => $requiresProductionSecurity
                    ? 'Session cookies are restricted to secure connections.'
                    : 'Localhost cookie exception; production will require secure cookies.',
                'severity' => 'medium',
                'detected' => $requiresProductionSecurity && ! (bool) config('session.secure'),
            ],
            [
                'title' => 'HTTP-only session cookies enabled',
                'description' => 'Enable HTTP-only cookies to reduce script access to sessions.',
                'passedDescription' => 'Session cookies are protected from script access.',
                'severity' => 'high',
                'detected' => ! (bool) config('session.http_only'),
            ],
            [
                'title' => 'Session SameSite protection enabled',
                'description' => 'Use lax or strict SameSite protection for session cookies.',
                'passedDescription' => 'Session cookies use SameSite request protection.',
                'severity' => 'medium',
                'detected' => ! in_array(config('session.same_site'), ['lax', 'strict'], true),
            ],
        ])->map(fn (array $check): array => [
            ...collect($check)->except('detected')->all(),
            'passed' => ! $check['detected'],
        ])
            ->values();

        $securityIssues = $securityChecks->where('passed', false)->values();
        $configurationScore = max(0, 100 - $securityIssues->sum(
            fn (array $issue): int => $issue['severity'] === 'high' ? 25 : 15,
        ));

        $latestLibraryScan = $request->user()->hasRole('Owner')
            ? SecurityScan::query()
                ->with('findings')
                ->whereIn('status', ['completed', 'completed_with_errors'])
                ->latest('completed_at')
                ->first()
            : null;

        $libraryChecks = $latestLibraryScan?->findings->map(fn (SecurityFinding $finding): array => [
            'title' => "{$finding->package_name}: {$finding->title}",
            'description' => collect([
                strtoupper($finding->ecosystem),
                $finding->installed_version ? "installed {$finding->installed_version}" : null,
                $finding->vulnerable_versions ? "affected {$finding->vulnerable_versions}" : null,
            ])->filter()->implode(' · '),
            'severity' => $finding->severity,
            'passed' => false,
            'advisoryUrl' => $finding->advisory_url,
            'ecosystem' => $finding->ecosystem,
            'packageName' => $finding->package_name,
            'installedVersion' => $finding->installed_version,
            'affectedVersions' => $finding->vulnerable_versions,
        ])->values() ?? collect();

        $allSecurityChecks = $securityChecks->concat($libraryChecks)->values();
        $allSecurityIssues = $allSecurityChecks->where('passed', false)->values();
        $securityScore = min($configurationScore, $latestLibraryScan?->score ?? 100);

        $activeSessions = DB::table('tbl_sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->timestamp)
            ->get(['user_id', 'user_agent']);
        $activeUserIds = $activeSessions->pluck('user_id')->unique();
        $deviceNameForUserAgent = static function (?string $userAgent): string {
            $userAgent ??= '';
            $device = preg_match('/Mobile|Android|iPhone|iPad|Tablet/i', $userAgent) ? 'Mobile' : 'Laptop';
            $browser = match (true) {
                str_contains($userAgent, 'Edg/') => 'Edge',
                str_contains($userAgent, 'Chrome/') => 'Chrome',
                str_contains($userAgent, 'Firefox/') => 'Firefox',
                str_contains($userAgent, 'Safari/') => 'Safari',
                default => 'Browser',
            };

            return "{$device} · {$browser}";
        };

        $sittingLocations = Company::query()
            ->whereIn('record_type', [2, 3])
            ->get(['company_code', 'company_name', 'record_type']);

        $userLocations = UserLocation::query()
            ->with([
                'user:id,unique_id,first_name,last_name',
                'user.departmentAssignment.company:id,company_name',
                'user.departmentAssignment.department:id,department_name',
                'user.departmentAssignment.designation:id,designation_name',
            ])
            ->whereIn('user_id', $activeUserIds)
            ->whereIn('id', UserLocation::query()->selectRaw('MAX(id)')->groupBy('user_id'))
            ->when(
                ! $request->user()->hasRole('Owner'),
                fn ($query) => $query->where('user_id', $request->user()->getKey()),
            )
            ->get()
            ->map(function (UserLocation $location) use ($activeSessions, $deviceNameForUserAgent, $sittingLocations): array {
                $assignedSittingLocation = $location->user->departmentAssignment?->sitting_location;
                $sittingLocation = $sittingLocations->first(
                    fn (Company $company): bool => in_array($assignedSittingLocation, [$company->company_code, $company->company_name], true),
                );

                return [
                    'userId' => $location->user_id,
                    'name' => $location->user->name,
                    'employeeId' => $location->user->unique_id,
                    'companyName' => $location->user->departmentAssignment?->company?->company_name,
                    'departmentName' => $location->user->departmentAssignment?->department?->department_name,
                    'designationName' => $location->user->departmentAssignment?->designation?->designation_name,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'locationName' => $location->location_name ?: "{$location->latitude}, {$location->longitude}",
                    'locationType' => match ($sittingLocation?->record_type) {
                        2 => 'plant',
                        3 => 'ho',
                        default => 'unassigned',
                    },
                    'sittingLocationCode' => $sittingLocation?->company_code,
                    'lastSeen' => ($location->captured_at ?? $location->updated_at)->toIso8601String(),
                    'devices' => $activeSessions
                        ->where('user_id', $location->user_id)
                        ->map(fn (object $session): string => $deviceNameForUserAgent($session->user_agent))
                        ->unique()
                        ->values(),
                ];
            });

        $companyLocations = Company::query()
            ->with('address:id,address_line_1,address_line_2,city,state,district,postal_code,country,latitude,longitude,status')
            ->whereIn('record_type', [2, 3])
            ->where('status', true)
            ->whereHas('address', fn ($query) => $query
                ->where('status', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereColumn('tbl_usraddress.user_id', 'tbl_company.company_code')
                ->whereRaw("((tbl_company.record_type = 2 AND tbl_usraddress.user_type = 'USR9') OR (tbl_company.record_type = 3 AND tbl_usraddress.user_type = 'USR11'))"))
            ->orderBy('company_name')
            ->get(['id', 'company_code', 'company_name', 'record_type', 'parent_company_id', 'address_id'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->company_code,
                'name' => $company->company_name,
                'parentCompanyCode' => $company->parent_company_id,
                'locationType' => $company->record_type === 2 ? 'plant' : 'ho',
                'latitude' => $company->address->latitude,
                'longitude' => $company->address->longitude,
                'address' => collect([
                    $company->address->address_line_1,
                    $company->address->address_line_2,
                    $company->address->city,
                    $company->address->district,
                    $company->address->state,
                    $company->address->postal_code,
                    $company->address->country,
                ])->filter()->implode(', '),
            ]);

        return Inertia::render('dashboard', [
            'organizationStats' => [
                'companies' => Company::query()->where('record_type', 1)->count(),
                'plants' => Company::query()->where('record_type', 2)->count(),
                'materials' => Material::query()->count(),
                'employees' => User::query()
                    ->where('status', true)
                    ->whereIn('user_type', UserType::query()->whereRaw('LOWER(name) = ?', ['employee'])->select('uid'))
                    ->count(),
            ],
            'userTypes' => UserType::query()
                ->where('status', true)
                ->orderBy('name')
                ->get(['uid', 'name']),
            'portalSecurity' => [
                'score' => $securityScore,
                'checks' => $allSecurityChecks,
                'issues' => $allSecurityIssues,
                'latestScan' => $latestLibraryScan ? [
                    'scannedAt' => $latestLibraryScan->completed_at?->toIso8601String(),
                    'status' => $latestLibraryScan->status,
                    'score' => $latestLibraryScan->score,
                    'totalFindings' => $latestLibraryScan->total_findings,
                    'composerFindings' => $latestLibraryScan->summary['composer'] ?? 0,
                    'npmFindings' => $latestLibraryScan->summary['npm'] ?? 0,
                    'criticalFindings' => $latestLibraryScan->critical_findings,
                    'highFindings' => $latestLibraryScan->high_findings,
                    'mediumFindings' => $latestLibraryScan->medium_findings,
                    'lowFindings' => $latestLibraryScan->low_findings,
                ] : null,
            ],
            'userLocations' => $userLocations,
            'companyLocations' => $companyLocations,
            'mapCompanies' => Company::query()
                ->where('record_type', 1)
                ->where('status', true)
                ->orderBy('company_name')
                ->get(['company_code', 'company_name'])
                ->map(fn (Company $company): array => [
                    'code' => $company->company_code,
                    'name' => $company->company_name,
                ]),
            'portalLogs' => PortalLog::query()
                ->with('user:id,first_name,last_name')
                ->when(
                    ! $request->user()->hasRole('Owner'),
                    fn ($query) => $query->where('user_id', $request->user()->getKey()),
                )
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (PortalLog $log): array => [
                    'id' => $log->id,
                    'userName' => $log->user?->name ?? 'Deleted user',
                    'action' => $log->action,
                    'routeName' => $log->route_name,
                    'method' => $log->method,
                    'url' => $log->url,
                    'statusCode' => $log->status_code,
                    'ipAddress' => $log->ip_address,
                    'userAgent' => $log->user_agent,
                    'durationMs' => $log->metadata['duration_ms'] ?? null,
                    'isInertia' => (bool) ($log->metadata['inertia'] ?? false),
                    'createdAt' => $log->created_at->toIso8601String(),
                ]),
        ]);
    })->middleware('permission:dashboard.view')->name('dashboard');

    Route::post('dashboard/location', [DashboardLocationController::class, 'store'])->middleware('permission:dashboard.view')->name('dashboard.location.store');
    Route::get('dashboard/location-report', [DashboardLocationController::class, 'report'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard.location-report');
    Route::get('dashboard/activity', [PortalActivityController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard.activity');
    Route::post('dashboard/session-closing', [DashboardLocationController::class, 'markSessionClosing'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard.session-closing');
    Route::get('api/security-audits/latest', [SecurityAuditController::class, 'latest'])
        ->middleware('throttle:30,1')
        ->name('api.security-audits.latest');
    Route::post('api/security-audits/run', [SecurityAuditController::class, 'run'])
        ->middleware('throttle:3,1')
        ->name('api.security-audits.run');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('employee-hierarchy', [EmployeeHierarchyController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('employee-hierarchy.index');
        Route::get('modules', [ModuleController::class, 'index'])->middleware('permission:users.view')->name('modules.index');
        Route::post('modules', [ModuleController::class, 'store'])->middleware('permission:users.manage')->name('modules.store');
        Route::put('modules/{module}', [ModuleController::class, 'update'])->middleware('permission:users.manage')->name('modules.update');
        Route::patch('modules/{module}/status', [ModuleController::class, 'toggleStatus'])->middleware('permission:users.manage')->name('modules.status');
        Route::get('modules/{module}/download', [ModuleController::class, 'download'])->middleware('permission:users.view')->name('modules.download');
        Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->middleware('permission:users.manage')->name('modules.destroy');
        Route::get('materials', [MaterialController::class, 'index'])->middleware('permission:procurement.inventory.materials.view')->name('materials.index');
        Route::post('materials', [MaterialController::class, 'store'])->middleware('permission:procurement.inventory.materials.manage')->name('materials.store');
        Route::post('materials/import', [MaterialController::class, 'import'])->middleware('permission:procurement.inventory.materials.manage')->name('materials.import');
        Route::get('materials/import-template', [MaterialController::class, 'template'])->middleware('permission:procurement.inventory.materials.manage')->name('materials.template');
        Route::post('materials/import-api', [MaterialController::class, 'queueApiImport'])->middleware('permission:procurement.inventory.materials.manage')->name('materials.import-api');
        Route::patch('materials/bulk', [MaterialController::class, 'bulkAction'])->middleware('permission:procurement.inventory.materials.manage')->name('materials.bulk');
        Route::put('materials/{material}', [MaterialController::class, 'update'])->middleware('permission:procurement.inventory.materials.manage')->name('materials.update');
        Route::patch('materials/{material}/status', [MaterialController::class, 'toggleStatus'])->middleware('permission:procurement.inventory.materials.manage')->name('materials.status');
        Route::delete('materials/{material}', [MaterialController::class, 'destroy'])->middleware('permission:procurement.inventory.materials.manage')->name('materials.destroy');
        Route::get('material-stocks', [MaterialStockController::class, 'index'])->middleware('permission:procurement.inventory.material_stock.view')->name('material-stocks.index');
        Route::post('material-stocks', [MaterialStockController::class, 'store'])->middleware('permission:procurement.inventory.material_stock.manage')->name('material-stocks.store');
        Route::patch('material-stocks/bulk', [MaterialStockController::class, 'bulkAction'])->middleware('permission:procurement.inventory.material_stock.manage')->name('material-stocks.bulk');
        Route::put('material-stocks/{materialStock}', [MaterialStockController::class, 'update'])->middleware('permission:procurement.inventory.material_stock.manage')->name('material-stocks.update');
        Route::patch('material-stocks/{materialStock}/status', [MaterialStockController::class, 'toggleStatus'])->middleware('permission:procurement.inventory.material_stock.manage')->name('material-stocks.status');
        Route::delete('material-stocks/{materialStock}', [MaterialStockController::class, 'destroy'])->middleware('permission:procurement.inventory.material_stock.manage')->name('material-stocks.destroy');
        Route::get('api-integrations', [ApiIntegrationController::class, 'index'])->middleware('permission:procurement.api.material.view|procurement.api.production.view|procurement.api.quality.view|procurement.api.stock.view|procurement.api.consumption.view|procurement.api.vendor.view|sales_distribution.customers.view|users.view')->name('api-integrations.index');
        Route::post('api-integrations', [ApiIntegrationController::class, 'store'])->middleware('permission:procurement.api.material.manage|procurement.api.production.manage|procurement.api.quality.manage|procurement.api.stock.manage|procurement.api.consumption.manage|procurement.api.vendor.manage|users.manage')->name('api-integrations.store');
        Route::put('api-integrations/{apiIntegration}', [ApiIntegrationController::class, 'update'])->middleware('api.integration:manage')->name('api-integrations.update');
        Route::patch('api-integrations/{apiIntegration}/status', [ApiIntegrationController::class, 'toggleStatus'])->middleware('api.integration:manage')->name('api-integrations.status');
        Route::post('api-integrations/{apiIntegration}/run', [ApiIntegrationController::class, 'run'])->middleware('api.integration:run')->name('api-integrations.run');
        Route::post('api-integrations/run-procurement', [ApiIntegrationController::class, 'runProcurement'])->middleware('permission:procurement.api.material.run|users.manage')->name('api-integrations.run-procurement');
        Route::delete('api-integrations/{apiIntegration}/logs/{log}', [ApiIntegrationController::class, 'destroyLog'])->middleware('api.integration:manage')->name('api-integrations.logs.destroy');
        Route::delete('api-integrations/{apiIntegration}/logs', [ApiIntegrationController::class, 'destroyLogs'])->middleware('api.integration:manage')->name('api-integrations.logs.destroy-all');
        Route::delete('api-integrations/{apiIntegration}', [ApiIntegrationController::class, 'destroy'])->middleware('api.integration:manage')->name('api-integrations.destroy');
        Route::get('companies', [CompanyController::class, 'index'])->middleware('permission:users.view')->name('companies.index');
        Route::get('companies/hierarchy', [CompanyController::class, 'hierarchy'])->middleware('permission:users.view')->name('companies.hierarchy');
        Route::post('companies', [CompanyController::class, 'store'])->middleware('permission:users.manage')->name('companies.store');
        Route::patch('companies/bulk', [CompanyController::class, 'bulkAction'])->middleware('permission:users.manage')->name('companies.bulk');
        Route::put('companies/{company}', [CompanyController::class, 'update'])->middleware('permission:users.manage')->name('companies.update');
        Route::patch('companies/{company}/status', [CompanyController::class, 'toggleStatus'])->middleware('permission:users.manage')->name('companies.status');
        Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->middleware('permission:users.manage')->name('companies.destroy');
        Route::get('designation-levels', [DesignationLevelController::class, 'index'])->middleware('permission:users.view')->name('designation-levels.index');
        Route::post('designation-levels', [DesignationLevelController::class, 'store'])->middleware('permission:users.manage')->name('designation-levels.store');
        Route::patch('designation-levels/bulk', [DesignationLevelController::class, 'bulkAction'])->middleware('permission:users.manage')->name('designation-levels.bulk');
        Route::put('designation-levels/{designationLevel}', [DesignationLevelController::class, 'update'])->middleware('permission:users.manage')->name('designation-levels.update');
        Route::patch('designation-levels/{designationLevel}/status', [DesignationLevelController::class, 'toggleStatus'])->middleware('permission:users.manage')->name('designation-levels.status');
        Route::delete('designation-levels/{designationLevel}', [DesignationLevelController::class, 'destroy'])->middleware('permission:users.manage')->name('designation-levels.destroy');

        Route::get('designations', [DesignationController::class, 'index'])->middleware('permission:users.view')->name('designations.index');
        Route::post('designations', [DesignationController::class, 'store'])->middleware('permission:users.manage')->name('designations.store');
        Route::patch('designations/bulk', [DesignationController::class, 'bulkAction'])->middleware('permission:users.manage')->name('designations.bulk');
        Route::put('designations/{designation}', [DesignationController::class, 'update'])->middleware('permission:users.manage')->name('designations.update');
        Route::patch('designations/{designation}/status', [DesignationController::class, 'toggleStatus'])->middleware('permission:users.manage')->name('designations.status');
        Route::delete('designations/{designation}', [DesignationController::class, 'destroy'])->middleware('permission:users.manage')->name('designations.destroy');

        Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:users.view')->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:users.manage')->name('departments.store');
        Route::patch('departments/bulk', [DepartmentController::class, 'bulkAction'])->middleware('permission:users.manage')->name('departments.bulk');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:users.manage')->name('departments.update');
        Route::patch('departments/{department}/status', [DepartmentController::class, 'toggleStatus'])->middleware('permission:users.manage')->name('departments.status');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:users.manage')->name('departments.destroy');

        Route::get('user-types', [UserTypeController::class, 'index'])->middleware('permission:users.view')->name('user-types.index');
        Route::post('user-types', [UserTypeController::class, 'store'])->middleware('permission:users.manage')->name('user-types.store');
        Route::patch('user-types/bulk', [UserTypeController::class, 'bulkAction'])->middleware('permission:users.manage')->name('user-types.bulk');
        Route::put('user-types/{userType}', [UserTypeController::class, 'update'])->middleware('permission:users.manage')->name('user-types.update');
        Route::patch('user-types/{userType}/status', [UserTypeController::class, 'toggleStatus'])->middleware('permission:users.manage')->name('user-types.status');
        Route::delete('user-types/{userType}', [UserTypeController::class, 'destroy'])->middleware('permission:users.manage')->name('user-types.destroy');

        Route::get('user-master', [UserMasterController::class, 'index'])->middleware('permission:users.view')->name('user-master.index');
        Route::get('user-master/location-options', [UserMasterController::class, 'locationOptions'])->middleware('permission:users.manage')->name('user-master.location-options');
        Route::get('user-master/location-coordinates', [UserMasterController::class, 'locationCoordinates'])->middleware('permission:users.manage')->name('user-master.location-coordinates');
        Route::post('user-master', [UserMasterController::class, 'store'])->middleware('permission:users.manage')->name('user-master.store');
        Route::post('user-master/import', [UserMasterController::class, 'importExcel'])->middleware('permission:users.manage')->name('user-master.import');
        Route::get('user-master/import-template', [UserMasterController::class, 'downloadImportTemplate'])->middleware('permission:users.manage')->name('user-master.import-template');
        Route::patch('user-master/bulk', [UserMasterController::class, 'bulkAction'])->middleware('permission:users.manage')->name('user-master.bulk');
        Route::patch('user-master/{user}/status', [UserMasterController::class, 'toggleStatus'])->middleware('permission:users.manage')->name('user-master.status');
        Route::post('user-master/{user}/otp/send', [UserMasterController::class, 'sendContactOtp'])->middleware('permission:users.manage')->name('user-master.otp.send');
        Route::post('user-master/{user}/otp/verify', [UserMasterController::class, 'verifyContactOtp'])->middleware('permission:users.manage')->name('user-master.otp.verify');
        Route::put('user-master/{user}', [UserMasterController::class, 'update'])->middleware('permission:users.manage')->name('user-master.update');
        Route::delete('user-master/{user}', [UserMasterController::class, 'destroy'])->middleware('permission:users.manage')->name('user-master.destroy');

        Route::get('users', [UserRoleController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::put('users/{user}/roles', [UserRoleController::class, 'update'])->middleware('permission:users.manage')->name('users.roles.update');

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:roles.manage')->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage')->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.manage')->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage')->name('roles.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

$moduleRouteFiles = [
    base_path('Modules/GroupOverview/routes/web.php'),
    base_path('Modules/Procurement/routes/web.php'),
    base_path('Modules/ProductionCapacity/routes/web.php'),
    base_path('Modules/Sales/routes/web.php'),
    base_path('Modules/SalesDistribution/routes/web.php'),
    base_path('Modules/CorporateChat/routes/web.php'),
    base_path('Modules/Support/routes/web.php'),
];

foreach ($moduleRouteFiles as $moduleRouteFile) {
    if (is_file($moduleRouteFile)) {
        require $moduleRouteFile;
    }
}
