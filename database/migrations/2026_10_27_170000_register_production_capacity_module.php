<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'production_capacity.dashboard.view';

    public function up(): void
    {
        $now = now();

        DB::table('tbl_modules')->updateOrInsert(
            ['slug' => 'production-capacity'],
            [
                'name' => 'Production & Capacity',
                'description' => 'Production output, plan achievement, capacity utilization and lost-time dashboard.',
                'version' => '1.0.0',
                'source' => 'service',
                'package_path' => 'Modules/ProductionCapacity',
                'original_filename' => 'ProductionCapacityService.php',
                'mime_type' => 'text/x-php',
                'file_size' => 0,
                'checksum_sha256' => hash('sha256', 'Modules\\ProductionCapacity\\Services\\ProductionCapacityService'),
                'manifest' => json_encode([
                    'service_class' => 'Modules\\ProductionCapacity\\Services\\ProductionCapacityService',
                    'module_path' => 'Modules/ProductionCapacity',
                    'capabilities' => ['production output', 'capacity utilization', 'lost line hours', 'production scorecard'],
                ]),
                'display_locations' => json_encode(['tabs']),
                'status' => true,
                'uploaded_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('tbl_modules')->where('slug', 'production-capacity')->where('source', 'service')->delete();
        DB::table(config('permission.table_names.permissions'))->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
    }
};
