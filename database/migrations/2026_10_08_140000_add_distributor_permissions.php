<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS = ['view', 'create', 'edit', 'delete'];

    public function up(): void
    {
        $now = now();

        foreach (self::ACTIONS as $action) {
            $distributorPermission = "distributors.{$action}";
            DB::table('permissions')->insertOrIgnore([
                'name' => $distributorPermission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sourcePermissionId = DB::table('permissions')->where('name', "users.{$action}")->where('guard_name', 'web')->value('id');
            $distributorPermissionId = DB::table('permissions')->where('name', $distributorPermission)->where('guard_name', 'web')->value('id');

            if ($sourcePermissionId && $distributorPermissionId) {
                DB::table('role_has_permissions')
                    ->where('permission_id', $sourcePermissionId)
                    ->pluck('role_id')
                    ->each(fn ($roleId) => DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $distributorPermissionId,
                        'role_id' => $roleId,
                    ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_map(fn ($action) => "distributors.{$action}", self::ACTIONS))
            ->delete();
    }
};
