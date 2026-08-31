<?php

namespace App\Jobs;

use App\Services\BuildingDeletion\BuildingDeletionProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBuildingDeletionRequest implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $requestId)
    {
        $this->onQueue('arcgis');
    }

    /**
     * Execute the job.
     */
    public function handle(BuildingDeletionProcessor $processor): void
    {
        $processor->process($this->requestId);
    }
}
