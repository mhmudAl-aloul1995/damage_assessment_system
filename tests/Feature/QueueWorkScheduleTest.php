<?php

use Illuminate\Console\Scheduling\Schedule;

test('queue work is scheduled every minute', function () {
    $queueWorkEvent = collect(app(Schedule::class)->events())
        ->first(function (object $event): bool {
            return str_contains($event->command, 'queue:work --stop-when-empty');
        });

    expect($queueWorkEvent)->not->toBeNull();
    expect($queueWorkEvent->getExpression())->toBe('* * * * *');
    expect($queueWorkEvent->withoutOverlapping)->toBeTrue();
    expect($queueWorkEvent->runInBackground)->toBeTrue();
});
