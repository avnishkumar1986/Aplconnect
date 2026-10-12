<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('procurement_balance_results', function (Blueprint $table) {
            $table->text('remarks')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_balance_results', function (Blueprint $table) {
            $table->string('remarks', 1000)->nullable()->change();
        });
    }
};
