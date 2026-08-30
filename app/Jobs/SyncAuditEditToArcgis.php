<?php

namespace App\Jobs;

use App\services\ArcgisAuditedUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAuditEditToArcgis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public string $type,
        public string $globalId,
        public string $fieldName,
        public mixed $fieldValue,
    ) {}

    public function handle(ArcgisAuditedUploadService $arcgisAuditedUploadService): void
    {
        $arcgisAuditedUploadService->syncAuditEditField(
            $this->type,
            $this->globalId,
            $this->fieldName,
            $this->fieldValue,
        );
    }
}
