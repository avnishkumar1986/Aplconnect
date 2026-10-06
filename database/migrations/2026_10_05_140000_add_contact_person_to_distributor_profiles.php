<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_distributor_profiles', function (Blueprint $table): void {
            $table->foreignId('contact_person_user_id')->nullable()->after('distributor_category')
                ->constrained('tbl_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_distributor_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contact_person_user_id');
        });
    }
};
