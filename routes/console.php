<?php

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
//اا
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('phc:queue-status', function () {
    $queues = collect(['arcgis', 'default', 'exports', config('heks_kobo.queue', 'heks')])
        ->filter()
        ->unique()
        ->values();

    $this->info('Queue connection: '.config('queue.default'));

    if (! Schema::hasTable('jobs')) {
        $this->warn('The jobs table does not exist.');

        return Command::FAILURE;
    }

    $rows = $queues->map(fn (string $queue): array => [
        'Queue' => $queue,
        'Pending' => DB::table('jobs')->where('queue', $queue)->whereNull('reserved_at')->count(),
        'Reserved' => DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->count(),
        'Failed' => Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('queue', $queue)->count()
            : 0,
    ])->all();

    $this->table(['Queue', 'Pending', 'Reserved', 'Failed'], $rows);
    $this->line('Run workers: php artisan phc:queue-work-arcgis / php artisan phc:queue-work-exports');

    return Command::SUCCESS;
})->purpose('Display PHC queue status and pending job counts');

Artisan::command('phc:queue-work-arcgis {--once : Process only one job} {--daemon : Keep the worker running when the queue is empty} {--slot=manual : Worker slot id for scheduler mutexes and logs}', function () {
    $options = [
        'connection' => 'database',
        '--queue' => 'arcgis,default',
        '--tries' => 3,
        '--timeout' => 180,
        '--memory' => 512,
    ];

    if ($this->option('once')) {
        $options['--once'] = true;
    } elseif (! $this->option('daemon')) {
        $options['--stop-when-empty'] = true;
    }

    return $this->call('queue:work', $options);
})->purpose('Process PHC ArcGIS and default queue jobs');

Artisan::command('phc:queue-work-exports {--once : Process only one job}', function () {
    $options = [
        'connection' => 'database',
        '--queue' => 'exports',
        '--tries' => 1,
        '--timeout' => 3600,
        '--memory' => 2048,
    ];

    if ($this->option('once')) {
        $options['--once'] = true;
    } else {
        $options['--stop-when-empty'] = true;
    }

    return $this->call('queue:work', $options);
})->purpose('Process PHC export queue jobs');

Artisan::command('phc:queue-restart', function () {
    return $this->call('queue:restart');
})->purpose('Restart PHC queue workers after their current job');

/*
|--------------------------------------------------------------------------
| Export Queue Worker
|--------------------------------------------------------------------------
*/

Schedule::command('queue:work database --queue=exports --stop-when-empty --tries=1 --timeout=3600 --memory=2048')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->name('exports-queue-worker')
    ->appendOutputTo(storage_path('logs/queue-schedule.log'));

foreach (range(1, 3) as $workerSlot) {
    Schedule::command("phc:queue-work-arcgis --slot={$workerSlot}")
        ->everyMinute()
        ->withoutOverlapping(10)
        ->runInBackground()
        ->name("arcgis-queue-worker-{$workerSlot}")
        ->appendOutputTo(storage_path('logs/queue-schedule.log'));
}

/*
|--------------------------------------------------------------------------
| ArcGIS Sync Schedule
|--------------------------------------------------------------------------
| يعمل كل ساعة من 01:00 حتى 23:00
*/
Schedule::command('sync:arcgis-layers')
    ->cron('0 16-23 * * *')
    ->withoutOverlapping(120)
    ->name('sync-arcgis-layers')
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->appendOutputTo(storage_path('logs/sync-arcgis.log'));

Schedule::command('kobo:sync-iqrad-borrowers --all')
    ->everyFiveMinutes()
    ->withoutOverlapping(20)
    ->runInBackground()
    ->name('kobo-sync-iqrad-borrowers')
    ->appendOutputTo(storage_path('logs/kobo-iqrad-sync.log'));

Schedule::command('heks:kobo-sync --all')
    ->everyFiveMinutes()
    ->withoutOverlapping(20)
    ->runInBackground()
    ->name('heks-kobo-sync')
    ->appendOutputTo(storage_path('logs/heks-kobo-sync.log'));
/*
|-------f-------------------------------------------------------------------
| Database Backup
|--------------------------------------------------------------------------
*/

Schedule::command('app:backup-database')
    ->dailyAt('00:00')
    ->withoutOverlapping(120)
    ->name('database-backup')
    ->appendOutputTo(storage_path('logs/schedule.log'));
