<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->string('user_type', 30)->nullable()->after('id');
        });

        $employeeCode = DB::table('tbl_usertypes')->where('type_name', 'Employee')->value('type_code')
            ?? DB::table('tbl_usertypes')->where('status', '1')->value('type_code');
        if ($employeeCode) DB::table('tbl_users')->whereNull('user_type')->update(['user_type' => $employeeCode]);

        DB::statement('ALTER TABLE tbl_users MODIFY user_type VARCHAR(30) NOT NULL');
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->foreign('user_type')->references('type_code')->on('tbl_usertypes')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropForeign(['user_type']);
            $table->dropColumn('user_type');
        });
    }
};
