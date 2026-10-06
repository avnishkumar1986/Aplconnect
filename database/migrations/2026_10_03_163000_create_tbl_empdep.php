<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_empdep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('tbl_users')->cascadeOnDelete();
            $table->string('company_id', 30)->collation('utf8mb4_general_ci');
            $table->integer('sitting_location_id')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('tbl_departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('tbl_designations')->nullOnDelete();
            $table->unsignedBigInteger('reporting_to_user_id')->nullable();
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->foreign('company_id')->references('company_code')->on('tbl_company')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('sitting_location_id')->references('id')->on('tbl_company')->nullOnDelete();
            $table->foreign('reporting_to_user_id')->references('id')->on('tbl_users')->nullOnDelete();
        });

        DB::table('tbl_users')->orderBy('id')->each(function ($user): void {
            DB::table('tbl_empdep')->insert([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'sitting_location_id' => $user->sitting_location_id,
                'department_id' => $user->department_id,
                'designation_id' => $user->designation_id,
                'reporting_to_user_id' => $user->reporting_to_user_id,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'created_by' => $user->created_by,
                'updated_by' => $user->updated_by,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_empdep');
    }
};
