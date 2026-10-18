<?php

namespace App\Modules\Reh\Console\Commands;

use Illuminate\Console\Command;

class SyncRehCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reh:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read the REH Phase 2 ArcGIS survey into the local review module';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $run = \App\Modules\Reh\Models\RehSyncRun::query()->where('status', 'queued')->oldest()->first()
            ?? \App\Modules\Reh\Models\RehSyncRun::query()->create();
        try {
            app()->call([new \App\Modules\Reh\Jobs\SyncRehSurvey($run->id), 'handle']);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $run->refresh();
        $this->info("REH: {$run->status}; buildings: {$run->buildings_count}; units: {$run->units_count}");

        return in_array($run->status, ['completed', 'completed_with_warnings'], true) ? self::SUCCESS : self::FAILURE;
    }
}
