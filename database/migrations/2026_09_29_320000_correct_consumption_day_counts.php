<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('procurement_daily_consumptions')->update([
            'day_count' => DB::raw('DATEDIFF(LAST_DAY(consumption_date), consumption_date) + 1'),
        ]);
    }

    public function down(): void {}
};
