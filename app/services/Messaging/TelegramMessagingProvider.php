<?php

declare(strict_types=1);

namespace App\services\Messaging;

use App\Models\CommitteeDecision;
use App\Models\HousingUnit;
use App\Models\User;
use App\services\TelegramDestinationResolver;
use App\services\TelegramNotifier;
use App\services\TelegramSettingsService;
use Illuminate\Support\Facades\Log;

class TelegramMessagingProvider implements MessagingProvider
{
    public function __construct(
        private readonly TelegramSettingsService $settingsService,
        private readonly TelegramDestinationResolver $destinationResolver,
        private readonly TelegramNotifier $notifier,
    ) {}

    public function sendCommitteeDecision(CommitteeDecision $decision, ?User $recipient = null): array
    {
        if (! $this->settingsService->enabled()) {
            Log::warning('Committee Telegram is disabled or not configured.', [
                'committee_decision_id' => $decision->id,
            ]);

            return [
                'success' => false,
                'status' => 'not_configured',
                'message' => 'Telegram is disabled or not configured.',
            ];
        }

        if ($recipient === null) {
            return [
                'success' => false,
                'status' => 'missing_destination',
                'message' => 'The field engineer could not be resolved.',
            ];
        }

        $destinations = $this->destinationResolver->forRelatedModel(User::class, $recipient->id, 'notify_status_changes');

        if ($destinations->isEmpty()) {
            return [
                'success' => false,
                'status' => 'missing_destination',
                'message' => 'No connected Telegram destination was found for the field engineer.',
            ];
        }

        $result = $this->notifier->sendCollection($destinations, $this->buildCommitteeDecisionMessage($decision));

        if ($result['sent_count'] === 0) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'Telegram delivery failed for all resolved destinations.',
            ];
        }

        return [
            'success' => true,
            'status' => 'sent',
            'message' => sprintf('Sent to %d Telegram destination(s).', $result['sent_count']),
        ];
    }

    private function buildCommitteeDecisionMessage(CommitteeDecision $decision): string
    {
        $decisionable = $decision->decisionable;
        $building = $decisionable instanceof HousingUnit ? $decisionable->building : $decisionable;
        $recordName = $building?->building_name ?: 'Unnamed record';
        $buildingNumber = $building?->objectid ?: '-';
        $unitLabel = $decisionable instanceof HousingUnit
            ? ' | Unit: '.($decisionable->housing_unit_number ?: $decisionable->full_name ?: (string) $decisionable->objectid)
            : '';

        return trim(implode("\n", [
            '<b>Committee Decision</b>',
            'Building: '.$recordName.' (#'.$buildingNumber.')'.$unitLabel,
            'Decision Type: '.($decision->decision_type ?? '-'),
            'Decision Text: '.($decision->decision_text ?? '-'),
            'Required Action: '.($decision->action_text ?? '-'),
            'Committee Notes: '.($decision->notes ?? '-'),
            'Decision Date: '.optional($decision->decision_date)->format('Y-m-d'),
            '<code>'.now()->format('Y-m-d H:i').'</code>',
        ]));
    }
}
