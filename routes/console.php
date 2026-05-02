<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work database --stop-when-empty --tries=1 --timeout=3600 --memory=2048')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/queue-schedule.log'))
    ->runInBackground();

Schedule::command('sync:arcgis-layers')
    ->dailyAt(config('database_backup.schedule_time', '01:00'))
    ->withoutOverlapping()
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->appendOutputTo(storage_path('logs/schedule.log'))
    ->runInBackground();

Schedule::command('app:backup-database')
    ->dailyAt(config('database_backup.schedule_time', '00:00'))
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/schedule.log'));
