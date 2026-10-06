<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['procurement.all_materials.edit', 'procurement.all_materials.delete'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $role = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'web')->value('id');
        if ($role) DB::table('permissions')->whereIn('name', ['procurement.all_materials.edit', 'procurement.all_materials.delete'])->pluck('id')->each(
            fn ($permission) => DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role])
        );
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['procurement.all_materials.edit', 'procurement.all_materials.delete'])->delete();
    }
};
