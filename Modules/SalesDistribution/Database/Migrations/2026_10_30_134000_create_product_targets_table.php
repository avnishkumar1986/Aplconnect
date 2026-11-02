<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_product_targets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id')->nullable()->unique();
            $table->string('customer_name', 255);
            $table->string('customer_code', 50)->index();
            $table->string('state', 100)->nullable()->index();
            $table->string('city', 100)->nullable();
            $table->string('month', 20)->nullable();
            $table->string('fiscal_year', 10)->index();
            foreach (['l3_code', 'l4_code', 'l5_code', 'l6_code'] as $column) {
                $table->string($column, 50)->nullable()->index();
            }
            foreach ($this->productColumns() as $column) {
                $table->decimal($column, 20, 2)->default(0);
            }
            $table->decimal('total_sale', 20, 2)->default(0);
            $table->boolean('status')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->index(['fiscal_year', 'customer_code'], 'product_targets_year_customer_index');
        });

        $now = now();
        foreach (['sales_distribution.product_targets.view', 'sales_distribution.product_targets.manage'] as $name) {
            DB::table('tbl_permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['status' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
        $permissionIds = DB::table('tbl_permissions')->whereIn('name', [
            'sales_distribution.product_targets.view',
            'sales_distribution.product_targets.manage',
        ])->pluck('id');
        $roleIds = DB::table('tbl_roles')->whereIn('name', ['Owner', 'Administrator', 'Super Admin'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('tbl_role_has_permissions')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['status' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('tbl_permissions')->whereIn('name', [
            'sales_distribution.product_targets.view',
            'sales_distribution.product_targets.manage',
        ])->pluck('id');
        DB::table('tbl_role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('tbl_permissions')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('tbl_product_targets');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function productColumns(): array
    {
        return [
            'product1_pvc_pipes', 'product2_colum_pipes', 'product3_casing_pipe', 'product4_hdpe_pipe',
            'product5_astm_pipe', 'product6_cpvc_pipe', 'product7_ppr_pipe', 'product8_sprk_pipe',
            'product9_swr_pipe', 'product10_agri_swr_fit', 'product11_hdpe_fit', 'product12_astm_fit',
            'product13_sprk_fit', 'product14_cpvc_fit', 'product15_ppr_fit', 'product16_mdpe_fit',
            'product17_solvent', 'product18_bathfit', 'product19_watertank', 'product20_mdpe_pipe',
            'product21_plumbing_pipe', 'product22_opvc', 'product23_dwc_pipe', 'product24_other_products',
        ];
    }
};
