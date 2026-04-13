<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database {--connection=}';

    protected $description = 'Create a backup copy of the configured database';

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?: config('database_backup.connection', config('database.default'));
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            $this->error("Database connection [{$connectionName}] is not configured.");

            return self::FAILURE;
        }

        $connection = $this->resolveBackupConnection($connection);

        $backupDirectory = storage_path(trim(config('database_backup.path', 'backups/database'), '/\\'));
        File::ensureDirectoryExists($backupDirectory);

        $timestamp = now()->format('Y-m-d_H-i-s');
        $driver = $connection['driver'] ?? 'unknown';

        try {
            $backupPath = match ($driver) {
                'sqlite' => $this->backupSqlite($connection, $connectionName, $backupDirectory, $timestamp),
                'mysql', 'mariadb' => $this->backupMySql($connection, $connectionName, $backupDirectory, $timestamp, $driver),
                'pgsql' => $this->backupPostgres($connection, $connectionName, $backupDirectory, $timestamp),
                default => throw new \RuntimeException("Database driver [{$driver}] is not supported for backups."),
            };
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Database backup created: {$backupPath}");

        return self::SUCCESS;
    }

    private function resolveBackupConnection(array $connection): array
    {
        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            $override = config("database_backup.{$key}");

            if ($override !== null && $override !== '') {
                $connection[$key] = $override;
            }
        }

        return $connection;
    }

    private function backupSqlite(array $connection, string $connectionName, string $backupDirectory, string $timestamp): string
    {
        $databasePath = $connection['database'] ?? null;

        if (! $databasePath) {
            throw new \RuntimeException("SQLite database path is not configured for [{$connectionName}].");
        }

        if (! File::exists($databasePath)) {
            throw new \RuntimeException("SQLite database file was not found at [{$databasePath}].");
        }

        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR."{$connectionName}_{$timestamp}.sqlite";
        File::copy($databasePath, $backupPath);

        return $backupPath;
    }

    private function backupMySql(array $connection, string $connectionName, string $backupDirectory, string $timestamp, string $driver): string
    {
        $dumpBinary = $this->resolveMySqlDumpBinary($driver);

        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR."{$connectionName}_{$timestamp}.sql";

        $arguments = [
            $dumpBinary,
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '3306'),
            '--user='.($connection['username'] ?? 'root'),
            '--skip-comments',
            '--single-transaction',
            '--quick',
            '--result-file='.$backupPath,
            $connection['database'] ?? '',
        ];

        if (! empty($connection['password'])) {
            $arguments[] = '--password='.$connection['password'];
        }

        $result = Process::timeout(300)->run($arguments);
        $result->throw();

        return $backupPath;
    }

    private function resolveMySqlDumpBinary(string $driver): string
    {
        $mariaDumpBinary = $this->resolveBinaryPath('mariadb_dump_binary');
        $mysqlDumpBinary = $this->resolveBinaryPath('mysqldump_binary');

        if ($driver === 'mariadb') {
            return $mariaDumpBinary ?? 'mariadb-dump';
        }

        return $mariaDumpBinary ?? $mysqlDumpBinary ?? 'mysqldump';
    }

    private function backupPostgres(array $connection, string $connectionName, string $backupDirectory, string $timestamp): string
    {
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR."{$connectionName}_{$timestamp}.sql";
        $dumpBinary = $this->resolveBinaryPath('pg_dump_binary') ?? 'pg_dump';

        $arguments = [
            $dumpBinary,
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '5432'),
            '--username='.($connection['username'] ?? 'postgres'),
            '--file='.$backupPath,
            $connection['database'] ?? '',
        ];

        $environment = [];

        if (! empty($connection['password'])) {
            $environment['PGPASSWORD'] = $connection['password'];
        }

        $result = Process::timeout(300)
            ->env($environment)
            ->run($arguments);

        $result->throw();

        return $backupPath;
    }

    private function resolveBinaryPath(string $configKey): ?string
    {
        $binary = config("database_backup.{$configKey}");

        if (! is_string($binary) || trim($binary) === '') {
            return null;
        }

        return trim($binary);
    }
}
