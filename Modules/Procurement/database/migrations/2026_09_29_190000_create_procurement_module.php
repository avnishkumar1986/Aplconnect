<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'procurement.overview.view',
        'procurement.overview.all_companies',
        'procurement.all_materials.view',
        'procurement.all_materials.all_companies',
        'procurement.purchases.view',
        'procurement.purchases.create',
        'procurement.purchases.all_companies',
        'procurement.daily_consumption.view',
        'procurement.daily_consumption.create',
        'procurement.daily_consumption.all_companies',
    ];

    public function up(): void
    {
        Schema::create('procurement_purchases', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('plant_id');
            $table->string('vendor_name');
            $table->string('material', 40);
            $table->date('purchase_date');
            $table->decimal('quantity_mt', 14, 3);
            $table->date('expected_delivery_date');
            $table->string('reference', 100)->nullable();
            $table->string('remarks', 1000)->nullable();
            $table->boolean('status')->default(true);
            $table->uuid('submission_token')->unique();
            $table->foreignId('created_by')->nullable()->constrained('tbl_login')->nullOnDelete();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('tbl_company')->restrictOnDelete();
            $table->foreign('plant_id')->references('id')->on('tbl_company')->restrictOnDelete();
            $table->index(['company_id', 'plant_id', 'purchase_date'], 'procurement_purchase_scope_date');
        });

        Schema::create('procurement_daily_consumptions', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('plant_id');
            $table->date('consumption_date');
            $table->string('material', 40);
            $table->decimal('quantity_mt', 14, 3);
            $table->string('remarks', 1000)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('superseded_at')->nullable();
            $table->uuid('submission_token')->unique();
            $table->foreignId('created_by')->nullable()->constrained('tbl_login')->nullOnDelete();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('tbl_company')->restrictOnDelete();
            $table->foreign('plant_id')->references('id')->on('tbl_company')->restrictOnDelete();
            $table->index(['company_id', 'plant_id', 'consumption_date'], 'procurement_consumption_scope_date');
        });

        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }

        $superAdminRoleId = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'web')->value('id');
        if ($superAdminRoleId) {
            DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id')->each(
                fn ($permissionId) => DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $superAdminRoleId])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_daily_consumptions');
        Schema::dropIfExists('procurement_purchases');
        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->delete();
    }
};
