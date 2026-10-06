<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $levelId = DB::table('tbl_designation_levels')->where('level_code', 'L1')->value('id');
        $reportsToId = DB::table('tbl_designations')->where('designation_name', 'Assistant Manager')->value('id');
        $now = now();

        foreach ([
            ['des_id' => '96', 'designation_name' => 'Supervisor'],
            ['des_id' => '97', 'designation_name' => 'Technician'],
            ['des_id' => '98', 'designation_name' => 'Operator'],
        ] as $designation) {
            DB::table('tbl_designations')->updateOrInsert(
                ['designation_name' => $designation['designation_name']],
                $designation + [
                    'designation_level_id' => $levelId,
                    'reports_to_designation_id' => $reportsToId,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        DB::table('tbl_designations')
            ->whereIn('designation_name', ['Engineer', 'Officer', 'Supervisor', 'Technician', 'Operator'])
            ->update([
                'designation_level_id' => $levelId,
                'reports_to_designation_id' => $reportsToId,
                'status' => 1,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('tbl_designations')
            ->whereIn('designation_name', ['Supervisor', 'Technician', 'Operator'])
            ->whereIn('des_id', ['96', '97', '98'])
            ->delete();
    }
};
