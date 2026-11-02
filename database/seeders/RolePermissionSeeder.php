<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'group_overview.dashboard.view',
            'sales_distribution.dashboard.view',
            'sales_distribution.customers.view',
            'sales_distribution.customers.manage',
            'sales_distribution.pending_sales.view',
            'sales_distribution.pending_sales.manage',
            'sales_distribution.daily_sales.view',
            'sales_distribution.daily_sales.manage',
            'sales_distribution.customer_sales_summary.view',
            'sales_distribution.customer_sales_summary.manage',
            'sales_distribution.product_targets.view',
            'sales_distribution.product_targets.manage',
            'users.view',
            'users.manage',
            'roles.view',
            'roles.manage',
            'procurement.dashboard.view',
            'procurement.rm_planning.view',
            'procurement.planning_decisions.view',
            'procurement.planning_decisions.create',
            'procurement.planning_decisions.edit',
            'procurement.planning_decisions.delete',
            'procurement.materials_balance.view',
            'procurement.materials_balance.edit',
            'procurement.consumption.view',
            'procurement.consumption.create',
            'procurement.consumption.edit',
            'procurement.consumption.delete',
            'procurement.rm_inventories.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('Owner', 'web')->givePermissionTo($permissions);
        Role::findOrCreate('Administrator', 'web')->givePermissionTo($permissions);
        Role::findOrCreate('Viewer', 'web')->givePermissionTo('dashboard.view');
        Role::findOrCreate('Super Admin', 'web')->givePermissionTo([
            'procurement.rm_planning.view',
            'procurement.planning_decisions.view',
            'procurement.planning_decisions.create',
            'procurement.planning_decisions.edit',
            'procurement.planning_decisions.delete',
            'procurement.planning_decisions.unlock',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
