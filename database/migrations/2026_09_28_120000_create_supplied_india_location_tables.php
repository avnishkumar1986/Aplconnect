<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_india_districts', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('state', 100)->index();
            $table->string('district_name', 150);
            $table->unique(['state', 'district_name'], 'india_district_state_name_unique');
        });

        Schema::create('tbl_india_city_pincodes', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('city', 180)->index();
            $table->string('pin_code', 12)->index();
            $table->unique(['city', 'pin_code'], 'india_city_pin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_india_city_pincodes');
        Schema::dropIfExists('tbl_india_districts');
    }
};
