<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_designations', 'reports_to_designation_id')) {
            Schema::table('tbl_designations', function (Blueprint $table) {
                $table->foreignId('reports_to_designation_id')
                    ->nullable()
                    ->after('designation_level_id')
                    ->constrained('tbl_designations')
                    ->nullOnDelete();
            });
        }

        $hierarchy = [
            'Chairman Managing Dr' => null,
            'Joint Managing Dir.' => null,
            'Associate VP' => null,
            'Business Head' => 'Associate VP',
            'Sr. General Manager' => 'Associate VP',
            'General Manager' => 'Associate VP',
            'Asst General Manager' => 'General Manager',
            'Dy. General Manager' => 'General Manager',
            'Regional Sales Mgr.' => 'General Manager',
            'Dy. RSM' => 'Dy. General Manager',
            'Sr. Manager' => 'Dy. General Manager',
            'Manager' => 'Dy. General Manager',
            'Area sales manager' => 'Dy. General Manager',
            'Dy. Manager' => 'Manager',
            'Assistant Manager' => 'Manager',
            'Sr. Executive' => 'Assistant Manager',
            'Executive' => 'Assistant Manager',
            'Sr. Officer' => 'Assistant Manager',
            'Officer' => 'Assistant Manager',
            'Sales Co-Ordinator' => 'Assistant Manager',
            'Engineer' => 'Assistant Manager',
        ];

        $ids = DB::table('tbl_designations')->pluck('id', 'designation_name');
        foreach ($hierarchy as $designation => $parent) {
            if (! isset($ids[$designation])) continue;
            DB::table('tbl_designations')->where('id', $ids[$designation])->update([
                'reports_to_designation_id' => $parent ? ($ids[$parent] ?? null) : null,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_designations', 'reports_to_designation_id')) {
            Schema::table('tbl_designations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reports_to_designation_id');
            });
        }
    }
};
