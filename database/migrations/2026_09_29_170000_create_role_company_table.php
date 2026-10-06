<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::create('role_company',function(Blueprint $table){$table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();$table->integer('company_id');$table->foreign('company_id')->references('id')->on('tbl_company')->cascadeOnDelete();$table->primary(['role_id','company_id']);}); DB::table('roles')->whereNotNull('company_id')->orderBy('id')->each(fn($role)=>DB::table('role_company')->insertOrIgnore(['role_id'=>$role->id,'company_id'=>$role->company_id])); }
 public function down():void { Schema::dropIfExists('role_company'); }
};
