<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_users', function (Blueprint $table): void {
            $table->string('business_name', 255)->nullable()->after('last_name');
            $table->string('legal_business_name', 255)->nullable()->after('business_name');
            $table->string('distributor_category', 30)->nullable()->after('legal_business_name');
        });

        if (Schema::hasTable('tbl_distributor_profiles')) {
            DB::table('tbl_users as users')
                ->join('tbl_distributor_profiles as profile', 'profile.user_id', '=', 'users.id')
                ->update([
                    'users.business_name' => DB::raw('profile.business_name'),
                    'users.legal_business_name' => DB::raw('profile.legal_business_name'),
                    'users.distributor_category' => DB::raw('profile.distributor_category'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tbl_users', function (Blueprint $table): void {
            $table->dropColumn(['business_name', 'legal_business_name', 'distributor_category']);
        });
    }
};
