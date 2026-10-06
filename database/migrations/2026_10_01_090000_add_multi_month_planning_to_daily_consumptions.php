<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->uuid('planning_batch')->nullable()->after('submission_token')->index();
            $table->unsignedSmallInteger('planning_sequence')->default(1)->after('planning_batch');
            $table->unsignedSmallInteger('planning_months')->default(1)->after('planning_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_daily_consumptions', function (Blueprint $table) {
            $table->dropIndex(['planning_batch']);
            $table->dropColumn(['planning_batch', 'planning_sequence', 'planning_months']);
        });
    }
};
