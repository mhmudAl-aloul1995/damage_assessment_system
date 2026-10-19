<?php

use App\Modules\Reh\Services\RehSyncWorker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Cache::flush();
    config(['reh.worker_php_binary' => PHP_BINARY]);
});

it('launches a detached dedicated worker and throttles repeated clicks', function (): void {
    Process::fake();
    expect(app(RehSyncWorker::class)->start())->toBeTrue()
        ->and(app(RehSyncWorker::class)->start())->toBeTrue();
    Process::assertRanTimes(fn ($process): bool => true, 1);
    Process::assertRan(function ($process): bool {
        $command = $process->command;
        if (PHP_OS_FAMILY === 'Windows') {
            $script = mb_convert_encoding(base64_decode($command[4]), 'UTF-8', 'UTF-16LE');

            return $command[0] === 'powershell.exe'
                && str_contains($script, '-WindowStyle Hidden')
                && str_contains($script, 'phc:queue-work-reh --no-interaction')
                && str_contains($script, PHP_BINARY)
                && str_contains($script, 'reh-worker-error.log');
        }

        return $command[0] === 'sh' && str_contains($command[2], 'nohup') && str_contains($command[2], 'phc:queue-work-reh');
    });
});

it('allows immediate retry when the operating system cannot launch the worker', function (): void {
    Process::fake(['*' => Process::sequence()->push(Process::result(exitCode: 1))->push(Process::result(exitCode: 0))]);
    expect(app(RehSyncWorker::class)->start())->toBeFalse()
        ->and(app(RehSyncWorker::class)->start())->toBeTrue();
    Process::assertRanTimes(fn ($process): bool => true, 2);
});

it('rejects missing or CGI PHP executables instead of claiming the worker started', function (string $binary): void {
    Process::fake();
    config(['reh.worker_php_binary' => $binary]);
    expect(app(RehSyncWorker::class)->start())->toBeFalse();
    Process::assertNothingRan();
})->with(['Z:/nonexistent/reh-php.exe', 'Z:/nonexistent/php-cgi.exe']);

it('starts a real detached process using an isolated artisan fixture', function (): void {
    $originalBase = base_path();
    $originalStorage = storage_path();
    $sandbox = storage_path('framework/testing/reh-worker-'.Str::uuid());
    File::ensureDirectoryExists($sandbox.'/logs');
    File::put($sandbox.'/artisan', '<?php file_put_contents(__DIR__."/started", json_encode($argv)); usleep(500000); file_put_contents(__DIR__."/finished", "ok");');

    try {
        app()->setBasePath($sandbox);
        app()->useStoragePath($sandbox);
        expect(app(RehSyncWorker::class)->start())->toBeTrue();
        $deadline = microtime(true) + 8;
        while (! is_file($sandbox.'/finished') && microtime(true) < $deadline) {
            usleep(100000);
        }
        expect(is_file($sandbox.'/finished'))->toBeTrue();
        $arguments = json_decode(File::get($sandbox.'/started'), true);
        expect($arguments)->toContain('phc:queue-work-reh', '--no-interaction');
    } finally {
        app()->setBasePath($originalBase);
        app()->useStoragePath($originalStorage);
        File::deleteDirectory($sandbox);
    }
});
