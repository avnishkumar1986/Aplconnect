<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_integrations', fn (Blueprint $table) => $table->renameColumn('destination_table', 'tbl_name'));
        Schema::table('api_integrations', fn (Blueprint $table) => $table->dropColumn('destination_database'));
    }

    public function down(): void
    {
        Schema::table('api_integrations', fn (Blueprint $table) => $table->string('destination_database', 64)->nullable()->after('endpoint_path'));
        Schema::table('api_integrations', fn (Blueprint $table) => $table->renameColumn('tbl_name', 'destination_table'));
    }
};
