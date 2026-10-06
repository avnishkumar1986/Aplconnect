<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ApiSyncRun;
use App\Models\ApiIntegration;
use App\Jobs\SyncApiIntegration;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => ApiSyncRun::purgeExpired())
    ->hourly()
    ->name('purge-expired-api-sync-runs')
    ->withoutOverlapping();

Artisan::command('procurement:sync-apis', function () {
    $steps = [
        ['code' => 'Material', 'label' => 'Material API'],
        ['code' => 'stock', 'label' => 'Stock API'],
        ['code' => 'vendor', 'label' => 'Vendor API'],
    ];
    $integrations = [];
    foreach ($steps as $step) {
        $integration = ApiIntegration::where('code', $step['code'])->firstOrFail();
        if (! $integration->status) throw new \RuntimeException($step['label'].' is inactive.');
        if ($integration->runs()->whereIn('status', ['queued', 'running'])
            ->where('started_at', '>=', now()->subHours(6))->exists()) {
            $this->warn($step['label'].' already has a synchronization in progress; the API chain was not queued again.');
            return;
        }
        $integrations[] = $integration;
    }

    $jobs = [];
    foreach ($integrations as $integration) {
        $run = ApiSyncRun::create([
            'api_integration_id' => $integration->id,
            'run_uuid' => (string) Str::uuid(),
            'status' => 'queued',
            'started_at' => now(),
            'triggered_by' => null,
        ]);
        $jobs[] = (new SyncApiIntegration($run->id))->onQueue('api-sync');
    }

    Bus::chain($jobs)->onQueue('api-sync')->dispatch();
    $this->info('Material, Stock and Vendor synchronization queued in the background.');
})->purpose('Queue Material, Stock and Vendor APIs in the background with duplicate-safe upserts');

Schedule::command('procurement:sync-apis')
    ->twiceDaily(6, 18)
    ->timezone('Asia/Kolkata')
    ->name('procurement-api-sync-twice-daily')
    ->withoutOverlapping(360);
