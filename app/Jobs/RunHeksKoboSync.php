<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class RunHeksKoboSync implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Artisan::call('heks:kobo-sync', [
            '--all' => true,
        ]);
    }
}
