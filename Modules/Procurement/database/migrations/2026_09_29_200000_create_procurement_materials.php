<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        DB::table('procurement_materials')->insert([
            'matnr' => 'RMRESPVCFP',
            'mtart' => 'ZRAW',
            'matkl' => 'RMRESU',
            'meins' => 'KG',
            'maktx' => 'PVC RESIN Fitting PRIME',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_materials');
    }
};
