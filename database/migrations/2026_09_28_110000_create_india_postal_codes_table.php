<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_india_postal_codes', function (Blueprint $table) {
            $table->id();
            $table->string('state', 100);
            $table->string('district', 100);
            $table->string('city', 180);
            $table->string('postal_code', 6);
            $table->index(['state', 'district']);
            $table->index(['state', 'district', 'city']);
            $table->unique(['state', 'district', 'city', 'postal_code'], 'india_postal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_india_postal_codes');
    }
};
