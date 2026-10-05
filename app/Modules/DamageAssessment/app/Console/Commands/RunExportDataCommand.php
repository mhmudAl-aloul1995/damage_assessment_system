<?php

namespace App\Console\Commands;

use App\Jobs\ExportDataJob;
use Illuminate\Console\Command;

class RunExportDataCommand extends Command
{
    protected $signature = 'exports:run {exportId : The export record id}';

    protected $description = 'Run a pending data export by id.';

    public function handle(): int
    {
        $exportId = (int) $this->argument('exportId');

        if ($exportId <= 0) {
            $this->error('A valid export id is required.');

            return self::FAILURE;
        }

        (new ExportDataJob($exportId))->handle();

        return self::SUCCESS;
    }
}
