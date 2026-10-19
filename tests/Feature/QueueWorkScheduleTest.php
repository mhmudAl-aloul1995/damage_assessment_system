<?php

use Illuminate\Console\Scheduling\Schedule;

test('reh queue worker is scheduled independently with a mutex covering its runtime', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (object $event): bool => str_contains($event->command, 'phc:queue-work-reh'));

    expect($event)->not->toBeNull()
        ->and($event->getExpression())->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(65)
        ->and($event->runInBackground)->toBeTrue();
});

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

test('arcgis queue work is scheduled with multiple worker slots', function () {
    $queueWorkEvents = collect(app(Schedule::class)->events())
        ->filter(function (object $event): bool {
            return str_contains($event->command, 'phc:queue-work-arcgis')
                && str_contains($event->command, '--slot=');
        })
        ->values();

    expect($queueWorkEvents)->toHaveCount(3);

    $queueWorkEvents->each(function (object $event): void {
        expect($event->getExpression())->toBe('* * * * *');
        expect($event->withoutOverlapping)->toBeTrue();
        expect($event->runInBackground)->toBeTrue();
    });
});
