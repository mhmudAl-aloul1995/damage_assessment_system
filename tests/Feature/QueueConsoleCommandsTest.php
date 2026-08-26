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
        ->toContain('exports')
        ->toContain('Run workers:');
});

it('registers phc queue console commands', function () {
    $commands = collect(Artisan::all())->keys();

    expect($commands)
        ->toContain('phc:queue-status')
        ->toContain('phc:queue-work-arcgis')
        ->toContain('phc:queue-work-exports')
        ->toContain('phc:queue-restart');
});
