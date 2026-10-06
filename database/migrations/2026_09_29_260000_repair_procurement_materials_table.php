<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_materials')) return;

        Schema::create('procurement_materials', function (Blueprint $table) {
            $table->id();
            $table->string('matnr', 40)->unique();
            $table->string('mtart', 20);
            $table->string('matkl', 40);
            $table->string('meins', 10);
            $table->string('maktx', 255);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index(['matkl', 'status']);
        });
    }

    public function down(): void
    {
        // Repair migration intentionally does not remove application data.
    }
};
