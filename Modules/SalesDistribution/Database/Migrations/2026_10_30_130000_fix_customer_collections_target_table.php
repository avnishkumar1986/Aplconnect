<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_api_integrations')->where('code', 'customer_collections')->update([
            'target_table' => json_encode(['tbl_customer_collection_transactions'], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('tbl_api_integrations')->where('code', 'customer_collections')->update([
            'target_table' => json_encode(['tbl_monthly_collections'], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
};
