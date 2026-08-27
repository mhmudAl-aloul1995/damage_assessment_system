<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
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
     */
    public function runInBackground(string $name, array $arguments = [], array $options = []): bool
    {
        $command = $this->find($name);

        if ($command === null || ! $command['can_run']) {
            return false;
        }

        $artisanArguments = $this->artisanArguments($command, $arguments, $options);

        if ($artisanArguments === null) {
            return false;
        }

        $result = Process::path(base_path())->run($this->backgroundCommand($artisanArguments));

        return $result->successful();
    }

    private function toCatalogItem(Command $command): ?array
    {
        $reflection = new ReflectionClass($command);
        $fileName = $reflection->getFileName();

        if (! is_string($fileName) || ! str_starts_with($this->normalizePath($fileName), $this->commandsPath())) {
            return null;
        }

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
            'class' => $reflection->getShortName(),
            'file' => $this->normalizePath($fileName),
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

        $segments[] = '--no-interaction';

        return $segments;
    }

    /**
     * @param  array<int, string>  $artisanArguments
     * @return array<int, string>
     */
    private function backgroundCommand(array $artisanArguments): array
    {
        $escapedArguments = collect($artisanArguments)
            ->map(fn (string $argument): string => escapeshellarg($argument))
            ->join(' ');

        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'cmd',
                '/C',
                sprintf('start /B "" %s artisan %s > NUL 2>&1', escapeshellarg(PHP_BINARY), $escapedArguments),
            ];
        }

        return [
            'sh',
            '-c',
            escapeshellarg(PHP_BINARY).' artisan '.$escapedArguments.' > /dev/null 2>&1 &',
        ];
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
