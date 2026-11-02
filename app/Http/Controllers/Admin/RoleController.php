<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\ModulePackage;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Rbac\RoleService;
use App\Services\Modules\ModuleRegistryService;
use App\Services\Modules\ModulePermissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly ModuleRegistryService $moduleRegistry,
        private readonly ModulePermissionService $modulePermissions,
    ) {}

    public function index(): Response
    {
        $activePermissionNames = $this->activePermissions()->pluck('name');

        return Inertia::render('admin/roles/index', [
            'roles' => Role::query()
                ->with([
                    'permissions' => fn ($query) => $query
                        ->whereIn('name', $activePermissionNames)
                        ->select(['id', 'name']),
                    'companies:id,company_code,company_name',
                ])
                ->withCount('users')
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'companies' => Company::query()
                ->where('status', true)
                ->where('record_type', 1)
                ->orderBy('company_name')
                ->get(['id', 'company_code', 'company_name']),
        ]);
    }

    public function create(): Response
    {
        return $this->form();
    }

    public function edit(Role $role): Response
    {
        $role->load(['permissions:id,name', 'companies:id']);

        return $this->form($role);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roleService->create($request->validated());

        return back()->with('success', 'Role created successfully.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();
        $activePermissionNames = $this->activePermissions()->pluck('name');
        $inactivePermissionNames = $role->permissions()
            ->whereNotIn('name', $activePermissionNames)
            ->pluck('name')
            ->all();

        $data['permissions'] = array_values(array_unique([
            ...($data['permissions'] ?? []),
            ...$inactivePermissionNames,
        ]));

        $this->roleService->update($role, $data);

        return back()->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->roleService->delete($role);

        return back()->with('success', 'Role deleted successfully.');
    }

    private function form(?Role $role = null): Response
    {
        $permissions = $this->activePermissions();
        $activePermissionNames = $permissions->pluck('name');

        return Inertia::render('admin/roles/form', [
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'department_id' => $role->department_id,
                'designation_id' => $role->designation_id,
                'company_ids' => $role->companies->pluck('id')->all(),
                'permissions' => $role->permissions
                    ->pluck('name')
                    ->intersect($activePermissionNames)
                    ->values()
                    ->all(),
            ] : null,
            'companies' => Company::query()->where('status', true)->where('record_type', 1)->orderBy('company_name')->get(['id', 'company_code', 'company_name']),
            'departments' => Department::query()->where('status', true)->orderBy('department_name')->get(['id', 'department_name']),
            'designations' => Designation::query()->where('status', true)->orderBy('designation_name')->get(['id', 'designation_name', 'department_id']),
            'permissions' => $permissions,
        ]);
    }

    private function activePermissions(): \Illuminate\Support\Collection
    {
        $modules = ModulePackage::query()->get();
        $activeModuleSlugs = $modules
            ->filter(fn (ModulePackage $module): bool => $module->status && $this->moduleRegistry->isInstalled($module))
            ->pluck('slug')
            ->all();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('status', true);

        foreach ($modules as $module) {
            if (! in_array($module->slug, $activeModuleSlugs, true)) {
                $prefix = $this->modulePermissions->prefix($module->slug);
                $permissions->where('name', '<>', $prefix)
                    ->where('name', 'not like', $prefix.'.%');
            }
        }

        return $permissions->orderBy('name')->get(['id', 'name']);
    }
}
