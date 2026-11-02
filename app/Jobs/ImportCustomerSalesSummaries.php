<?php

namespace App\Jobs;

use App\Models\ApiIntegration;
use App\Models\ApiIntegrationLog;
use App\Services\MonthlyCollectionSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportCustomerSalesSummaries implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $apiId,
        public ?int $requestedBy,
        public int $logId,
        public int $afterUserId = 0,
        public int $processed = 0,
        public int $failed = 0,
    ) {
        $this->onQueue('api-imports');
    }

    public function handle(): void
    {
        $api = ApiIntegration::query()->findOrFail($this->apiId);
        $runLog = ApiIntegrationLog::query()
            ->where('api_integration_id', $api->id)
            ->findOrFail($this->logId);

        $customer = DB::table('tbl_users')
            ->where('user_type', 'USR4')
            ->where('id', '>', $this->afterUserId)
            ->whereNotNull('unique_id')
            ->where('unique_id', '<>', '')
            ->orderBy('id')
            ->first(['id', 'unique_id']);

        if (! $customer) {
            app(MonthlyCollectionSummaryService::class)->refresh();
            $status = $this->failed > 0 ? 'warning' : 'success';
            $runLog->update([
                'status' => $status,
                'records_received' => $this->processed,
                'message' => "Customer sales summary import completed. {$this->processed} imported, {$this->failed} failed.",
            ]);
            $api->update(['last_run_status' => $status, 'last_run_at' => now()]);

            return;
        }

        try {
            $customerNumber = trim((string) $customer->unique_id);
            $sapCustomerNumber = ctype_digit($customerNumber)
                ? (ltrim($customerNumber, '0') ?: '0')
                : $customerNumber;
            $import = new ImportMaterialsFromApi(
                apiId: $api->id,
                requestedBy: $this->requestedBy,
                logId: $runLog->id,
                customerNumber: $sapCustomerNumber,
            );
            $import->handle();

            $processed = $this->processed + $import->lastReceivedCount;
            self::dispatch($api->id, $this->requestedBy, $runLog->id, (int) $customer->id, $processed, $this->failed);
            $runLog->update([
                'status' => 'running',
                'records_received' => $processed,
                'message' => "Imported customer {$customer->unique_id}. Queuing the next USR4 customer.",
            ]);
            $api->update(['last_run_status' => 'running', 'last_run_at' => now()]);

        } catch (Throwable $exception) {
            $failed = $this->failed + 1;
            self::dispatch($api->id, $this->requestedBy, $runLog->id, (int) $customer->id, $this->processed, $failed);
            $runLog->update([
                'status' => 'running',
                'records_received' => $this->processed,
                'message' => "Customer {$customer->unique_id} failed and was skipped ({$failed} failure(s)): {$exception->getMessage()}. Queuing the next USR4 customer.",
            ]);
            $api->update(['last_run_status' => 'running', 'last_run_at' => now()]);

        }
    }
}
