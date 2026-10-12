<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_distributor_subsidiaries', function (Blueprint $table): void {
            $table->string('address_line_1')->nullable()->after('email');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city', 100)->nullable()->after('address_line_2');
            $table->string('district', 100)->nullable()->after('city');
            $table->string('state', 100)->nullable()->after('district');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('country', 100)->default('India')->after('postal_code');
            $table->decimal('latitude', 10, 7)->nullable()->after('country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_distributor_subsidiaries', function (Blueprint $table): void {
            $table->dropColumn([
                'address_line_1', 'address_line_2', 'city', 'district', 'state',
                'postal_code', 'country', 'latitude', 'longitude',
            ]);
        });
    }
};
