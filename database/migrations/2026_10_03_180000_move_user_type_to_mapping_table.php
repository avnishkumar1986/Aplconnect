<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_user_type_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('tbl_users')->cascadeOnDelete();
            $table->string('type_code', 30);
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->foreign('type_code')->references('type_code')->on('tbl_usertypes')->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::table('tbl_users')->select('id', 'user_type', 'created_at', 'updated_at', 'created_by', 'updated_by')
            ->orderBy('id')->each(function ($user): void {
                DB::table('tbl_user_type_map')->insert([
                    'user_id' => $user->id,
                    'type_code' => $user->user_type,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'created_by' => $user->created_by,
                    'updated_by' => $user->updated_by,
                ]);
            });

        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropForeign(['user_type']);
            $table->dropColumn('user_type');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_users', fn (Blueprint $table) => $table->string('user_type', 30)->nullable()->after('id'));
        DB::table('tbl_user_type_map')->orderBy('user_id')->each(fn ($map) =>
            DB::table('tbl_users')->where('id', $map->user_id)->update(['user_type' => $map->type_code])
        );
        Schema::dropIfExists('tbl_user_type_map');
    }
};
