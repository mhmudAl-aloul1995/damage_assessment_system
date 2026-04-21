<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CommitteeDecision;
use App\services\ArcGisStatusUpdaterService;
use App\services\CommitteeDecisionWorkflowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCommitteeDecisionArcGisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(public int $committeeDecisionId) {}

    public function handle(
        CommitteeDecisionWorkflowService $workflowService,
        ArcGisStatusUpdaterService $arcGisStatusUpdaterService,
    ): void {
        $decision = CommitteeDecision::query()->with('decisionable')->find($this->committeeDecisionId);

        if ($decision === null) {
            return;
        }

        $workflowService->markArcGisResult(
            $decision,
            $arcGisStatusUpdaterService->syncDecisionStatus($decision),
        );
    }
}
