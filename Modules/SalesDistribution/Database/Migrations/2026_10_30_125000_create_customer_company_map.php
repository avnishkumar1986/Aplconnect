<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_customer_company_map', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_number', 50);
            $table->string('company_code', 20);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_number', 'company_code'], 'customer_company_map_unique');
            $table->index(['company_code', 'customer_number'], 'company_customer_map_index');
        });

        $now = now();
        DB::table('tbl_dailysale')
            ->whereNotNull('customer_number')
            ->whereNotNull('company_code')
            ->select(['customer_number', 'company_code'])
            ->distinct()
            ->orderBy('customer_number')
            ->chunk(1000, function ($pairs) use ($now): void {
                DB::table('tbl_customer_company_map')->insertOrIgnore($pairs->map(fn ($pair): array => [
                    'customer_number' => $pair->customer_number,
                    'company_code' => $pair->company_code,
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_customer_company_map');
    }
};
