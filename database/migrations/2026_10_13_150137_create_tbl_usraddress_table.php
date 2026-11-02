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
        Schema::create('tbl_usraddress', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 10);
            $table->string('address_type', 30)->default('primary')->index();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('state', 100)->nullable()->index();
            $table->string('postal_code', 20)->nullable()->index();
            $table->string('country', 100)->nullable()->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'address_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_usraddress');
    }
};
