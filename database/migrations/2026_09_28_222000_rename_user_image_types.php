<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_user_images MODIFY image_type ENUM('profile','cover','profileimg','wallimage') NOT NULL");
        DB::table('tbl_user_images')->where('image_type', 'profile')->update(['image_type' => 'profileimg']);
        DB::table('tbl_user_images')->where('image_type', 'cover')->update(['image_type' => 'wallimage']);
        DB::statement("ALTER TABLE tbl_user_images MODIFY image_type ENUM('profileimg','wallimage') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tbl_user_images MODIFY image_type ENUM('profileimg','wallimage','profile','cover') NOT NULL");
        DB::table('tbl_user_images')->where('image_type', 'profileimg')->update(['image_type' => 'profile']);
        DB::table('tbl_user_images')->where('image_type', 'wallimage')->update(['image_type' => 'cover']);
        DB::statement("ALTER TABLE tbl_user_images MODIFY image_type ENUM('profile','cover') NOT NULL");
    }
};
