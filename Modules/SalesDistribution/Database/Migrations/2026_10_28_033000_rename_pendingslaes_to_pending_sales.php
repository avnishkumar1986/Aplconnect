<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_pendingslaes') && ! Schema::hasTable('tbl_pending_sales')) {
            Schema::rename('tbl_pendingslaes', 'tbl_pending_sales');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_pending_sales') && ! Schema::hasTable('tbl_pendingslaes')) {
            Schema::rename('tbl_pending_sales', 'tbl_pendingslaes');
        }
    }
};
