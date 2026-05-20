<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

it('allows the deployment pull route with the configured token', function (): void {
    Process::fake([
        'git pull' => Process::result('Already up to date.'),
    ]);

    $this->get('/deployment/pull/'.config('app.deployment_pull_token'))
        ->assertOk()
        ->assertJsonPath('message', 'Successfully pulled latest changes')
        ->assertJsonPath('output', 'Already up to date.');

    Process::assertRan(fn ($process): bool => $process->command === 'git pull');
});

it('rejects the deployment pull route when the token is invalid', function (): void {
    Process::fake();

    $this->get('/deployment/pull/wrong-token')
        ->assertForbidden();

    Process::assertNothingRan();
});

it('calls the server pull url even when there are no local changes to push', function (): void {
    $sequence = Process::sequence()
        ->push(Process::result('added'))
        ->push(Process::result('', '', 0));

    Process::fake(fn () => $sequence());
    Http::fake([
        config('app.server_pull_url') => Http::response(['message' => 'Successfully pulled latest changes']),
    ]);

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $this->actingAs(User::factory()->create())
        ->get('/push')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('push_status', 'no_changes')
        ->assertJsonPath('steps.2.name', 'server_http_pull');

    Process::assertRanTimes(fn (): bool => true, 2);
    Process::assertNotRan(fn ($process): bool => is_array($process->command)
        && in_array('commit', $process->command, true));
    Process::assertNotRan(fn ($process): bool => is_array($process->command)
        && in_array('push', $process->command, true));
    Http::assertSent(fn (Request $request): bool => $request->url() === config('app.server_pull_url'));
});

it('commits pushes and then calls the server pull url when local changes exist', function (): void {
    $sequence = Process::sequence()
        ->push(Process::result('added'))
        ->push(Process::result('', '', 1))
        ->push(Process::result('[main abc123] Auto-update'))
        ->push(Process::result('pushed'));

    Process::fake(fn () => $sequence());
    Http::fake([
        config('app.server_pull_url') => Http::response(['message' => 'Successfully pulled latest changes']),
    ]);

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $this->actingAs(User::factory()->create())
        ->get('/push')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('push_status', 'pushed')
        ->assertJsonPath('steps.3.name', 'local_git_push')
        ->assertJsonPath('steps.4.name', 'server_http_pull');

    Process::assertRanTimes(fn (): bool => true, 4);
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('commit', $process->command, true));
    Process::assertRan(fn ($process): bool => is_array($process->command)
        && in_array('push', $process->command, true));
    Http::assertSent(fn (Request $request): bool => $request->url() === config('app.server_pull_url'));
});
