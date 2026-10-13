<?php

namespace App\Modules\HR\Jobs;

use App\Modules\HR\Services\AttendanceExportQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class WriteAttendanceExportPart implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public string $exportId, public int $index) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('hr-export-part-'.$this->exportId.'-'.$this->index))->releaseAfter(10)->expireAfter(90)];
    }

    public function handle(AttendanceExportQueue $exports): void
    {
        $exports->part($this->exportId, $this->index);
    }

    public function failed(?Throwable $exception): void
    {
        app(AttendanceExportQueue::class)->failed($this->exportId);
    }
}
