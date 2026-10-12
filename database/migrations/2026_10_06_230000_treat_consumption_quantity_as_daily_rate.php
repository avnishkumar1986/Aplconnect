<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_daily_consumptions')
            || ! Schema::hasColumn('procurement_daily_consumptions', 'daily_consumption_mt')) {
            return;
        }

        DB::table('procurement_daily_consumptions')
            ->when(Schema::hasTable('procurement_plan_histories'), function ($query) {
                $query->whereNotExists(function ($history) {
                    $history->selectRaw('1')->from('procurement_plan_histories')
                        ->whereColumn('procurement_plan_histories.consumption_id', 'procurement_daily_consumptions.id');
                });
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $daily = round((float) $row->quantity_mt, 6);
                    $days = max(1, (int) $row->day_count);

                    DB::table('procurement_daily_consumptions')->where('id', $row->id)->update([
                        'daily_consumption_mt' => $daily,
                        'quantity_mt' => round($daily * $days, 3),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('procurement_daily_consumptions')
            || ! Schema::hasColumn('procurement_daily_consumptions', 'daily_consumption_mt')) {
            return;
        }

        DB::table('procurement_daily_consumptions')
            ->when(Schema::hasTable('procurement_plan_histories'), function ($query) {
                $query->whereNotExists(function ($history) {
                    $history->selectRaw('1')->from('procurement_plan_histories')
                        ->whereColumn('procurement_plan_histories.consumption_id', 'procurement_daily_consumptions.id');
                });
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('procurement_daily_consumptions')->where('id', $row->id)->update([
                        'quantity_mt' => round((float) $row->daily_consumption_mt, 3),
                    ]);
                }
            });
    }
};
