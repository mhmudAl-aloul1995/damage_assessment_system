<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    File::deleteDirectory(storage_path('app/artisan-command-runs'));
});

it('shows application console commands to database officers', function (): void {
    $user = User::factory()->create();
    $databaseOfficer = Role::findOrCreate('Database Officer', 'web');

    $user->assignRole($databaseOfficer);

    $this->actingAs($user)
        ->get(route('admin.artisan-commands.index'))
        ->assertOk()
        ->assertSee('php artisan sync:arcgis-layers')
        ->assertSee('Sync ArcGIS layers')
        ->assertSee('data-copy-command', false)
        ->assertSee('data-run-command', false)
        ->assertSee('artisan-command-preview', false);
});

it('blocks the artisan commands page from ordinary authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.artisan-commands.index'))
        ->assertForbidden();
});

it('starts eligible commands in the background for database officers', function (): void {
    $user = User::factory()->create();
    $databaseOfficer = Role::findOrCreate('Database Officer', 'web');

    $user->assignRole($databaseOfficer);

    Process::fake([
        '*' => Process::result(),
    ]);

    $this->actingAs($user)
        ->postJson(route('admin.artisan-commands.run'), [
            'command' => 'sync:arcgis-layers',
        ])
        ->assertAccepted()
        ->assertJson([
            'message' => __('ui.artisan_commands.run_started', ['command' => 'sync:arcgis-layers']),
        ])
        ->assertJsonStructure([
            'run_id',
            'status_url',
            'preview',
        ]);

    Process::assertRan(function ($process): bool {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : $process->command;

        return str_contains($command, 'run.')
            && (str_contains($command, '.bat') || str_contains($command, '.sh'));
    });
});

it('starts eligible commands with selected arguments and options', function (): void {
    $user = User::factory()->create();
    $databaseOfficer = Role::findOrCreate('Database Officer', 'web');

    $user->assignRole($databaseOfficer);

    Process::fake([
        '*' => Process::result(),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('admin.artisan-commands.run'), [
            'command' => 'sync:arcgis-layers',
            'arguments' => [
                'table' => 'housing_units',
            ],
            'options' => [
                'chunk' => '250',
                'force' => '1',
            ],
        ])
        ->assertAccepted();

    expect($response->json('preview'))
        ->toContain('sync:arcgis-layers')
        ->toContain('housing_units')
        ->toContain('--chunk=250')
        ->toContain('--force')
        ->toContain('-vvv');

    Process::assertRan(function ($process): bool {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : $process->command;

        return str_contains($command, 'run.')
            && (str_contains($command, '.bat') || str_contains($command, '.sh'));
    });
});

it('shows live output status for a started command run', function (): void {
    $user = User::factory()->create();
    $databaseOfficer = Role::findOrCreate('Database Officer', 'web');

    $user->assignRole($databaseOfficer);

    Process::fake([
        '*' => Process::result(),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('admin.artisan-commands.run'), [
            'command' => 'sync:arcgis-layers',
            'options' => [
                'force' => '1',
            ],
        ])
        ->assertAccepted();

    $this->actingAs($user)
        ->getJson($response->json('status_url'))
        ->assertOk()
        ->assertJson([
            'run_id' => $response->json('run_id'),
            'status' => 'running',
            'exit_code' => null,
            'preview' => 'php artisan sync:arcgis-layers --force -vvv',
        ])
        ->assertJsonStructure([
            'output',
            'started_at',
        ]);
});

it('does not run blocked risky commands', function (): void {
    $user = User::factory()->create();
    $databaseOfficer = Role::findOrCreate('Database Officer', 'web');

    $user->assignRole($databaseOfficer);

    Process::fake();

    $this->actingAs($user)
        ->postJson(route('admin.artisan-commands.run'), [
            'command' => 'housing:delete-by-building-globalids',
        ])
        ->assertUnprocessable();

    Process::assertNothingRan();
});
