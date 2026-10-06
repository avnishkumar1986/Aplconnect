<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->string('profile_image_path', 500)->nullable()->after('photo_id');
            $table->string('cover_image_path', 500)->nullable()->after('profile_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropColumn(['profile_image_path', 'cover_image_path']);
        });
    }
};
