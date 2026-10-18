<?php

namespace App\Modules\Reh\Jobs;

use App\Modules\Reh\Models\RehSyncIssue;
use App\Modules\Reh\Models\RehSyncRun;
use App\Modules\Reh\Services\RehArcgisClient;
use App\Modules\Reh\Services\RehImporter;
use App\Modules\Reh\Services\RehSyncException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class SyncRehSurvey implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $runId)
    {
        $this->onConnection(config('reh.queue_connection'));
        $this->onQueue('reh');
    }

    /**
     * Execute the job.
     */
    public function handle(RehArcgisClient $client, RehImporter $importer): void
    {
        $lock = Cache::lock('reh:sync:execution', 1900);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }
        try {
            $run = RehSyncRun::query()->findOrFail($this->runId);
            if (! in_array($run->status, ['queued', 'running'], true)) {
                return;
            }
            $run->update(['status' => 'running', 'started_at' => now(), 'message' => 'جاري استقبال المباني والوحدات…']);
            $buildings = [];
            $units = [];
            $issueIds = [];
            foreach ($client->records((int) config('reh.buildings_layer')) as $feature) {
                $buildings[] = $importer->building($feature)->id;
                if (count($buildings) % 100 === 0) {
                    $run->update(['buildings_count' => count($buildings)]);
                }
            }
            $run->update(['buildings_count' => count($buildings)]);
            foreach ($client->records((int) config('reh.units_layer')) as $feature) {
                try {
                    $units[] = $importer->unit($feature)->id;
                    RehSyncIssue::query()->where('object_id', $feature['attributes']['objectid'])->whereNull('resolved_at')->update(['resolved_at' => now()]);
                } catch (RehSyncException $exception) {
                    if (! in_array($exception->getCode(), [1001, 1002], true)) {
                        throw $exception;
                    }
                    $issue = RehSyncIssue::query()->updateOrCreate(['object_id' => $feature['attributes']['objectid']], ['source' => $feature, 'reason' => $exception->getMessage(), 'resolved_at' => null]);
                    $issueIds[] = $issue->id;
                }
                if (count($units) % 100 === 0) {
                    $run->update(['units_count' => count($units)]);
                }
            }
            $importer->markMissing($buildings, $units);
            RehSyncIssue::query()->whereNull('resolved_at')->whereNotIn('id', $issueIds)->update(['resolved_at' => now()]);
            $message = $issueIds === [] ? 'اكتملت المزامنة. لم يتم تعديل بيانات ArcGIS.' : 'اكتمل الاستقبال مع '.count($issueIds).' وحدة محفوظة تحتاج إصلاح الربط في المصدر.';
            $run->update(['status' => $issueIds === [] ? 'completed' : 'completed_with_warnings', 'units_count' => count($units), 'finished_at' => now(), 'message' => $message]);
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw new RuntimeException($exception instanceof RehSyncException ? $exception->getMessage() : 'تعذر حفظ بيانات REH؛ تحقق من الاتصال وقاعدة البيانات ثم أعد المحاولة.');
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        RehSyncRun::query()->whereKey($this->runId)->whereIn('status', ['queued', 'running'])->update(['status' => 'failed', 'finished_at' => now(), 'message' => $exception instanceof RehSyncException ? $exception->getMessage() : 'لم تكتمل المزامنة. قد تكون بعض السجلات قد حُدّثت؛ تحقق من الاتصال وصلاحيات الخدمة ثم أعد المحاولة.']);
    }
}
