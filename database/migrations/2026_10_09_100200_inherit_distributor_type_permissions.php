<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $sourceId = DB::table('permissions')->where('name', "distributors.{$action}")->where('guard_name', 'web')->value('id');
            $targetId = DB::table('permissions')->where('name', "distributor-types.{$action}")->where('guard_name', 'web')->value('id');
            if (! $sourceId || ! $targetId) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permission assignments are intentionally retained to avoid removing
        // administrator choices made after this migration was applied.
    }
};
