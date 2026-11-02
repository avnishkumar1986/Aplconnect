<?php

use App\Jobs\ImportMaterialsFromApi;
use App\Models\ApiIntegration;
use App\Models\ApiIntegrationLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('api-logs:prune', function () {
    $deleted = ApiIntegrationLog::query()
        ->where('created_at', '<', now()->subDay())
        ->delete();

    $this->info("Deleted {$deleted} API run log(s) older than 24 hours.");
})->purpose('Delete API integration logs older than 24 hours');

Artisan::command('procurement:release-expired-planning-locks', function () {
    $released = DB::table('procurement_planning_decisions')
        ->whereDate('planning_month', '<', now('Asia/Kolkata')->startOfMonth()->toDateString())
        ->whereNotNull('locked_at')
        ->update([
            'locked_at' => null,
            'locked_by' => null,
            'updated_at' => now(),
        ]);

    $this->info("Released {$released} expired planning lock(s).");
})->purpose('Automatically unlock procurement planning decisions after their month ends');

Artisan::command('procurement:sync-planning-data', function () {
    $integrations = ApiIntegration::query()
        ->where('status', true)
        ->whereIn('code', ['stock', 'mrmcon', 'vendor'])
        ->get();

    if ($integrations->isEmpty()) {
        $this->warn('No active Stock, RM Consumption, or Vendor integration is configured.');

        return self::SUCCESS;
    }

    foreach ($integrations as $integration) {
        ImportMaterialsFromApi::dispatchSync($integration->id);
        $this->info("Synchronized {$integration->name} ({$integration->code}).");
    }

    return self::SUCCESS;
})->purpose('Refresh source data used by automatic procurement calculations');

Artisan::command('procurement:sync-vendor-orders {material?}', function (?string $material = null) {
    $integration = ApiIntegration::query()
        ->where('code', 'vendor')
        ->where('status', true)
        ->first();

    if (! $integration || blank($integration->endpoint)) {
        $this->error('No active Vendor integration is configured.');

        return self::FAILURE;
    }

    ImportMaterialsFromApi::dispatchSync($integration->id, null, $material ? trim($material) : null);
    $received = (int) $integration->logs()->latest('id')->value('records_received');
    $count = DB::table('tbl_vendor_purchase_orders')
        ->when($material, fn ($query) => $query->where('material', trim($material)))
        ->count();
    $this->info("Vendor purchase orders synchronized. {$received} item(s) received from SAP; {$count} total stored item(s) match the requested scope.");

    return self::SUCCESS;
})->purpose('Synchronize SAP vendor purchase-order items, optionally for one material');

Artisan::command('customers:sync-all {--chunk=20} {--start=0} {--max-chunks=0}', function () {
    $integration = ApiIntegration::query()->where('code', 'cust')->where('status', true)->first();
    if (! $integration || blank($integration->endpoint)) {
        $this->error('No active Customers integration is configured.');

        return self::FAILURE;
    }

    $chunk = max(1, min(100, (int) $this->option('chunk')));
    $skip = max(0, (int) $this->option('start'));
    $maxChunks = max(0, (int) $this->option('max-chunks'));
    $processedChunks = 0;

    while (true) {
        $before = DB::table('tbl_users')->where('user_type', 'USR4')->count();
        $this->info("Requesting customers {$skip} through ".($skip + $chunk - 1).'...');
        ImportMaterialsFromApi::dispatchSync($integration->id, null, null, null, $chunk, $skip);
        $log = $integration->logs()->latest('id')->first();
        if (! $log || $log->status !== 'success') {
            $this->error($log?->message ?: 'The customer chunk failed without a run log.');

            return self::FAILURE;
        }

        $received = (int) $log->records_received;
        $after = DB::table('tbl_users')->where('user_type', 'USR4')->count();
        $this->info("Chunk complete: {$received} received; ".($after - $before)." new; {$after} total customers.");
        $processedChunks++;

        if ($received < $chunk) {
            $this->info('Customer synchronization completed: SAP returned the final partial chunk.');

            return self::SUCCESS;
        }
        if ($after === $before) {
            $this->warn('Stopped because this page added no new customer numbers. SAP may be repeating or ignoring $skip.');

            return self::SUCCESS;
        }
        if ($maxChunks > 0 && $processedChunks >= $maxChunks) {
            $this->info('Checkpoint reached. Resume with --start='.($skip + $chunk).'.');

            return self::SUCCESS;
        }
        $skip += $chunk;
    }
})->purpose('Synchronize all SAP customers sequentially in resumable chunks');

