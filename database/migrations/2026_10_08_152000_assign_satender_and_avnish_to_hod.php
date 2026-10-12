<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hodDesignationId = DB::table('tbl_designations')->where('designation_name', 'HOD')->value('id');

        DB::table('tbl_designations')
            ->whereIn('designation_name', ['Manager', 'Assistant Manager'])
            ->update(['reports_to_designation_id' => $hodDesignationId, 'updated_at' => now()]);

        $employees = DB::table('tbl_users')
            ->whereIn('first_name', ['Satender', 'Avnish'])
            ->where('status', '1')
            ->get(['id']);

        foreach ($employees as $employee) {
            $companyId = DB::table('tbl_empdep')->where('user_id', $employee->id)->value('company_id');
            $hodUserId = DB::table('tbl_empdep')
                ->join('tbl_designations', 'tbl_empdep.designation_id', '=', 'tbl_designations.id')
                ->join('tbl_users', 'tbl_empdep.user_id', '=', 'tbl_users.id')
                ->where('tbl_designations.designation_name', 'HOD')
                ->where('tbl_empdep.company_id', $companyId)
                ->where('tbl_empdep.status', '1')
                ->where('tbl_users.status', '1')
                ->value('tbl_empdep.user_id');

            if ($hodUserId) {
                DB::table('tbl_empdep')->where('user_id', $employee->id)->update([
                    'reporting_to_user_id' => $hodUserId,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $managerId = DB::table('tbl_designations')->where('designation_name', 'Manager')->value('id');
        $dgmId = DB::table('tbl_designations')->where('designation_name', 'Dy. General Manager')->value('id');
        DB::table('tbl_designations')->where('designation_name', 'Manager')->update(['reports_to_designation_id' => $dgmId]);
        DB::table('tbl_designations')->where('designation_name', 'Assistant Manager')->update(['reports_to_designation_id' => $managerId]);
    }
};
