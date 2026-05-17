<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\LocalDatabaseImportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LocalDatabaseImportController extends Controller
{
    public function index(): View
    {
        $connectionName = $this->connectionName();
        $connection = config("database.connections.{$connectionName}", []);

        return view('admin.local_database_import', [
            'connectionName' => $connectionName,
            'connection' => $connection,
            'sqlFiles' => $this->availableSqlFiles(),
        ]);
    }

    public function store(LocalDatabaseImportRequest $request): RedirectResponse
    {
        abort_unless(app()->environment('local', 'testing'), 403);

        $connectionName = $this->connectionName();
        $connection = config("database.connections.{$connectionName}", []);

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            return back()->with('error', 'Local database import is only available for MySQL or MariaDB connections.');
        }

        $storedPath = null;
        $absolutePath = null;

        try {
            if ($request->input('import_source') === 'local_path') {
                $absolutePath = $this->validatedLocalSqlPath((string) $request->input('local_path'));
            } else {
                $storedPath = $request->file('sql_file')->storeAs(
                    'database-imports',
                    uniqid('import_', true).'.'.$request->file('sql_file')->getClientOriginalExtension()
                );

                if (! is_string($storedPath)) {
                    return back()->with('error', 'Unable to store the uploaded SQL file.');
                }

                $absolutePath = Storage::path($storedPath);
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! is_string($absolutePath)) {
            return back()->with('error', 'Unable to resolve the SQL import file.');
        }

        try {
            $result = $this->runMysqlImport($connection, $absolutePath);
        } finally {
            if ($storedPath !== null) {
                Storage::delete($storedPath);
            }
        }

        if (! $result->successful()) {
            return back()->with('error', trim($result->errorOutput()) ?: 'The database import failed.');
        }

        return back()->with('success', 'Database import completed successfully.');
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function runMysqlImport(array $connection, string $absolutePath): \Illuminate\Contracts\Process\ProcessResult
    {
        $file = fopen($absolutePath, 'r');

        if ($file === false) {
            throw new RuntimeException('Unable to read the uploaded SQL file.');
        }

        try {
            return Process::forever()
                ->env(array_filter([
                    'MYSQL_PWD' => $connection['password'] ?? null,
                ], fn (mixed $value): bool => $value !== null && $value !== ''))
                ->input($file)
                ->run($this->mysqlCommand($connection));
        } finally {
            fclose($file);
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array<int, string>
     */
    private function mysqlCommand(array $connection): array
    {
        $command = [
            $this->mysqlBinaryPath(),
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.(string) ($connection['username'] ?? 'root'),
            '--default-character-set='.($connection['charset'] ?? 'utf8mb4'),
        ];

        if (! empty($connection['unix_socket'])) {
            $command[] = '--socket='.(string) $connection['unix_socket'];
        }

        $command[] = (string) ($connection['database'] ?? '');

        return $command;
    }

    private function mysqlBinaryPath(): string
    {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0\\bin\\mysql.exe',
            'C:\\laragon\\bin\\mysql\\mysql-5.7\\bin\\mysql.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'mysql';
    }

    private function connectionName(): string
    {
        return (string) config('database.local_import_connection', config('database.default'));
    }

    /**
     * @return array<int, array{name: string, path: string, size: string}>
     */
    private function availableSqlFiles(): array
    {
        return collect(glob(base_path('*.sql')) ?: [])
            ->filter(fn (string $path): bool => is_file($path))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'path' => str_replace('\\', '/', $path),
                'size' => number_format(filesize($path) / 1024 / 1024, 2).' MB',
            ])
            ->values()
            ->all();
    }

    private function validatedLocalSqlPath(string $path): string
    {
        $path = trim($path);
        $resolvedPath = realpath($path);

        if ($resolvedPath === false && ! Str::contains($path, ['\\', '/'])) {
            $resolvedPath = realpath(base_path($path));
        }

        if ($resolvedPath === false || ! is_file($resolvedPath)) {
            throw new RuntimeException('The selected local SQL file does not exist.');
        }

        if (! in_array(strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION)), ['sql', 'txt'], true)) {
            throw new RuntimeException('The selected local file must be a .sql or .txt file.');
        }

        return $resolvedPath;
    }
}
