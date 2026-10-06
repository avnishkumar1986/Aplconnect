<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_integrations')->where('code', 'quality')->update(['verify_ssl' => false, 'updated_at' => now()]);
        DB::table('api_integrations')->where('code', 'production')->update(['verify_ssl' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('api_integrations')->whereIn('code', ['quality', 'production'])->update(['verify_ssl' => true, 'updated_at' => now()]);
    }
};
