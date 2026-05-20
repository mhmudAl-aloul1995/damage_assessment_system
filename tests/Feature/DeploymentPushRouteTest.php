<?php

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('redirects to the server pull url when there are no local changes to push', function (): void {
    $sequence = Process::sequence()
        ->push(Process::result('added'))
        ->push(Process::result('', '', 0));

    Process::fake(fn () => $sequence());

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $this->actingAs(User::factory()->create())
        ->get('/push')
        ->assertRedirect(config('app.server_pull_url'));

    Process::assertRanTimes(fn (): bool => true, 2);
    Process::assertNotRan(fn ($process): bool => is_array($process->command)
        && in_array('commit', $process->command, true));
    Process::assertNotRan(fn ($process): bool => is_array($process->command)
        && in_array('push', $process->command, true));
});

it('commits pushes and then redirects to the server pull url when local changes exist', function (): void {
    $sequence = Process::sequence()
        ->push(Process::result('added'))
        ->push(Process::result('', '', 1))
        ->push(Process::result('[main abc123] Auto-update'))
        ->push(Process::result('pushed'));

    Process::fake(fn () => $sequence());

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $this->actingAs(User::factory()->create())
        ->get('/push')
        ->assertRedirect(config('app.server_pull_url'));

    Process::assertRanTimes(fn (): bool => true, 4);
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('commit', $process->command, true));
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('push', $process->command, true));
});
