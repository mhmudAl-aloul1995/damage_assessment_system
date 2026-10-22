<?php

namespace App\Modules\Reh\Jobs;

use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Services\RehUnitRestoration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RestoreRehUnit implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 1;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    public function __construct(public int $operationId)
    {
        $this->onConnection(config('reh.queue_connection'));
        $this->onQueue('reh');
    }

    /**
     * Execute the job.
     */
    public function handle(RehUnitRestoration $restoration): void
    {
        $operationLock = Cache::lock('reh:restore:'.$this->operationId, 1900);
        if (! $operationLock->get()) {
            $this->release(30);

            return;
        }
        $syncLock = Cache::lock('reh:sync:execution', 1900);
        try {
            if (! $syncLock->get()) {
                $this->release(30);

                return;
            }
            $log = RehAuditLog::query()->where('record_kind', 'unit_restore')->find($this->operationId);
            if ($log && in_array($log->status, ['queued', 'running'], true)) {
                $restoration->process($log);
            }
        } finally {
            $syncLock->release();
            $operationLock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = RehAuditLog::query()->where('record_kind', 'unit_restore')->find($this->operationId);
        if ($log && $log->status !== 'completed') {
            $log->update(['status' => 'needs_review', 'message' => 'توقفت المعالجة. استخدم التحقق والاستكمال قبل أي طلب جديد.']);
        }
    }
}
