<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_addresses', function (Blueprint $table) {
            $table->boolean('is_current_permanent_same')->default(false)->after('address_type');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_addresses', function (Blueprint $table) {
            $table->dropColumn('is_current_permanent_same');
        });
    }
};
