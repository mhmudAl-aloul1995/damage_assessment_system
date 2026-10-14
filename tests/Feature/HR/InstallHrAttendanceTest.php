<?php

use App\Modules\HR\Services\AttendanceInstaller;
use App\Support\ArtisanCommandCatalog;
use Illuminate\Support\Facades\Artisan;

it('takes a backup of the HR connection before migrating only HR and restoring backup overrides', function (): void {
    config(['database_backup.host' => 'backup-only.example.test', 'database_backup.password' => 'fake-backup-secret']);
    Artisan::shouldReceive('call')->once()->ordered()->with('app:backup-database', ['--connection' => 'sqlite', '--no-interaction' => true])
        ->andReturnUsing(function (): int {
            expect(config('database_backup.host'))->toBeNull()->and(config('database_backup.password'))->toBeNull();

            return 0;
        });
    Artisan::shouldReceive('call')->once()->ordered()->with('migrate', [
        '--database' => 'sqlite', '--path' => ['app/Modules/HR/database/migrations'], '--force' => true, '--no-interaction' => true,
    ])->andReturn(0);
    Artisan::shouldReceive('call')->once()->ordered()->with('db:seed', ['--class' => 'Database\\Seeders\\HrPermissionsSeeder', '--force' => true, '--no-interaction' => true])->andReturn(0);
    Artisan::shouldReceive('call')->once()->ordered()->with('db:seed', ['--class' => 'Database\\Seeders\\HrContractTypesSeeder', '--force' => true, '--no-interaction' => true])->andReturn(0);
    Artisan::shouldReceive('call')->once()->ordered()->with('route:clear', ['--no-interaction' => true])->andReturn(0);
    Artisan::shouldReceive('call')->once()->ordered()->with('config:clear', ['--no-interaction' => true])->andReturn(0);
    app(AttendanceInstaller::class)->install();
    expect(config('database_backup.host'))->toBe('backup-only.example.test')
        ->and(config('database_backup.password'))->toBe('fake-backup-secret');
});

it('does not migrate anything when the backup fails', function (): void {
    config(['database_backup.host' => 'backup-only.example.test']);
    Artisan::shouldReceive('call')->once()->with('app:backup-database', ['--connection' => 'sqlite', '--no-interaction' => true])->andReturn(1);
    expect(fn () => app(AttendanceInstaller::class)->install())->toThrow(RuntimeException::class, 'فشل النسخ الاحتياطي');
    expect(config('database_backup.host'))->toBe('backup-only.example.test');
});

it('rejects skipping a backup in production before any operation occurs', function (): void {
    Artisan::shouldReceive('call')->never();
    app()->instance('env', 'production');
    try {
        expect(fn () => app(AttendanceInstaller::class)->install(true))->toThrow(RuntimeException::class);
    } finally {
        app()->instance('env', 'testing');
    }
});

it('offers a dedicated installer through the protected administration catalog', function (): void {
    expect(app(ArtisanCommandCatalog::class)->find('hr:install-attendance')['can_run'])->toBeTrue();
});
