<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_integrations', function (Blueprint $table) {
            $table->string('username_env_key', 100)->nullable()->after('credential_env_key');
            $table->string('password_env_key', 100)->nullable()->after('username_env_key');
        });
        DB::table('api_integrations')->update(['auth_type' => 'basic', 'username_env_key' => 'API_AUTH_USERNAME', 'password_env_key' => 'API_AUTH_PASSWORD', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('api_integrations', fn (Blueprint $table) => $table->dropColumn(['username_env_key', 'password_env_key']));
    }
};
