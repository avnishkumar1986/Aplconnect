<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_modules') || ! Schema::hasTable('tbl_permissions')) {
            return;
        }

        DB::table('tbl_modules')
            ->where('status', true)
            ->pluck('slug')
            ->each(function (string $slug): void {
                $prefix = strtolower(str_replace('-', '_', $slug));

                DB::table('tbl_permissions')
                    ->where('guard_name', 'web')
                    ->where(function ($query) use ($prefix): void {
                        $query->where('name', $prefix)
                            ->orWhere('name', 'like', $prefix.'.%');
                    })
                    ->update(['status' => true, 'updated_at' => now()]);
            });
    }

    public function down(): void
    {
        // Permission availability should not be disabled during a rollback.
    }
};
