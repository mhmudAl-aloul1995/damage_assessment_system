<?php

namespace App\Modules\Reh\Jobs;

use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Services\RehBulkUnitDeletion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DeleteRehUnits implements ShouldQueue
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
    public function handle(RehBulkUnitDeletion $bulk): void
    {
        $lock = Cache::lock('reh:bulk-operation:'.$this->operationId, 1900);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }
        try {
            $log = RehAuditLog::query()->where('record_kind', 'unit_bulk')->find($this->operationId);
            if ($log && $bulk->processChunk($log)) {
                if ($log->values['waiting_for_sync'] ?? false) {
                    self::dispatch($this->operationId)->delay(now()->addSeconds(30));
                } else {
                    self::dispatch($this->operationId);
                }
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        RehAuditLog::query()->where('record_kind', 'unit_bulk')->whereKey($this->operationId)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'message' => 'توقفت المعالجة. النتائج المنفذة محفوظة؛ راجع السجل قبل إنشاء طلب جديد.']);
    }
}
