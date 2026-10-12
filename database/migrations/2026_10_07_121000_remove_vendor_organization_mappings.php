<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')->whereRaw('LOWER(name) = ?', ['vendor'])->pluck('id');

        DB::table('role_company')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->update([
            'company_id' => null,
            'department_id' => null,
            'designation_id' => null,
        ]);
    }

    public function down(): void
    {
        // Organization mappings cannot be reconstructed safely.
    }
};
