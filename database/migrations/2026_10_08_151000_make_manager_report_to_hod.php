<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hodId = DB::table('tbl_designations')
            ->where('designation_name', 'HOD')
            ->value('id');

        DB::table('tbl_designations')
            ->where('designation_name', 'Manager')
            ->update([
                'reports_to_designation_id' => $hodId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $previousParentId = DB::table('tbl_designations')
            ->where('designation_name', 'Dy. General Manager')
            ->value('id');

        DB::table('tbl_designations')
            ->where('designation_name', 'Manager')
            ->update([
                'reports_to_designation_id' => $previousParentId,
                'updated_at' => now(),
            ]);
    }
};
