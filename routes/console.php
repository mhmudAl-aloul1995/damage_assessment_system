<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Export Queue Worker
|--------------------------------------------------------------------------
*/

Schedule::command('queue:work database --queue=exports --stop-when-empty --tries=1 --timeout=3600 --memory=2048')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->name('exports-queue-worker')
    ->appendOutputTo(storage_path('logs/queue-schedule.log'))
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| ArcGIS Sync Schedule
|--------------------------------------------------------------------------
| يعمل كل ساعة من 01:00 حتى 23:00
*/
Schedule::command('sync:arcgis-layers')
    ->cron('0 1-23 * * *')
    ->withoutOverlapping(120)
    ->name('sync-arcgis-layers')
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->appendOutputTo(storage_path('logs/sync-arcgis.log'));
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