<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\Models\Role;

it('shows the local database import page to database officers', function (): void {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.local-database-import.index'))
        ->assertOk()
        ->assertSee('Local Database Import')
        ->assertSee('Target Connection');
});

it('streams an uploaded sql file into the configured mysql database', function (): void {
    Storage::fake('local');
    Process::fake([
        '*' => Process::result('Import complete.'),
    ]);

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $user = User::factory()->create();

    config()->set('database.local_import_connection', 'testing_mysql');
    config()->set('database.connections.testing_mysql', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'phc_testing',
        'username' => 'root',
        'password' => 'secret',
        'charset' => 'utf8mb4',
    ]);

    $file = UploadedFile::fake()->createWithContent('local.sql', 'CREATE TABLE imported_items (id INT);');

    $this->actingAs($user)
        ->post(route('admin.local-database-import.store'), [
            'import_source' => 'upload',
            'sql_file' => $file,
            'confirm_database' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Database import completed successfully.');

    Process::assertRan(function ($process): bool {
        return is_array($process->command)
            && in_array('--host=127.0.0.1', $process->command, true)
            && in_array('--user=root', $process->command, true)
            && in_array('phc_testing', $process->command, true)
            && ($process->environment['MYSQL_PWD'] ?? null) === 'secret';
    });
});

it('requires confirmation before importing a database dump', function (): void {
    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('local.sql', 'SELECT 1;');

    $this->actingAs($user)
        ->post(route('admin.local-database-import.store'), [
            'import_source' => 'upload',
            'sql_file' => $file,
        ])
        ->assertSessionHasErrors('confirm_database');
});

it('imports a sql file from a local project path without uploading it', function (): void {
    Process::fake([
        '*' => Process::result('Import complete.'),
    ]);

    $this->withoutMiddleware(RoleOrPermissionMiddleware::class);

    $user = User::factory()->create();
    $path = base_path('local-import-test.sql');

    config()->set('database.local_import_connection', 'testing_mysql');
    config()->set('database.connections.testing_mysql', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'phc_testing',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ]);

    file_put_contents($path, 'SELECT 1;');

    try {
        $this->actingAs($user)
            ->post(route('admin.local-database-import.store'), [
                'import_source' => 'local_path',
                'local_path' => $path,
                'confirm_database' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Database import completed successfully.');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }

    Process::assertRan(fn ($process): bool => is_array($process->command));
});
