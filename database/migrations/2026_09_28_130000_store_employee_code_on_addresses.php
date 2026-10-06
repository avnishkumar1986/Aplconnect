<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tbl_addresses MODIFY employee_id VARCHAR(50) NOT NULL DEFAULT \'0\'');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tbl_addresses MODIFY employee_id INT(10) NOT NULL DEFAULT 0');
    }
};
