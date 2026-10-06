<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_action_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();
            $table->string('title');
            $table->foreignId('action_by')->nullable()->constrained('tbl_login')->nullOnDelete();
            $table->json('data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_action_logs');
    }
};
