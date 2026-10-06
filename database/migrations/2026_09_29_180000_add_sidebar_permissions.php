<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['modules.view', 'modules.create', 'modules.edit', 'modules.delete', 'theme-settings.view', 'theme-settings.edit', 'action-logs.view'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }

        $superAdminRoleId = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'web')->value('id');
        if ($superAdminRoleId) {
            DB::table('permissions')->whereIn('name', ['modules.view', 'modules.create', 'modules.edit', 'modules.delete', 'theme-settings.view', 'theme-settings.edit', 'action-logs.view'])
                ->pluck('id')->each(fn ($permissionId) => DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $superAdminRoleId]));
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['modules.view', 'modules.create', 'modules.edit', 'modules.delete', 'theme-settings.view', 'theme-settings.edit', 'action-logs.view'])->delete();
    }
};
