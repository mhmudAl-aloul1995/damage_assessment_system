<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('creates a sqlite database backup file', function () {
    $databasePath = storage_path('framework/testing/backup-test.sqlite');
    $backupPath = storage_path('app/testing-backups');

    File::ensureDirectoryExists(dirname($databasePath));
    File::ensureDirectoryExists($backupPath);
    File::put($databasePath, 'sqlite-backup-content');

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $databasePath);
    config()->set('database_backup.connection', 'sqlite');
    config()->set('database_backup.path', 'app/testing-backups');

    Artisan::call('app:backup-database');

    $files = File::files($backupPath);

    expect($files)->toHaveCount(1);
    expect(File::get($files[0]->getPathname()))->toBe('sqlite-backup-content');

    File::deleteDirectory($backupPath);
    File::delete($databasePath);
});

it('registers the database backup command in the scheduler', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('app:backup-database');
});
