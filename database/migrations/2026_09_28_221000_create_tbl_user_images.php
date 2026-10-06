<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_user_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('tbl_users')->cascadeOnDelete();
            $table->enum('image_type', ['profile', 'cover']);
            $table->string('file_path', 500);
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unique(['user_id', 'image_type']);
        });

        DB::table('tbl_users')->select('id', 'profile_image_path', 'cover_image_path')->orderBy('id')->each(function ($user) {
            $now = now();
            if ($user->profile_image_path) {
                DB::table('tbl_user_images')->insert(['user_id'=>$user->id,'image_type'=>'profile','file_path'=>$user->profile_image_path,'created_at'=>$now,'updated_at'=>$now]);
            }
            if ($user->cover_image_path) {
                DB::table('tbl_user_images')->insert(['user_id'=>$user->id,'image_type'=>'cover','file_path'=>$user->cover_image_path,'created_at'=>$now,'updated_at'=>$now]);
            }
        });

        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropColumn(['profile_image_path', 'cover_image_path']);
        });
    }

    public function down(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->string('profile_image_path', 500)->nullable()->after('photo_id');
            $table->string('cover_image_path', 500)->nullable()->after('profile_image_path');
        });
        DB::table('tbl_user_images')->orderBy('id')->each(function ($image) {
            DB::table('tbl_users')->where('id', $image->user_id)->update([$image->image_type.'_image_path' => $image->file_path]);
        });
        Schema::dropIfExists('tbl_user_images');
    }
};
