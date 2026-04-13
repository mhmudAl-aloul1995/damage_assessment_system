<?php

use App\Console\Commands\BackupDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

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

it('prefers the configured mariadb dump binary path even for mysql driver', function () {
    config()->set('database_backup.mariadb_dump_binary', 'C:\\Program Files\\MariaDB 12.1\\bin\\mariadb-dump.exe');
    config()->set('database_backup.mysqldump_binary', 'C:\\mysql\\bin\\mysqldump.exe');

    $command = app(BackupDatabase::class);
    $method = new ReflectionMethod($command, 'resolveMySqlDumpBinary');
    $method->setAccessible(true);

    expect($method->invoke($command, 'mysql'))->toBe('C:\\Program Files\\MariaDB 12.1\\bin\\mariadb-dump.exe');
    expect($method->invoke($command, 'mariadb'))->toBe('C:\\Program Files\\MariaDB 12.1\\bin\\mariadb-dump.exe');
});

it('returns a failure code when the dump process fails', function () {
    Process::fake([
        '*' => Process::result('', 'dump failed', 1),
    ]);

    config()->set('database_backup.connection', 'mysql');
    config()->set('database.connections.mysql', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'phc',
        'username' => 'root',
        'password' => 'secret',
    ]);

    $exitCode = Artisan::call('app:backup-database');

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('dump failed');
});

it('registers the database backup command in the scheduler', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('app:backup-database');
});
