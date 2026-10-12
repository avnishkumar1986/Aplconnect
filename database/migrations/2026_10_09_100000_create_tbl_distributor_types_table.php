<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_distributor_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('tbl_distributor_types')->insert([
            ['code' => 'regional', 'name' => 'Regional Distributor', 'description' => 'Distributor assigned to a specific region.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'exclusive', 'name' => 'Exclusive Distributor', 'description' => 'Exclusive distributor for an assigned territory.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'stockist', 'name' => 'Stockist', 'description' => 'Stock-holding distribution partner.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'national', 'name' => 'National Distributor', 'description' => 'Distributor operating across India.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'other', 'name' => 'Other', 'description' => 'Other distributor classification.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_distributor_types');
    }
};
