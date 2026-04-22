<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CommitteeDecision;
use App\services\CommitteeDecisionWorkflowService;
use App\services\Messaging\MessagingProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

        $defaultChatId = config('services.telegram.chat_id') ?: config('services.committee_decisions.telegram.chat_id');

        if ($recipient === null && blank($defaultChatId)) {
            $workflowService->markTelegramResult($decision, [
                'success' => false,
                'status' => 'missing_chat_id',
                'message' => 'Telegram chat id is missing for both the user and the default target.',
            ]);

            Log::warning('Committee Telegram skipped because no target chat id was found.', [
                'committee_decision_id' => $decision->id,
            ]);

            return;
        }

        if ($recipient !== null && blank($recipient->telegram_chat_id) && blank($defaultChatId)) {
            $workflowService->markTelegramResult($decision, [
                'success' => false,
                'status' => 'missing_chat_id',
                'message' => 'Field engineer Telegram chat id is missing.',
            ]);

            Log::warning('Committee Telegram skipped because the engineer Telegram chat id was not found.', [
                'committee_decision_id' => $decision->id,
            ]);

            return;
        }

        $workflowService->markTelegramResult(
            $decision,
            $messagingProvider->sendCommitteeDecision($decision, $recipient),
        );
    }
}
