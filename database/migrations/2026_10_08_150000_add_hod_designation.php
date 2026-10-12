<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $levelId = DB::table('tbl_designation_levels')
            ->where('level_code', 'L5')
            ->value('id');
        $reportsToId = DB::table('tbl_designations')
            ->where('designation_name', 'Associate VP')
            ->value('id');

        DB::table('tbl_designations')->updateOrInsert(
            ['designation_name' => 'HOD'],
            [
                'des_id' => '99',
                'designation_level_id' => $levelId,
                'reports_to_designation_id' => $reportsToId,
                'status' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('tbl_designations')
            ->where('des_id', '99')
            ->where('designation_name', 'HOD')
            ->delete();
    }
};
