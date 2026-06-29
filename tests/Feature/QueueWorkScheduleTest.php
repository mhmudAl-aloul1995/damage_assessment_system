<?php

use Illuminate\Console\Scheduling\Schedule;

test('queue work is scheduled every minute', function () {
    $queueWorkEvent = collect(app(Schedule::class)->events())
        ->first(function (object $event): bool {
            return str_contains($event->command, 'queue:work')
                && str_contains($event->command, '--stop-when-empty')
                && str_contains($event->command, '--queue=exports');
        });

    expect($queueWorkEvent)->not->toBeNull();
    expect($queueWorkEvent->getExpression())->toBe('* * * * *');
    expect($queueWorkEvent->withoutOverlapping)->toBeTrue();
    expect($queueWorkEvent->runInBackground)->toBeTrue();
});
