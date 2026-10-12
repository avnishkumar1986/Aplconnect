<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = ['distributor-types.view', 'distributor-types.create', 'distributor-types.edit', 'distributor-types.delete'];
        foreach ($permissions as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $roles = DB::table('roles')->whereIn('name', ['Super Admin', 'Admin'])->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
        foreach ($roles as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['distributor-types.view', 'distributor-types.create', 'distributor-types.edit', 'distributor-types.delete'])->delete();
    }
};
