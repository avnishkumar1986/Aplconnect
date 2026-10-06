<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tbl_apiattemptlogs');
        Schema::dropIfExists('tbl_apipayloads');
        Schema::dropIfExists('tbl_apijobruns');
        Schema::dropIfExists('tbl_apiintegrations');
    }

    public function down(): void
    {
        // Obsolete tables are intentionally not recreated.
    }
};
