<?php

namespace App\Jobs;

use App\services\ArcgisAuditedUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAssessmentAssignmentToSourceArcgis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public string $type,
        public string $globalId,
        public mixed $fieldValue,
    ) {}

    public function handle(ArcgisAuditedUploadService $arcgisAuditedUploadService): void
    {
        $arcgisAuditedUploadService->syncSourceAssessmentAssignment(
            $this->type,
            $this->globalId,
            $this->fieldValue,
        );
    }
}
