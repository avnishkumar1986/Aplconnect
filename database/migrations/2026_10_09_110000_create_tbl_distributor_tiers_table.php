<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_distributor_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100)->unique();
            $table->decimal('minimum_annual_sales', 18, 2)->nullable();
            $table->decimal('maximum_discount_percent', 5, 2)->nullable();
            $table->string('description')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('tbl_distributor_tiers')->insert([
            ['code' => 'silver', 'name' => 'Silver', 'minimum_annual_sales' => 0, 'maximum_discount_percent' => 3, 'description' => 'Entry-level distributor partnership tier.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'gold', 'name' => 'Gold', 'minimum_annual_sales' => 5000000, 'maximum_discount_percent' => 5, 'description' => 'Growth tier for established distributor partners.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'diamond', 'name' => 'Diamond', 'minimum_annual_sales' => 15000000, 'maximum_discount_percent' => 8, 'description' => 'Premium tier for top-performing distributor partners.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $permissions = ['distributor-tiers.view', 'distributor-tiers.create', 'distributor-tiers.edit', 'distributor-tiers.delete'];
        foreach ($permissions as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $sourceId = DB::table('permissions')->where('name', "distributors.{$action}")->where('guard_name', 'web')->value('id');
            $targetId = DB::table('permissions')->where('name', "distributor-tiers.{$action}")->where('guard_name', 'web')->value('id');
            if (! $sourceId || ! $targetId) continue;
            foreach (DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $targetId, 'role_id' => $roleId]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'like', 'distributor-tiers.%')->delete();
        Schema::dropIfExists('tbl_distributor_tiers');
    }
};
