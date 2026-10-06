<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_users', 'reporting_to_user_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->foreignId('reporting_to_user_id')->nullable()->after('department_id')
                    ->constrained('tbl_users')->nullOnDelete();
            });
        }

        $users = DB::table('tbl_users')->orderBy('id')->get();
        $parents = DB::table('tbl_designations')->pluck('reports_to_designation_id', 'id');
        foreach ($users as $user) {
            $parentDesignationId = $parents[$user->designation_id] ?? null;
            $candidates = $parentDesignationId
                ? $users->where('designation_id', $parentDesignationId)->where('company_id', $user->company_id)->where('status', '1')->where('id', '!=', $user->id)
                : collect();
            $manager = $candidates->firstWhere('department_id', $user->department_id)
                ?? $candidates->firstWhere('sitting_location_id', $user->sitting_location_id)
                ?? $candidates->first();
            DB::table('tbl_users')->where('id', $user->id)->update(['reporting_to_user_id' => $manager?->id]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_users', 'reporting_to_user_id')) {
            Schema::table('tbl_users', fn (Blueprint $table) => $table->dropConstrainedForeignId('reporting_to_user_id'));
        }
    }
};
