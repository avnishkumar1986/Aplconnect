<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_users', 'company_id')) return;

        DB::statement('ALTER TABLE tbl_users MODIFY company_id VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
        DB::statement('UPDATE tbl_users users JOIN tbl_company company ON company.id = CAST(users.company_id AS UNSIGNED) SET users.company_id = company.company_code');
        DB::statement('ALTER TABLE tbl_users ADD CONSTRAINT fk_users_company_code FOREIGN KEY (company_id) REFERENCES tbl_company (company_code) ON DELETE RESTRICT ON UPDATE CASCADE');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tbl_users', 'company_id')) return;

        DB::statement('ALTER TABLE tbl_users DROP FOREIGN KEY fk_users_company_code');
        DB::statement('UPDATE tbl_users users JOIN tbl_company company ON company.company_code = users.company_id SET users.company_id = company.id');
        DB::statement('ALTER TABLE tbl_users MODIFY company_id INT(10) NOT NULL');
    }
};
