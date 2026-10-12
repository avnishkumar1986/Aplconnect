<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_distributor_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('tbl_users')->cascadeOnDelete();
            $table->unsignedSmallInteger('target_year');
            $table->unsignedTinyInteger('target_month')->default(0); // 0 = yearly target
            $table->decimal('target_amount', 18, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'target_year', 'target_month'], 'distributor_target_period_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('tbl_distributor_targets'); }
};
