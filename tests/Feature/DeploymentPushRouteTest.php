<?php

use App\Models\User;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('pushes existing commits before redirecting even when the working tree is clean', function (): void {
    $sequence = Process::sequence()
        ->push(Process::result('added'))
        ->push(Process::result('', '', 0))
        ->push(Process::result('pushed'));

    Process::fake(fn () => $sequence());

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $this->actingAs(User::factory()->create())
        ->get('/push')
        ->assertRedirect(config('app.server_pull_url'));

    Process::assertRanTimes(fn (): bool => true, 3);
    Process::assertNotRan(fn ($process): bool => is_array($process->command)
        && in_array('commit', $process->command, true));
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('push', $process->command, true));
});

it('does not redirect to deployment after a failed push despite up-to-date output', function (): void {
    $sequence = Process::sequence()
        ->push(Process::result('added'))
        ->push(Process::result('', '', 0))
        ->push(Process::result('Everything up-to-date', 'error: RPC failed; HTTP 408', 1));

    Process::fake(fn () => $sequence());
    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $this->actingAs(User::factory()->create())->get('/push')
        ->assertStatus(500)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('command', 'git push')
        ->assertJsonPath('exit_code', 1);

    Process::assertRanTimes(fn (): bool => true, 3);
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
