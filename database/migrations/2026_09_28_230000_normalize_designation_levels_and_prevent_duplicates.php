<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_designation_levels')->where('level_code', 'L1')->update(['level_name' => 'Level 1']);
        DB::table('tbl_designation_levels')->where('level_code', 'L2')->update(['level_name' => 'Level 2']);
        DB::table('tbl_designation_levels')->where('level_code', 'L3')->update(['level_name' => 'Level 3']);
        DB::table('tbl_designation_levels')->where('level_code', 'L4')->update(['level_name' => 'Level 4']);
        DB::table('tbl_designation_levels')->where('level_code', 'L5')->update(['level_name' => 'Level 5']);
        DB::table('tbl_designation_levels')->where('level_code', 'L6')->update(['level_name' => 'Level 6']);

        $mapping = [
            'L6' => ['Chairman Managing Dr', 'Associate VP', 'Joint Managing Dir.'],
            'L5' => ['Sr. General Manager', 'General Manager', 'Business Head'],
            'L4' => ['Asst General Manager', 'Dy. General Manager', 'Regional Sales Mgr.'],
            'L3' => ['Dy. RSM', 'Sr. Manager', 'Manager', 'Area sales manager'],
            'L2' => ['Dy. Manager', 'Assistant Manager'],
            'L1' => ['Sr. Executive', 'Executive', 'Sr. Officer', 'Officer', 'Sales Co-Ordinator', 'Engineer'],
        ];

        foreach ($mapping as $levelCode => $designations) {
            $levelId = DB::table('tbl_designation_levels')->where('level_code', $levelCode)->value('id');
            DB::table('tbl_designations')
                ->whereIn('designation_name', $designations)
                ->update(['designation_level_id' => $levelId]);
        }

        if (! $this->hasIndex('tbl_designations', 'uq_tbl_designations_name')) {
            DB::statement('ALTER TABLE tbl_designations ADD CONSTRAINT uq_tbl_designations_name UNIQUE (designation_name)');
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('tbl_designations', 'uq_tbl_designations_name')) {
            DB::statement('ALTER TABLE tbl_designations DROP INDEX uq_tbl_designations_name');
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) return false;

        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains(fn ($row) => $row->Key_name === $index);
    }
};