Artisan::command('sales:backfill-year {--year=} {--start-month=1}', function () {
    $integration = ApiIntegration::query()->where('code', 'sales_data')->where('status', true)->first();
    if (! $integration || blank($integration->endpoint)) {
        $this->error('No active Sales Data integration is configured.');

        return self::FAILURE;
    }

    $today = CarbonImmutable::today(config('app.timezone'));
    $year = (int) ($this->option('year') ?: $today->year);
    if ($year < 2000 || $year > $today->year) {
        $this->error('The backfill year must be between 2000 and the current year.');

        return self::FAILURE;
    }

    $startMonth = (int) $this->option('start-month');
    if ($startMonth < 1 || $startMonth > 12) {
        $this->error('The start month must be between 1 and 12.');

        return self::FAILURE;
    }

    $cursor = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();
    $lastDate = $year === $today->year ? $today : $cursor->endOfYear();
    while ($cursor <= $lastDate) {
        $monthEnd = $cursor->endOfMonth()->min($lastDate);
        $this->info("Importing SAP sales from {$cursor->toDateString()} through {$monthEnd->toDateString()}...");
        ImportMaterialsFromApi::dispatchSync(
            $integration->id,
            null,
            null,
            null,
            null,
            null,
            null,
            $cursor->toDateString(),
            $monthEnd->toDateString(),
        );
        $log = $integration->logs()->latest('id')->first();
        if (! $log || $log->status !== 'success') {
            $this->error($log?->message ?: 'The SAP sales month import failed.');

            return self::FAILURE;
        }
        $this->info("Stored {$log->records_received} SAP row(s) for {$cursor->format('F Y')}.");
        $cursor = $cursor->addMonth()->startOfMonth();
    }

    $stored = DB::table('tbl_dailysale')->where('billing_year', $year)->count();
    $this->info("Sales backfill complete: {$stored} database row(s) stored for {$year}.");

    return self::SUCCESS;
})->purpose('Import SAP sales month by month from 1 January through today');

Artisan::command('collections:backfill-year {--year=} {--start-month=1}', function () {
    $integration = ApiIntegration::query()->where('code', 'customer_collections')->where('status', true)->first();
    if (! $integration || blank($integration->endpoint)) {
        $this->error('No active Customer Collections integration is configured.');
        return self::FAILURE;
    }

    $today = CarbonImmutable::today(config('app.timezone'));
    $year = (int) ($this->option('year') ?: $today->year);
    $startMonth = (int) $this->option('start-month');
    if ($year < 2000 || $year > $today->year || $startMonth < 1 || $startMonth > 12) {
        $this->error('Enter a valid year and start month.');
        return self::FAILURE;
    }

    $cursor = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();
    $lastDate = $year === $today->year ? $today : $cursor->endOfYear();
    while ($cursor <= $lastDate) {
        $monthEnd = $cursor->endOfMonth()->min($lastDate);
        $this->info("Importing SAP collections from {$cursor->toDateString()} through {$monthEnd->toDateString()}...");
        ImportMaterialsFromApi::dispatchSync($integration->id, null, null, null, null, null, null, $cursor->toDateString(), $monthEnd->toDateString());
        $log = $integration->logs()->latest('id')->first();
        if (! $log || $log->status !== 'success') {
            $this->error($log?->message ?: 'The SAP collections month import failed.');
            return self::FAILURE;
        }
        $this->info("Stored {$log->records_received} collection row(s) for {$cursor->format('F Y')}.");
        $cursor = $cursor->addMonth()->startOfMonth();
    }

    app(\App\Services\MonthlyCollectionSummaryService::class)->refresh();
    $stored = DB::table('tbl_customer_collection_transactions')->whereYear('posting_date', $year)->count();
    $this->info("Collections backfill complete: {$stored} receipt row(s) stored for {$year}.");
    return self::SUCCESS;
})->purpose('Import SAP collections month by month from 1 January through today');

Schedule::command('api-logs:prune')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('procurement:release-expired-planning-locks')
    ->dailyAt('00:05')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();

Schedule::command('procurement:sync-planning-data')
    ->everyFifteenMinutes()
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('security:audit')
    ->monthlyOn(1, '02:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping()
    ->runInBackground();
