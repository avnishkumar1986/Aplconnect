<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_users', 'middle_name')) {
            Schema::table('tbl_users', function (Blueprint $table): void {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_users', 'middle_name')) {
            Schema::table('tbl_users', function (Blueprint $table): void {
                $table->dropColumn('middle_name');
            });
        }
    }
};
