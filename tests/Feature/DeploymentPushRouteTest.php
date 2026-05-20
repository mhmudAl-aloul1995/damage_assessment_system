<?php

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('pulls the server repo even when there are no local changes to push', function (): void {
    $sequence = Process::sequence()
        ->push(Process::result('added'))
        ->push(Process::result('', '', 0))
        ->push(Process::result('Already up to date.'));

    Process::fake(fn () => $sequence());

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $this->actingAs(User::factory()->create())
        ->get('/push')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('push_status', 'no_changes')
        ->assertJsonPath('steps.2.name', 'server_git_pull');

    Process::assertRanTimes(fn (): bool => true, 3);
    Process::assertNotRan(fn ($process): bool => is_array($process->command)
        && in_array('commit', $process->command, true));
    Process::assertNotRan(fn ($process): bool => is_array($process->command)
        && in_array('push', $process->command, true));
});

it('commits pushes and then pulls the server repo when local changes exist', function (): void {
    $sequence = Process::sequence()
        ->push(Process::result('added'))
        ->push(Process::result('', '', 1))
        ->push(Process::result('[main abc123] Auto-update'))
        ->push(Process::result('pushed'))
        ->push(Process::result('Already up to date.'));

    Process::fake(fn () => $sequence());

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $this->actingAs(User::factory()->create())
        ->get('/push')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('push_status', 'pushed')
        ->assertJsonPath('steps.3.name', 'local_git_push')
        ->assertJsonPath('steps.4.name', 'server_git_pull');

    Process::assertRanTimes(fn (): bool => true, 5);
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('commit', $process->command, true));
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('push', $process->command, true));
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('pull', $process->command, true));
});
