<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_distributor_profiles', function (Blueprint $table) {
            $table->string('distributor_tier_code', 30)->nullable()->after('distributor_category')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_distributor_profiles', function (Blueprint $table) {
            $table->dropIndex(['distributor_tier_code']);
            $table->dropColumn('distributor_tier_code');
        });
    }
};
