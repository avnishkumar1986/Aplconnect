<?php

namespace App\Jobs;

use App\Models\ApiIntegration;
use App\Models\ApiIntegrationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportApiInChunks implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int $apiId,
        public ?int $requestedBy,
        public int $logId,
        public int $chunkSize = 100,
        public int $offset = 0,
        public int $totalReceived = 0,
        public ?string $previousSignature = null,
    ) {
        $this->onQueue('api-imports');
    }

    public function handle(): void
    {
        $api = ApiIntegration::findOrFail($this->apiId);
        $runLog = ApiIntegrationLog::query()
            ->where('api_integration_id', $api->id)
            ->findOrFail($this->logId);

        try {
            $import = new ImportMaterialsFromApi(
                apiId: $api->id,
                requestedBy: $this->requestedBy,
                logId: $runLog->id,
                recordLimit: $this->chunkSize,
                recordSkip: $this->offset,
                deferRunStatus: true,
            );
            $import->handle();

            $runLog->refresh();
            if ($runLog->status !== 'success') {
                return;
            }

            $received = $import->lastReceivedCount;
            $signature = $import->lastBatchSignature;
            $totalReceived = $this->totalReceived + $received;
            $isFinalChunk = $received < $this->chunkSize;
            $isRepeatedPage = $received > 0 && $this->previousSignature !== null && $signature === $this->previousSignature;

            if ($isFinalChunk || $isRepeatedPage) {
                $reason = $isFinalChunk
                    ? 'The API returned the final partial chunk.'
                    : 'The API repeated the previous page, so the safety stop was applied.';
                $runLog->update([
                    'status' => 'success',
                    'records_received' => $totalReceived,
                    'message' => "Chunk import completed. {$totalReceived} record(s) received. {$reason}",
                ]);
                $api->update(['last_run_status' => 'success', 'last_run_at' => now()]);

                return;
            }

            // Advance by what SAP actually returned. Some SAP services cap a
            // response below the requested $top value; advancing by chunkSize
            // in that case would silently skip records.
            $nextOffset = $this->offset + $received;
            $runLog->update([
                'status' => 'running',
                'records_received' => $totalReceived,
                'message' => "Imported through offset ".($nextOffset - 1).". Queuing the next {$this->chunkSize} records.",
            ]);
            $api->update(['last_run_status' => 'running', 'last_run_at' => now()]);

            self::dispatch($api->id, $this->requestedBy, $runLog->id, $this->chunkSize, $nextOffset, $totalReceived, $signature);
        } catch (Throwable $exception) {
            $runLog->update(['status' => 'failed', 'message' => $exception->getMessage()]);
            $api->update(['last_run_status' => 'failed', 'last_run_at' => now()]);
            throw $exception;
        }
    }
}
