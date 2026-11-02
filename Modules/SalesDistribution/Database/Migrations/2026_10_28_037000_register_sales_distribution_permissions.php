<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'sales_distribution.dashboard.view',
        'sales_distribution.customers.view',
        'sales_distribution.customers.manage',
        'sales_distribution.pending_sales.view',
        'sales_distribution.pending_sales.manage',
        'sales_distribution.daily_sales.view',
        'sales_distribution.daily_sales.manage',
    ];

    public function up(): void
    {
        $now = now();
        $permissionsTable = config('permission.table_names.permissions');
        $rolesTable = config('permission.table_names.roles');
        $rolePermissionsTable = config('permission.table_names.role_has_permissions');

        foreach (self::PERMISSIONS as $name) {
            DB::table($permissionsTable)->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['status' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $permissionIds = DB::table($permissionsTable)
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table($rolesTable)
            ->whereIn('name', ['Owner', 'Administrator', 'Super Admin'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table($rolePermissionsTable)->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['status' => true, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))
            ->whereIn('name', array_slice(self::PERMISSIONS, 1))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
