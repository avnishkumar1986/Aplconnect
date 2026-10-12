<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('tbl_modules')->updateOrInsert(
            ['slug' => 'sales'],
            [
                'name' => 'Sales',
                'version' => '1.0.0',
                'description' => 'Sales operations workspace for APL and KPL.',
                'original_filename' => 'Sales',
                'stored_path' => 'Modules/Sales',
                'source_type' => 'discovered',
                'mime_type' => 'application/x-directory',
                'file_size' => 0,
                'status' => 'active',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $permissions = [
            'sales.overview.view',
            'sales.orders.view', 'sales.orders.create', 'sales.orders.edit', 'sales.orders.delete',
            'sales.customers.view', 'sales.customers.create', 'sales.customers.edit', 'sales.customers.delete',
            'sales.targets.view', 'sales.targets.create', 'sales.targets.edit', 'sales.targets.delete',
            'sales.dispatches.view', 'sales.dispatches.create', 'sales.dispatches.edit', 'sales.dispatches.delete',
            'sales.reports.view',
        ];

        foreach ($permissions as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')->whereIn('name', ['Super Admin', 'Admin'])->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'like', 'sales.%')->delete();
        DB::table('tbl_modules')->where('slug', 'sales')->delete();
    }
};
