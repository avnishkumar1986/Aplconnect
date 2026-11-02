<?php

namespace App\Jobs;

use App\Models\ApiIntegration;
use App\Models\ApiIntegrationLog;
use App\Services\MonthlyCollectionSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportCustomerCollections implements ShouldQueue
{
    use Queueable;

    private const SOURCE_USER_TYPE = 'USR4';

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public int $apiId, public ?int $requestedBy, public int $logId, public int $afterUserId = 0, public int $processed = 0, public int $failed = 0)
    {
        $this->onQueue('api-imports');
    }

    public function handle(): void
    {
        $api = ApiIntegration::query()->findOrFail($this->apiId);
        $log = ApiIntegrationLog::query()->where('api_integration_id', $api->id)->findOrFail($this->logId);
        if (! str_contains((string) $api->endpoint, '{{customer_number}}')) {
            $log->update(['status' => 'cancelled', 'message' => 'Customer-wise import replaced by the month-wise posting-date import.']);
            $api->update(['last_run_status' => 'cancelled', 'last_run_at' => now()]);
            return;
        }
        $customer = DB::table('tbl_users')
            ->where('user_type', self::SOURCE_USER_TYPE)
            ->where('status', true)
            ->where('id', '>', $this->afterUserId)
            ->whereNotNull('unique_id')
            ->where('unique_id', '<>', '')
            ->orderBy('id')
            ->first(['id', 'unique_id']);

        if (! $customer) {
            app(MonthlyCollectionSummaryService::class)->refresh();
            $status = $this->failed ? 'warning' : 'success';
            $log->update(['status' => $status, 'records_received' => $this->processed, 'message' => self::SOURCE_USER_TYPE." collection import completed. {$this->processed} receipts stored, {$this->failed} users failed."]);
            $api->update(['last_run_status' => $status, 'last_run_at' => now()]);
            return;
        }

        try {
            $number = trim((string) $customer->unique_id);
            $sapNumber = ctype_digit($number) ? str_pad(ltrim($number, '0') ?: '0', 10, '0', STR_PAD_LEFT) : $number;
            $import = new ImportMaterialsFromApi(apiId: $api->id, requestedBy: $this->requestedBy, logId: $log->id, customerNumber: $sapNumber, deferRunStatus: true);
            $import->handle();
            $processed = $this->processed + $import->lastReceivedCount;
            self::dispatch($api->id, $this->requestedBy, $log->id, (int) $customer->id, $processed, $this->failed)
                ->delay(now()->addSecond());
            $log->update(['status' => 'running', 'records_received' => $processed, 'message' => "Imported collections for customer {$number}."]);
        } catch (Throwable $e) {
            $failed = $this->failed + 1;
            self::dispatch($api->id, $this->requestedBy, $log->id, (int) $customer->id, $this->processed, $failed)
                ->delay(now()->addSecond());
            $log->update(['status' => 'running', 'records_received' => $this->processed, 'message' => "Customer {$customer->unique_id} collections failed: {$e->getMessage()}"]);
        }
    }
}
