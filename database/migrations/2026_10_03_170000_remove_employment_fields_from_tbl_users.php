<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'fk_users_company_code',
            'tbl_users_sitting_location_id_foreign',
            'tbl_users_department_id_foreign',
            'tbl_users_designation_id_foreign',
            'tbl_users_reporting_to_user_id_foreign',
        ] as $constraint) {
            $exists = DB::selectOne("SELECT COUNT(*) count FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND CONSTRAINT_NAME = ?", [$constraint]);
            if ((int) $exists->count > 0) DB::statement("ALTER TABLE tbl_users DROP FOREIGN KEY `$constraint`");
        }

        Schema::table('tbl_users', fn ($table) => $table->dropColumn([
            'designation_id',
            'company_id',
            'sitting_location_id',
            'department_id',
            'reporting_to_user_id',
        ]));
    }

    public function down(): void
    {
        Schema::table('tbl_users', function ($table) {
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->string('company_id', 30)->collation('utf8mb4_general_ci')->nullable();
            $table->integer('sitting_location_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('reporting_to_user_id')->nullable();
        });
    }
};
