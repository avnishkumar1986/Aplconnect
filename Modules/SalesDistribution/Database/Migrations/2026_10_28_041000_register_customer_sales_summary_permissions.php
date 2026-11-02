<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['sales_distribution.customer_sales_summary.view', 'sales_distribution.customer_sales_summary.manage'] as $name) {
            DB::table('tbl_permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['status' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }
        $permissionIds = DB::table('tbl_permissions')->whereIn('name', ['sales_distribution.customer_sales_summary.view', 'sales_distribution.customer_sales_summary.manage'])->pluck('id');
        $roleIds = DB::table('tbl_roles')->whereIn('name', ['Owner', 'Administrator', 'Super Admin'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('tbl_role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId], ['status' => true, 'updated_at' => $now, 'created_at' => $now]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('tbl_permissions')->whereIn('name', ['sales_distribution.customer_sales_summary.view', 'sales_distribution.customer_sales_summary.manage'])->pluck('id');
        DB::table('tbl_role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('tbl_permissions')->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
