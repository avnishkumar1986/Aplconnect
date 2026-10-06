<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Services\RoleSessionInvalidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const HIDDEN_PERMISSION_MODULES = ['accounts', 'addresses', 'contacts', 'education'];

    public function index()
    {
        Gate::authorize('roles.view');

        return view('roles.index', [
            'roles' => Role::withCount(['permissions', 'users'])->paginate(config('app.table_page_length')),
            'companyNames' => Company::pluck('company_name', 'id'),
            'roleCompanyIds' => DB::table('role_company')->get()->groupBy('role_id')->map->pluck('company_id'),
            'departmentNames' => Department::pluck('department_name', 'id'),
            'designationNames' => Designation::pluck('designation_name', 'id'),
        ]);
    }

    public function create()
    {
        Gate::authorize('roles.create');

        return view('roles.form', $this->formData(new Role));
    }

    public function store(Request $r)
    {
        Gate::authorize('roles.create');
        $d = $this->validated($r);
        DB::transaction(function () use ($d) {
            $role = Role::create(['name' => $d['name'], 'guard_name' => 'web'] + $this->organizationMapping($d));
            $this->syncCompanies($role, $d['company_ids'] ?? []);
            $this->syncPermissions($role, $d['permissions'] ?? []);
        });

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role)
    {
        Gate::authorize('roles.edit');

        return view('roles.form', $this->formData($role));
    }

    public function update(Request $r, Role $role)
    {
        Gate::authorize('roles.edit');
        $d = $this->validated($r, $role);
        $permissionsChanged = DB::transaction(function () use ($d, $role) {
            $before = $role->permissions->pluck('name')->sort()->values();
            $role->update(['name' => $d['name']] + $this->organizationMapping($d));
            $this->syncCompanies($role, $d['company_ids'] ?? []);
            $this->syncPermissions($role, $d['permissions'] ?? []);
            $after = $role->fresh()->permissions->pluck('name')->sort()->values();

            return $before->all() !== $after->all();
        });

        if ($permissionsChanged) {
            app(RoleSessionInvalidator::class)->invalidate([$role->id]);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        Gate::authorize('roles.delete');
        abort_if($role->name === 'Super Admin', 422, 'The Super Admin role cannot be deleted.');
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    private function formData(Role $role): array
    {
        return [
            'role' => $role,
            'permissions' => Permission::where(function ($query) {
                foreach (self::HIDDEN_PERMISSION_MODULES as $module) {
                    $query->where('name', 'not like', "{$module}.%");
                }
            })->orderBy('name')->get(),
            'companies' => Company::where('record_type', 1)->where('Status', 1)->orderBy('company_name')->get(),
            'departments' => Department::where('status', 1)->orderBy('department_name')->get(),
            'designations' => Designation::where('status', 1)->orderBy('designation_name')->get(),
            'selectedCompanyIds' => $role->exists ? DB::table('role_company')->where('role_id', $role->id)->pluck('company_id')->map(fn ($id) => (string) $id)->all() : [],
        ];
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'distinct', 'exists:tbl_company,id'],
            'department_id' => ['nullable', 'exists:tbl_departments,id'],
            'designation_id' => ['nullable', 'exists:tbl_designations,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
    }

    private function organizationMapping(array $data): array
    {
        return [
            'company_id' => $data['company_ids'][0] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
        ];
    }

    private function syncPermissions(Role $role, array $permissions): void
    {
        $permissions = collect($permissions)->unique();
        $hiddenPermissions = $role->permissions
            ->pluck('name')
            ->filter(fn (string $permission) => collect(self::HIDDEN_PERMISSION_MODULES)
                ->contains(fn (string $module) => str_starts_with($permission, "{$module}.")));
        $impliedViewPermissions = $permissions
            ->filter(fn (string $permission) => preg_match('/\.(create|edit|delete)$/', $permission))
            ->map(fn (string $permission) => preg_replace('/\.(create|edit|delete)$/', '.view', $permission));

        $role->syncPermissions(
            $permissions->merge($hiddenPermissions)->merge($impliedViewPermissions)->push('dashboard.view')->unique()->values()->all()
        );
    }

    private function syncCompanies(Role $role, array $companyIds): void
    {
        DB::table('role_company')->where('role_id', $role->id)->delete();
        $rows = collect($companyIds)->unique()->map(fn ($companyId) => ['role_id' => $role->id, 'company_id' => $companyId])->all();
        if ($rows) DB::table('role_company')->insert($rows);
    }
}
