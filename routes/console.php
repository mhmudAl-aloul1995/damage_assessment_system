<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/schedule.log'))
    ->runInBackground();

/* Schedule::command('sync:building')
    ->hourly()
    ->withoutOverlapping() // Prevents the task from running if the previous one is still active
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/schedule.log'))
    ->runInBackground();
Schedule::command('sync:housing')
    ->hourly()
    ->withoutOverlapping() // Prevents the task from running if the previous one is still active
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/schedule.log'))
    ->runInBackground();

Schedule::command('sync:public-building')
    ->hourly()
    ->withoutOverlapping()
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/schedule.log'))
    ->runInBackground(); */
Schedule::command('sync:arcgis-layers')
    ->dailyAt(config('database_backup.schedule_time', '00:00'))
    ->withoutOverlapping()
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/schedule.log'))
    ->runInBackground();
Schedule::command('app:backup-database')
    ->dailyAt(config('database_backup.schedule_time', '00:00'))
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/schedule.log'));

    