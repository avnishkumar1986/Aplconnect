<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_usertypes')->updateOrInsert(
            ['type_code' => 'SUPPLIER'],
            [
                'type_name' => 'Supplier',
                'description' => 'External supplier or vendor account',
                'status' => '1',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('tbl_usertypes')->where('type_code', 'SUPPLIER')->delete();
    }
};
