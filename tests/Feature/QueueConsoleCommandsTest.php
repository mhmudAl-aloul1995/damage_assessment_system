<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('shows phc queue status counts', function () {
    DB::table('jobs')->insert([
        [
            'queue' => 'arcgis',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
        [
            'queue' => 'arcgis',
            'payload' => '{}',
            'attempts' => 1,
            'reserved_at' => now()->timestamp,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ],
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'exports',
        'payload' => '{}',
        'exception' => 'failed',
        'failed_at' => now(),
    ]);

    expect(Artisan::call('phc:queue-status'))->toBe(0);

    expect(Artisan::output())
        ->toContain('Queue connection:')
        ->toContain('arcgis')
        ->not->toContain('arcgis-cso')
        ->toContain('exports')
        ->toContain('reh')
        ->toContain('Run workers:');
});

it('registers phc queue console commands', function () {
    $commands = collect(Artisan::all())->keys();

    expect($commands)
        ->toContain('phc:queue-status')
        ->toContain('phc:queue-work-arcgis')
        ->toContain('phc:queue-work-exports')
        ->toContain('phc:queue-work-reh')
        ->toContain('phc:queue-restart');
});

it('runs the dedicated reh worker with safe synchronization limits', function (bool $once, bool $daemon): void {
    config(['reh.queue_connection' => 'reh']);
    $worker = Mockery::mock(\Illuminate\Queue\Worker::class);
    $worker->shouldReceive('setName')->once()->andReturnSelf();
    $worker->shouldReceive('setCache')->once()->andReturnSelf();
    $worker->shouldReceive($once ? 'runNextJob' : 'daemon')->once()
        ->with('reh', 'reh', Mockery::on(function (\Illuminate\Queue\WorkerOptions $options) use ($once, $daemon): bool {
            return $options->timeout === 1800
                && $options->timeout < config('queue.connections.reh.retry_after')
                && $options->maxTries === 1
                && $options->memory === 512
                && $options->stopWhenEmpty === (! $once && ! $daemon)
                && (int) $options->maxTime === (! $once && ! $daemon ? 1800 : 0);
        }))->andReturn(0);
    app()->instance('queue.worker', $worker);

    $this->artisan('phc:queue-work-reh', ['--once' => $once, '--daemon' => $daemon])->assertSuccessful();
})->with([[false, false], [true, false], [false, true]]);
