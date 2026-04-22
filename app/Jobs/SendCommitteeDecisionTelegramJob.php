<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CommitteeDecision;
use App\services\CommitteeDecisionWorkflowService;
use App\services\Messaging\MessagingProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCommitteeDecisionTelegramJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $committeeDecisionId) {}

    public function handle(CommitteeDecisionWorkflowService $workflowService, MessagingProvider $messagingProvider): void
    {
        $decision = CommitteeDecision::query()->with('decisionable')->find($this->committeeDecisionId);

        if ($decision === null) {
            return;
        }

        $recipient = $workflowService->resolveAssignedEngineer($decision);

        $workflowService->markTelegramResult(
            $decision,
            $messagingProvider->sendCommitteeDecision($decision, $recipient),
        );
    }
}
