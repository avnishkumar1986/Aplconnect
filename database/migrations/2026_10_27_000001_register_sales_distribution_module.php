<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'sales_distribution.dashboard.view';

    public function up(): void
    {
        $now = now();

        DB::table('tbl_modules')->updateOrInsert(
            ['slug' => 'sales-distribution'],
            [
                'name' => 'Sales & Distribution',
                'description' => 'Sales planning, order fulfilment and distribution operations dashboard.',
                'version' => '1.0.0',
                'source' => 'service',
                'package_path' => 'Modules/SalesDistribution',
                'original_filename' => 'SalesDistributionService.php',
                'mime_type' => 'text/x-php',
                'file_size' => 0,
                'checksum_sha256' => hash('sha256', 'Modules\\SalesDistribution\\Services\\SalesDistributionService'),
                'manifest' => json_encode([
                    'service_class' => 'Modules\\SalesDistribution\\Services\\SalesDistributionService',
                    'module_path' => 'Modules/SalesDistribution',
                    'capabilities' => ['sales performance', 'order fulfilment', 'dispatch tracking', 'regional distribution'],
                ]),
                'display_locations' => json_encode(['sidebar', 'tabs', 'dropdown']),
                'status' => true,
                'uploaded_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $permissions = config('permission.table_names.permissions');
        $roles = config('permission.table_names.roles');
        $rolePermissions = config('permission.table_names.role_has_permissions');

        DB::table($permissions)->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table($permissions)->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');
        $roleIds = DB::table($roles)->whereIn('name', ['Owner', 'Administrator', 'Super Admin'])->where('guard_name', 'web')->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table($rolePermissions)->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tbl_modules')->where('slug', 'sales-distribution')->where('source', 'service')->delete();
        DB::table(config('permission.table_names.permissions'))->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
    }
};
