<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_sync_runs', function (Blueprint $table) {
            $table->unsignedInteger('materials_total')->default(0)->after('records_saved');
            $table->unsignedInteger('materials_processed')->default(0)->after('materials_total');
        });
    }

    public function down(): void
    {
        Schema::table('api_sync_runs', fn (Blueprint $table) => $table->dropColumn(['materials_total', 'materials_processed']));
    }
};
