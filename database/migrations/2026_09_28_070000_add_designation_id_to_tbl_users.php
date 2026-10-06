<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_users', 'designation_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->foreignId('designation_id')->nullable()->after('user_type_code')
                    ->constrained('tbl_designations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_users', 'designation_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('designation_id');
            });
        }
    }
};
