<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_users', 'unique_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->string('unique_id', 20)->nullable()->unique()->after('user_type_id');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'first_name')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->string('first_name', 100)->nullable()->after('unique_id');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'middle_name')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'last_name')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->string('last_name', 100)->nullable()->after('middle_name');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'gender')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->string('gender', 20)->nullable()->after('last_name');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'date_of_birth')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->date('date_of_birth')->nullable()->after('gender');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'anniversary_date')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->date('anniversary_date')->nullable()->after('date_of_birth');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'contact_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id')->nullable()->index()->after('anniversary_date');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'employment_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->unsignedBigInteger('employment_id')->nullable()->index()->after('contact_id');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'education_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->unsignedBigInteger('education_id')->nullable()->index()->after('employment_id');
            });
        }

        if (! Schema::hasColumn('tbl_users', 'address_id')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->unsignedBigInteger('address_id')->nullable()->index()->after('education_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropColumn([
                'unique_id',
                'first_name',
                'middle_name',
                'last_name',
                'gender',
                'date_of_birth',
                'anniversary_date',
                'contact_id',
                'employment_id',
                'education_id',
                'address_id',
            ]);
        });
    }
};
