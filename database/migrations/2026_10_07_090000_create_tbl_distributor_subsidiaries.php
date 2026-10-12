<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_distributor_subsidiaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distributor_user_id')->constrained('tbl_users')->cascadeOnDelete();
            $table->string('subsidiary_name', 255);
            $table->string('phone_number', 30);
            $table->string('email', 255);
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->index(['distributor_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_distributor_subsidiaries');
    }
};
