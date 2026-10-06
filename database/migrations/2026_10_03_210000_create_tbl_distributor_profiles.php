<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_distributor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('tbl_users')->cascadeOnDelete();
            $table->string('business_name', 255);
            $table->string('legal_business_name', 255);
            $table->enum('distributor_category', ['regional', 'exclusive', 'stockist', 'national', 'other']);
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_distributor_profiles');
    }
};
