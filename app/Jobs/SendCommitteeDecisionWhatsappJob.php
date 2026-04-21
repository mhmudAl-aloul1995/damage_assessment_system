<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CommitteeDecision;
use App\services\CommitteeDecisionWorkflowService;
use App\services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendCommitteeDecisionWhatsappJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $committeeDecisionId) {}

    public function handle(CommitteeDecisionWorkflowService $workflowService, WhatsAppService $whatsAppService): void
    {
        $decision = CommitteeDecision::query()->with('decisionable')->find($this->committeeDecisionId);

        if ($decision === null) {
            return;
        }

        $recipient = $workflowService->resolveAssignedEngineer($decision);

        if ($recipient === null || blank($recipient->phone)) {
            $workflowService->markWhatsappResult($decision, [
                'success' => false,
                'status' => 'missing_phone',
                'message' => 'Field engineer phone is missing.',
            ]);

            Log::warning('Committee WhatsApp skipped because the engineer phone was not found.', [
                'committee_decision_id' => $decision->id,
            ]);

            return;
        }

        $workflowService->markWhatsappResult(
            $decision,
            $whatsAppService->sendCommitteeDecision($decision, $recipient),
        );
    }
}
