<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_modules', function (Blueprint $table) {
            $table->json('display_locations')->nullable()->after('manifest');
        });

        DB::table('tbl_modules')->whereNull('display_locations')->update([
            'display_locations' => json_encode(['sidebar']),
        ]);
    }

    public function down(): void
    {
        Schema::table('tbl_modules', function (Blueprint $table) {
            $table->dropColumn('display_locations');
        });
    }
};
