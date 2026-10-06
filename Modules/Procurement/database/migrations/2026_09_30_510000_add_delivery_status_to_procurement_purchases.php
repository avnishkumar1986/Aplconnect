<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('procurement_purchases', function (Blueprint $table) {
            $table->string('delivery_status', 30)->default('planned')->after('expected_delivery_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchases', fn (Blueprint $table) => $table->dropColumn('delivery_status'));
    }
};
