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
            $table->string('destination_database', 64)->nullable()->after('endpoint_path');
            $table->string('destination_table', 64)->nullable()->after('destination_database');
        });

        DB::table('api_integrations')->where('code', 'stock')->update([
            'destination_database' => config('database.connections.mysql.database'),
            'destination_table' => 'procurement_materials',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('api_integrations', fn (Blueprint $table) => $table->dropColumn(['destination_database', 'destination_table']));
    }
};
