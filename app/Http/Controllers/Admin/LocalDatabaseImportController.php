<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\LocalDatabaseImportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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

        $path = $request->file('sql_file')->storeAs(
            'database-imports',
            uniqid('import_', true).'.'.$request->file('sql_file')->getClientOriginalExtension()
        );

        if (! is_string($path)) {
            return back()->with('error', 'Unable to store the uploaded SQL file.');
        }

        $absolutePath = Storage::path($path);

        try {
            $result = $this->runMysqlImport($connection, $absolutePath);
        } finally {
            Storage::delete($path);
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
}
