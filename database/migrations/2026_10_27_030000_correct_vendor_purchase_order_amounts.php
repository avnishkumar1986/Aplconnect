<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_vendor_purchase_orders')
            ->where('amount', 0)
            ->where('rate', '>', 0)
            ->orderBy('id')
            ->chunkById(250, function ($orders): void {
                foreach ($orders as $order) {
                    $payload = json_decode((string) $order->api_payload, true);
                    $payload = is_array($payload) ? array_change_key_case($payload, CASE_LOWER) : [];

                    if (! array_key_exists('poprice', $payload)) {
                        continue;
                    }

                    $amount = (float) $order->rate;
                    $quantity = (float) $order->quantity_mt;

                    DB::table('tbl_vendor_purchase_orders')
                        ->where('id', $order->id)
                        ->update([
                            'amount' => $amount,
                            'rate' => $quantity > 0 ? $amount / $quantity : 0,
                            'currency' => $order->currency ?: 'INR',
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // The original rate/amount interpretation cannot be restored reliably.
    }
};
