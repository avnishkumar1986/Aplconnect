<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_contacts', function (Blueprint $table) {
            $table->string('employee_id', 50)->default('0')->after('id');
            $table->integer('company_code')->default(0)->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_contacts', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'company_code']);
        });
    }
};
