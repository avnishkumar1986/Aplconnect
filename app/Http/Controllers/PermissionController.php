<?php

namespace App\Http\Controllers;

use App\Services\RoleSessionInvalidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        Gate::authorize('permissions.view');

        return view('permissions.index', ['permissions' => Permission::withCount('roles')->orderBy('name')->paginate(config('app.table_page_length'))]);
    }

    public function store(Request $r)
    {
        Gate::authorize('permissions.create');
        $d = $r->validate(['name' => 'required|max:125|unique:permissions,name']);
        Permission::create(['name' => $d['name'], 'guard_name' => 'web']);

        return back()->with('success', 'Permission created.');
    }

    public function update(Request $r, Permission $permission)
    {
        Gate::authorize('permissions.edit');
        $d = $r->validate(['name' => ['required', 'max:125', Rule::unique('permissions')->ignore($permission)]]);
        $roleIds = $permission->roles()->pluck('roles.id');
        $changed = $permission->name !== $d['name'];
        $permission->update($d);
        if ($changed) app(RoleSessionInvalidator::class)->invalidate($roleIds);

        return back()->with('success', 'Permission updated.');
    }

    public function destroy(Permission $permission)
    {
        Gate::authorize('permissions.delete');
        $roleIds = $permission->roles()->pluck('roles.id');
        $permission->delete();
        app(RoleSessionInvalidator::class)->invalidate($roleIds);

        return back()->with('success', 'Permission deleted.');
    }
}
