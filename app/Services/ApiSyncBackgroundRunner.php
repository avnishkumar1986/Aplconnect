<?php

namespace App\Services;

use App\Jobs\SyncApiIntegration;
use RuntimeException;

class ApiSyncBackgroundRunner
{
    public function dispatch(int $runId): void
    {
        SyncApiIntegration::dispatch($runId)
            ->onConnection('database')
            ->onQueue('api-sync');

        if (app()->runningUnitTests()) {
            return;
        }

        $this->startWorker($runId);
    }

    private function startWorker(int $runId): void
    {
        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $log = escapeshellarg(storage_path("logs/api-worker-{$runId}.log"));
        $arguments = "{$artisan} queue:work database --queue=api-sync --stop-when-empty --tries=1 --timeout=1800";

        $command = PHP_OS_FAMILY === 'Windows'
            ? "start \"\" /B {$php} {$arguments} >> {$log} 2>&1"
            : "nohup {$php} {$arguments} >> {$log} 2>&1 &";

        $handle = @popen($command, 'r');
        if ($handle === false) {
            throw new RuntimeException('The API background worker could not be started.');
        }

        pclose($handle);
    }
}
