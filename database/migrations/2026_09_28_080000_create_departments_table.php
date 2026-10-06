<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_departments')) {
            Schema::create('tbl_departments', function (Blueprint $table) {
                $table->id();
                $table->string('department_code', 50)->unique();
                $table->string('department_name', 150);
                $table->text('description')->nullable();
                $table->boolean('status')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (! Schema::hasColumn('tbl_designations', 'department_id')) {
            Schema::table('tbl_designations', function (Blueprint $table) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('reporting_to')
                    ->constrained('tbl_departments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_designations', 'department_id')) {
            Schema::table('tbl_designations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        Schema::dropIfExists('tbl_departments');
    }
};
