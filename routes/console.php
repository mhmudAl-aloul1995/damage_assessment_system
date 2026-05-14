<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
*/

$syncTimes = [
    '02:00',
    '07:30',
    '08:30',
    '09:30',
    '10:00',
    '11:00',
    '12:00',
    '13:00',
    '14:00',
    '15:00',
    '16:00',
    '17:00',
];

foreach ($syncTimes as $time) {
    Schedule::command('sync:arcgis-layers')
        ->dailyAt($time)
        ->withoutOverlapping()
        ->emailOutputOnFailure('mhmudaloul@gmail.com')
        ->appendOutputTo(storage_path('logs/schedule.log'))
        ->runInBackground();
}

/*
|--------------------------------------------------------------------------
| Database Backup
|--------------------------------------------------------------------------
*/

Schedule::command('app:backup-database')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/schedule.log'));