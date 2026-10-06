<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_company_contacts', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->foreignId('contact_id')->constrained('tbl_contacts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'contact_id']);
            $table->foreign('company_id')->references('id')->on('tbl_company')->cascadeOnDelete();
        });
        Schema::create('tbl_company_addresses', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->foreignId('address_id')->constrained('tbl_addresses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'address_id']);
            $table->foreign('company_id')->references('id')->on('tbl_company')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_company_addresses');
        Schema::dropIfExists('tbl_company_contacts');
    }
};
