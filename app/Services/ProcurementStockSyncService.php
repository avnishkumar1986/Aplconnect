<?php

namespace App\Services;

use App\Jobs\SyncApiIntegration;
use App\Models\ApiIntegration;
use App\Models\ApiSyncRun;
use Illuminate\Support\Str;
use RuntimeException;

class ProcurementStockSyncService
{
    // Both API screens and procurement use the same stock import job.
    public function sync(): ApiSyncRun
    {
        $integration = ApiIntegration::where('code', 'stock')->firstOrFail();
        if (! $integration->status) throw new RuntimeException('Activate the stock API before synchronizing.');
        $run = ApiSyncRun::create([
            'api_integration_id' => $integration->id, 'run_uuid' => (string) Str::uuid(),
            'status' => 'queued', 'started_at' => now(), 'triggered_by' => auth()->id(),
        ]);
        SyncApiIntegration::dispatch($run->id)->onQueue('api-sync');

        return $run;
    }
}
