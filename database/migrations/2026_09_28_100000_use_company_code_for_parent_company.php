<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE tbl_company DROP FOREIGN KEY fk_company_parent');
        DB::statement('ALTER TABLE tbl_company MODIFY parent_company_id VARCHAR(30) NULL COMMENT "Parent tbl_company.company_code for a plant; NULL for a company"');
        DB::statement('UPDATE tbl_company child JOIN tbl_company parent ON parent.id = CAST(child.parent_company_id AS UNSIGNED) SET child.parent_company_id = parent.company_code WHERE child.parent_company_id IS NOT NULL');
        DB::statement('ALTER TABLE tbl_company ADD UNIQUE KEY uq_company_code (company_code)');
        DB::statement('ALTER TABLE tbl_company ADD CONSTRAINT fk_company_parent_code FOREIGN KEY (parent_company_id) REFERENCES tbl_company (company_code) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tbl_company DROP FOREIGN KEY fk_company_parent_code');
        DB::statement('UPDATE tbl_company child JOIN tbl_company parent ON parent.company_code = child.parent_company_id SET child.parent_company_id = parent.id WHERE child.parent_company_id IS NOT NULL');
        DB::statement('ALTER TABLE tbl_company MODIFY parent_company_id INT(11) NULL COMMENT "Parent tbl_company.id for a plant; NULL for a company"');
        DB::statement('ALTER TABLE tbl_company DROP INDEX uq_company_code');
        DB::statement('ALTER TABLE tbl_company ADD CONSTRAINT fk_company_parent FOREIGN KEY (parent_company_id) REFERENCES tbl_company (id) ON DELETE SET NULL ON UPDATE CASCADE');
    }
};
