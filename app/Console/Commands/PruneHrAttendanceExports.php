<?php

namespace App\Console\Commands;

use App\Modules\HR\Models\ReportExport;
use App\Modules\HR\Services\AttendanceExportQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PruneHrAttendanceExports extends Command
{
    protected $signature = 'hr:prune-attendance-exports';

    protected $description = 'Remove expired derived attendance files while retaining export request history';

    public function handle(AttendanceExportQueue $exports): int
    {
        if (! Schema::hasTable('hr_attendance_report_exports')) {
            return self::SUCCESS;
        }
        $count = 0;
        ReportExport::query()->where('expires_at', '<=', now('UTC')->format('Y-m-d H:i:s'))->where('state', '!=', 'expired')
            ->chunkById(100, function ($requests) use ($exports, &$count): void {
                foreach ($requests as $export) {
                    ReportExport::query()->getConnection()->transaction(function () use ($export, $exports, &$count): void {
                        $export = ReportExport::query()->lockForUpdate()->findOrFail($export->id);
                        if (! Storage::disk('local')->deleteDirectory($exports->folder($export))) {
                            throw new \RuntimeException('Unable to remove an expired HR export directory.');
                        }
                        $export->update(['state' => 'expired', 'artifact' => null, 'artifact_hash' => null, 'parts' => []]);
                        $count++;
                    });
                }
            });
        $this->info('Expired derived attendance exports removed: '.$count);

        return self::SUCCESS;
    }
}
