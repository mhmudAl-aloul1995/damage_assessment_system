<?php

namespace App\Modules\Reh\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

class RehSyncWorker
{
    public function start(): bool
    {
        $lock = Cache::lock('reh:worker:launch', 15);
        if (! $lock->get()) {
            return true;
        }

        try {
            $php = config('reh.worker_php_binary') ?: (new PhpExecutableFinder)->find(false);
            if (! is_string($php) || ! is_file($php) || str_contains(strtolower(basename($php)), 'cgi')) {
                $lock->release();

                return false;
            }

            $result = Process::path(base_path())->timeout(10)->run($this->command($php));
            if (! $result->successful()) {
                $lock->release();
            }

            return $result->successful();
        } catch (Throwable $exception) {
            $lock->release();
            report($exception);

            return false;
        }
    }

    /** @return list<string> */
    private function command(string $php): array
    {
        $log = storage_path('logs/reh-worker.log');
        if (PHP_OS_FAMILY === 'Windows') {
            $quote = fn (string $value): string => "'".str_replace("'", "''", $value)."'";
            $arguments = '"'.base_path('artisan').'" phc:queue-work-reh --no-interaction';
            $script = '$ErrorActionPreference = \'Stop\'; Start-Process -FilePath '.$quote($php)
                .' -ArgumentList '.$quote($arguments).' -WorkingDirectory '.$quote(base_path())
                .' -WindowStyle Hidden -RedirectStandardOutput '.$quote($log)
                .' -RedirectStandardError '.$quote(storage_path('logs/reh-worker-error.log'))
                .' -PassThru | Select-Object -ExpandProperty Id';

            return ['powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'))];
        }

        return ['sh', '-c', 'nohup '.escapeshellarg($php).' '.escapeshellarg(base_path('artisan'))
            .' phc:queue-work-reh --no-interaction >> '.escapeshellarg($log).' 2>&1 < /dev/null &'];
    }
}
