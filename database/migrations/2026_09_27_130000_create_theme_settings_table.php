<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_theme_settings', function (Blueprint $table) {
            $table->id();
            $table->string('preset', 40)->default('teal-cyan');
            $table->enum('default_mode', ['light', 'dark'])->default('light');
            $table->string('primary_color', 7)->default('#159aa6');
            $table->string('secondary_color', 7)->default('#31b7c3');
            $table->string('light_navbar_bg', 7)->default('#ffffff');
            $table->string('light_sidebar_bg', 7)->default('#111b2e');
            $table->string('light_navbar_text', 7)->default('#24334a');
            $table->string('light_sidebar_text', 7)->default('#b5c0d3');
            $table->string('dark_navbar_bg', 7)->default('#101827');
            $table->string('dark_sidebar_bg', 7)->default('#0b1220');
            $table->string('dark_navbar_text', 7)->default('#e5edf7');
            $table->string('dark_sidebar_text', 7)->default('#9daac0');
            $table->string('canvas_bg', 7)->default('#f3f6fa');
            $table->string('card_bg', 7)->default('#ffffff');
            $table->foreignId('updated_by')->nullable()->constrained('tbl_login')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_theme_settings');
    }
};
