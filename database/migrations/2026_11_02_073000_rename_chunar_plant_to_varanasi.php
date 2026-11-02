<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_company')
            ->where('company_code', '1900')
            ->update(['company_name' => 'Varanasi', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('tbl_company')
            ->where('company_code', '1900')
            ->update(['company_name' => 'Chunar Plant', 'updated_at' => now()]);
    }
};
