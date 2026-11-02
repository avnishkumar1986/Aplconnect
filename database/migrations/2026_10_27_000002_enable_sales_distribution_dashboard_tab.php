<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_modules')
            ->where('slug', 'sales-distribution')
            ->update([
                'display_locations' => json_encode(['sidebar', 'tabs', 'dropdown']),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('tbl_modules')
            ->where('slug', 'sales-distribution')
            ->update([
                'display_locations' => json_encode(['sidebar', 'dropdown']),
                'updated_at' => now(),
            ]);
    }
};
