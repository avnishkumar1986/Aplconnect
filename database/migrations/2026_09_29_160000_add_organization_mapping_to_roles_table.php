<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'company_id')) {
            Schema::table('roles', fn (Blueprint $table) => $table->integer('company_id')->nullable()->after('guard_name'));
        } else {
            DB::statement('ALTER TABLE roles MODIFY company_id INT NULL');
        }
        Schema::table('roles', fn (Blueprint $table) => $table->foreign('company_id')->references('id')->on('tbl_company')->nullOnDelete());

        if (! Schema::hasColumn('roles', 'department_id')) {
            Schema::table('roles', fn (Blueprint $table) => $table->foreignId('department_id')->nullable()->after('company_id')->constrained('tbl_departments')->nullOnDelete());
        }
        if (! Schema::hasColumn('roles', 'designation_id')) {
            Schema::table('roles', fn (Blueprint $table) => $table->foreignId('designation_id')->nullable()->after('department_id')->constrained('tbl_designations')->nullOnDelete());
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
