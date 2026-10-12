<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('procurement_purchases', 'vendor_source')) {
            Schema::table('procurement_purchases', function (Blueprint $table) {
                $table->string('vendor_source', 20)->default('sap')->after('vendor_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('procurement_purchases', 'vendor_source')) {
            Schema::table('procurement_purchases', fn (Blueprint $table) => $table->dropColumn('vendor_source'));
        }
    }
};
