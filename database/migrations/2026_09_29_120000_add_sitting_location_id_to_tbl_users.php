<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_users', 'sitting_location_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->integer('sitting_location_id')->nullable()->after('company_id')->index();
                $table->foreign('sitting_location_id')
                    ->references('id')
                    ->on('tbl_company')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_users', 'sitting_location_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->dropForeign(['sitting_location_id']);
                $table->dropColumn('sitting_location_id');
            });
        }
    }
};
