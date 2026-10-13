<?php

namespace App\Modules\HR\Jobs;

use App\Modules\HR\Models\ReportExport;
use App\Modules\HR\Services\AttendanceExportQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Throwable;

class PrepareAttendanceExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public string $exportId)
    {
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('hr-export-prepare-'.$this->exportId))->releaseAfter(10)->expireAfter(90)];
    }

    public function handle(AttendanceExportQueue $exports): void
    {
        $export = ReportExport::query()->findOrFail($this->exportId);
        if (in_array($export->state, ['ready', 'failed', 'expired'], true)) {
            return;
        }
        $exports->authorized($export);
        $jobs = [];
        foreach (array_chunk($export->employee_ids, AttendanceExportQueue::PART_SIZE) as $index => $ids) {
            $jobs[] = new WriteAttendanceExportPart($export->id, $index);
        }
        $jobs[] = new FinishAttendanceExport($export->id);
        Bus::chain($jobs)->dispatch();
    }

    public function failed(?Throwable $exception): void
    {
        app(AttendanceExportQueue::class)->failed($this->exportId);
    }
}
