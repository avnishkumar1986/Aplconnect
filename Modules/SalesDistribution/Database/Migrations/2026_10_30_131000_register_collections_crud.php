<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
return new class extends Migration {
 public function up(): void { if(!Schema::hasColumn('tbl_customer_collection_transactions','status')) Schema::table('tbl_customer_collection_transactions',fn(Blueprint $t)=>$t->boolean('status')->default(true)->index()->after('synced_at')); $now=now(); $names=['sales_distribution.collections.view','sales_distribution.collections.manage']; foreach($names as $name) DB::table('tbl_permissions')->updateOrInsert(['name'=>$name,'guard_name'=>'web'],['status'=>true,'created_at'=>$now,'updated_at'=>$now]); $p=DB::table('tbl_permissions')->whereIn('name',$names)->pluck('id'); $r=DB::table('tbl_roles')->whereIn('name',['Owner','Administrator','Super Admin'])->pluck('id'); foreach($r as $rid)foreach($p as $pid)DB::table('tbl_role_has_permissions')->updateOrInsert(['permission_id'=>$pid,'role_id'=>$rid],['status'=>true,'created_at'=>$now,'updated_at'=>$now]); app(PermissionRegistrar::class)->forgetCachedPermissions(); }
 public function down(): void { $ids=DB::table('tbl_permissions')->whereIn('name',['sales_distribution.collections.view','sales_distribution.collections.manage'])->pluck('id'); DB::table('tbl_role_has_permissions')->whereIn('permission_id',$ids)->delete(); DB::table('tbl_permissions')->whereIn('id',$ids)->delete(); if(Schema::hasColumn('tbl_customer_collection_transactions','status'))Schema::table('tbl_customer_collection_transactions',fn(Blueprint $t)=>$t->dropColumn('status')); app(PermissionRegistrar::class)->forgetCachedPermissions(); }
};
