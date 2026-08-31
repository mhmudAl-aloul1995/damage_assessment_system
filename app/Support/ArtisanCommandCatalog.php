<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ArtisanCommandCatalog
{
    /**
     * @var array<int, string>
     */
    private const BLOCKED_COMMAND_PATTERNS = [
        'delete',
        'rollback',
        'restore',
        'wipe',
        'fresh',
        'seed',
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_ROUTE_CONSOLE_COMMANDS = [
        'phc:queue-work-arcgis',
    ];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function commands(): Collection
    {
        return collect(Artisan::all())
            ->map(fn (Command $command): ?array => $this->toCatalogItem($command))
            ->filter()
            ->sortBy('name')
            ->values();
    }

    public function find(string $name): ?array
    {
        return $this->commands()
            ->first(fn (array $command): bool => $command['name'] === $name);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $options
     * @return array<string, string>|false
     */
    public function runInBackground(string $name, array $arguments = [], array $options = []): array|false
    {
        $command = $this->find($name);

        if ($command === null || ! $command['can_run']) {
            return false;
        }

        $artisanArguments = $this->artisanArguments($command, $arguments, $options);

        if ($artisanArguments === null) {
            return false;
        }

        $runId = (string) Str::uuid();
        $paths = $this->runPaths($runId);

        File::ensureDirectoryExists($paths['directory']);
        File::put($paths['log'], '['.now()->toDateTimeString()."] Starting command...\n");
        File::put($paths['meta'], json_encode([
            'command' => $name,
            'preview' => $this->previewCommand($artisanArguments),
            'started_at' => now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $scriptPath = $this->writeRunScript($artisanArguments, $paths);
        $result = Process::path(base_path())->run($this->backgroundCommand($scriptPath));

        if (! $result->successful()) {
            File::put($paths['exit'], '1');
            File::append($paths['log'], "\nCould not start the background process.\n".($result->errorOutput() ?: $result->output()));

            return false;
        }

        return [
            'run_id' => $runId,
            'preview' => $this->previewCommand($artisanArguments),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function runStatus(string $runId): ?array
    {
        if (! preg_match('/^[a-f0-9-]{36}$/i', $runId)) {
            return null;
        }

        $paths = $this->runPaths($runId);

        if (! File::exists($paths['meta'])) {
            return null;
        }

        $meta = json_decode((string) File::get($paths['meta']), true) ?: [];
        $exitCode = File::exists($paths['exit']) ? trim((string) File::get($paths['exit'])) : null;
        $status = $exitCode === null ? 'running' : ((int) $exitCode === 0 ? 'success' : 'failed');

        return [
            'run_id' => $runId,
            'status' => $status,
            'exit_code' => $exitCode !== null ? (int) $exitCode : null,
            'command' => $meta['command'] ?? '',
            'preview' => $meta['preview'] ?? '',
            'started_at' => $meta['started_at'] ?? null,
            'output' => $this->tailFile($paths['log']),
        ];
    }

    private function toCatalogItem(Command $command): ?array
    {
        $reflection = new ReflectionClass($command);
        $fileName = $reflection->getFileName();
        $isAllowedRouteConsoleCommand = in_array($command->getName(), self::ALLOWED_ROUTE_CONSOLE_COMMANDS, true);

        if (! $isAllowedRouteConsoleCommand && (! is_string($fileName) || ! str_starts_with($this->normalizePath($fileName), $this->commandsPath()))) {
            return null;
        }

        $catalogFileName = $isAllowedRouteConsoleCommand ? base_path('routes/console.php') : $fileName;

        $definition = $command->getDefinition();
        $arguments = collect($definition->getArguments())
            ->map(fn (InputArgument $argument): array => [
                'name' => $argument->getName(),
                'required' => $argument->isRequired(),
                'description' => $argument->getDescription(),
                'is_array' => $argument->isArray(),
            ])
            ->values();
        $options = collect($definition->getOptions())
            ->reject(fn (InputOption $option): bool => in_array($option->getName(), ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'], true))
            ->map(fn (InputOption $option): array => [
                'name' => $option->getName(),
                'description' => $option->getDescription(),
                'accepts_value' => $option->acceptValue(),
                'value_required' => $option->isValueRequired(),
                'is_array' => $option->isArray(),
            ])
            ->values();
        $requiredArguments = $arguments->filter(fn (array $argument): bool => $argument['required'])->values();
        $isBlocked = $this->isBlockedCommand($command->getName(), (string) $command->getDescription());

        return [
            'name' => $command->getName(),
            'full_command' => 'php artisan '.$command->getName(),
            'description' => $command->getDescription() ?: __('ui.artisan_commands.no_description'),
            'class' => $isAllowedRouteConsoleCommand ? 'routes/console.php' : $reflection->getShortName(),
            'file' => $this->normalizePath($catalogFileName),
            'arguments' => $arguments,
            'options' => $options,
            'can_run' => ! $isBlocked,
            'disabled_reason' => $this->disabledReason($requiredArguments, $isBlocked),
        ];
    }

    private function disabledReason(Collection $requiredArguments, bool $isBlocked): ?string
    {
        if ($isBlocked) {
            return __('ui.artisan_commands.blocked_dangerous');
        }

        return null;
    }

    private function isBlockedCommand(string $name, string $description): bool
    {
        $text = strtolower($name.' '.$description);

        return collect(self::BLOCKED_COMMAND_PATTERNS)
            ->contains(fn (string $pattern): bool => str_contains($text, $pattern));
    }

    /**
     * @return array<int, string>
     */
    private function artisanArguments(array $command, array $arguments, array $options): ?array
    {
        $segments = [$command['name']];
        $allowedArguments = collect($command['arguments'])->keyBy('name');
        $allowedOptions = collect($command['options'])->keyBy('name');

        foreach ($allowedArguments as $argument) {
            $value = $arguments[$argument['name']] ?? null;

            if ($argument['required'] && blank($value)) {
                return null;
            }

            if (blank($value)) {
                continue;
            }

            if ($argument['is_array']) {
                foreach ((array) $value as $item) {
                    if (! blank($item)) {
                        $segments[] = (string) $item;
                    }
                }

                continue;
            }

            $segments[] = (string) $value;
        }

        foreach ($options as $name => $value) {
            $option = $allowedOptions->get($name);

            if ($option === null || blank($value)) {
                continue;
            }

            if (! $option['accepts_value']) {
                if (filter_var($value, FILTER_VALIDATE_BOOL)) {
                    $segments[] = '--'.$name;
                }

                continue;
            }

            foreach ((array) $value as $item) {
                if (! blank($item)) {
                    $segments[] = '--'.$name.'='.(string) $item;
                }
            }
        }

        $segments[] = '-vvv';
        $segments[] = '--no-interaction';

        return $segments;
    }

    /**
     * @return array<string, string>
     */
    private function runPaths(string $runId): array
    {
        $directory = storage_path('app/artisan-command-runs/'.$runId);

        return [
            'directory' => $directory,
            'log' => $directory.'/output.log',
            'exit' => $directory.'/exit.code',
            'meta' => $directory.'/meta.json',
            'script' => $directory.(PHP_OS_FAMILY === 'Windows' ? '/run.bat' : '/run.sh'),
        ];
    }

    /**
     * @param  array<int, string>  $artisanArguments
     */
    private function writeRunScript(array $artisanArguments, array $paths): string
    {
        $command = $this->scriptCommand($artisanArguments);

        if (PHP_OS_FAMILY === 'Windows') {
            File::put($paths['script'], implode("\r\n", [
                '@echo off',
                'cd /d '.$this->windowsQuote(base_path()),
                'echo ['.$this->windowsDateExpression().'] Running: '.$this->previewCommand($artisanArguments).' >> '.$this->windowsQuote($paths['log']),
                $command.' >> '.$this->windowsQuote($paths['log']).' 2>&1',
                'echo %ERRORLEVEL% > '.$this->windowsQuote($paths['exit']),
            ])."\r\n");

            return $paths['script'];
        }

        File::put($paths['script'], implode("\n", [
            '#!/usr/bin/env sh',
            'cd '.escapeshellarg(base_path()),
            'echo "['.'$(date "+%Y-%m-%d %H:%M:%S")'.'] Running: '.$this->previewCommand($artisanArguments).'" >> '.escapeshellarg($paths['log']),
            $command.' >> '.escapeshellarg($paths['log']).' 2>&1',
            'echo $? > '.escapeshellarg($paths['exit']),
        ])."\n");
        @chmod($paths['script'], 0755);

        return $paths['script'];
    }

    /**
     * @param  array<int, string>  $artisanArguments
     */
    private function scriptCommand(array $artisanArguments): string
    {
        $escapedArguments = collect($artisanArguments)
            ->map(fn (string $argument): string => PHP_OS_FAMILY === 'Windows' ? $this->windowsQuote($argument) : escapeshellarg($argument))
            ->join(' ');

        $phpBinary = PHP_OS_FAMILY === 'Windows' ? $this->windowsQuote(PHP_BINARY) : escapeshellarg(PHP_BINARY);

        return $phpBinary.' artisan '.$escapedArguments;
    }

    /**
     * @param  array<int, string>  $artisanArguments
     */
    private function previewCommand(array $artisanArguments): string
    {
        return 'php artisan '.collect($artisanArguments)
            ->reject(fn (string $argument): bool => $argument === '--no-interaction')
            ->map(fn (string $argument): string => preg_match('/^[A-Za-z0-9_\.\/:@=-]+$/', $argument) ? $argument : '"'.str_replace('"', '\"', $argument).'"')
            ->join(' ');
    }

    private function tailFile(string $path): string
    {
        if (! File::exists($path)) {
            return '';
        }

        $size = File::size($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        fseek($handle, max(0, $size - 80000));
        $output = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $output;
    }

    /**
     * @return array<int, string>
     */
    private function backgroundCommand(string $scriptPath): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'cmd',
                '/C',
                'start /B "" '.$this->windowsQuote($scriptPath),
            ];
        }

        return [
            'sh',
            '-c',
            escapeshellarg($scriptPath).' > /dev/null 2>&1 &',
        ];
    }

    private function windowsQuote(string $value): string
    {
        return '"'.str_replace('"', '\"', $value).'"';
    }

    private function windowsDateExpression(): string
    {
        return '%DATE% %TIME%';
    }

    private function commandsPath(): string
    {
        return $this->normalizePath(app_path('Console/Commands')).'/';
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
