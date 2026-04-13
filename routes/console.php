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

Schedule::command('sync:building')
    ->everyTenMinutes()
    ->withoutOverlapping() // Prevents the task from running if the previous one is still active
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/schedule.log'))
    ->runInBackground();
Schedule::command('sync:housing')
    ->everyTenMinutes()
    ->withoutOverlapping() // Prevents the task from running if the previous one is still active
    ->emailOutputOnFailure('mhmudaloul@gmail.com')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/schedule.log'))
    ->runInBackground();
