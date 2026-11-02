<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_usraddress', function (Blueprint $table): void {
            $table->string('address_line_1')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('tbl_usraddress')->whereNull('address_line_1')->update(['address_line_1' => '']);

        Schema::table('tbl_usraddress', function (Blueprint $table): void {
            $table->string('address_line_1')->nullable(false)->change();
        });
    }
};
