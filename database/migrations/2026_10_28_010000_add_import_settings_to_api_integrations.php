<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_api_integrations', function (Blueprint $table): void {
            $table->string('import_mode', 20)->default('direct')->after('target_table');
            $table->unsignedSmallInteger('chunk_size')->default(100)->after('import_mode');
            $table->unsignedInteger('chunk_offset')->default(0)->after('chunk_size');
        });

        DB::table('tbl_api_integrations')
            ->whereIn(DB::raw('LOWER(code)'), ['cust', 'customer'])
            ->update(['import_mode' => 'chunk_all', 'chunk_size' => 100, 'chunk_offset' => 0]);
    }

    public function down(): void
    {
        Schema::table('tbl_api_integrations', function (Blueprint $table): void {
            $table->dropColumn(['import_mode', 'chunk_size', 'chunk_offset']);
        });
    }
};
