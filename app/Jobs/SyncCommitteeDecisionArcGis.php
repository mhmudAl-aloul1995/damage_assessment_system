<?php

namespace App\Jobs;

use App\Models\CommitteeDecision;
use App\services\ArcGisStatusUpdaterService;
use App\services\CommitteeDecisionWorkflowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCommitteeDecisionArcGis implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public int $committeeDecisionId,
        public ?string $fieldStatus = null,
    ) {}

    public function handle(
        ArcGisStatusUpdaterService $arcGisStatusUpdaterService,
        CommitteeDecisionWorkflowService $workflowService,
    ): void {
        $decision = CommitteeDecision::query()
            ->with('decisionable')
            ->find($this->committeeDecisionId);

        if (! $decision instanceof CommitteeDecision) {
            return;
        }

        $result = $this->fieldStatus === null
            ? $arcGisStatusUpdaterService->syncDecisionStatus($decision)
            : $arcGisStatusUpdaterService->syncDecisionFieldStatus($decision, $this->fieldStatus);

        $workflowService->markArcGisResult($decision, $result);
    }
}
