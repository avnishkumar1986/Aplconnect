<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_modules', function (Blueprint $table) {
            $table->enum('source_type', ['upload', 'discovered'])->default('upload')->after('stored_path');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_modules', fn (Blueprint $table) => $table->dropColumn('source_type'));
    }
};
