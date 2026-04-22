<?php

declare(strict_types=1);

namespace App\services\Messaging;

use App\Models\CommitteeDecision;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramMessagingProvider implements MessagingProvider
{
    public function sendCommitteeDecision(CommitteeDecision $decision, ?User $recipient = null): array
    {
        $token = (string) (config('services.telegram.bot_token') ?: config('services.committee_decisions.telegram.bot_token', ''));
        $defaultChatId = (string) (config('services.telegram.chat_id') ?: config('services.committee_decisions.telegram.chat_id', ''));
        $chatId = $recipient?->telegram_chat_id ?: $defaultChatId;

        if ($token === '' || $chatId === '') {
            Log::warning('Committee Telegram bot is not configured.', ['committee_decision_id' => $decision->id]);

            return [
                'success' => false,
                'status' => 'not_configured',
                'message' => 'Telegram bot token or chat id is not configured.',
            ];
        }

        /** @var Response $response */
        $response = Http::acceptJson()->post(
            sprintf('https://api.telegram.org/bot%s/sendMessage', $token),
            [
                'chat_id' => $chatId,
                'text' => $this->buildCommitteeDecisionMessage($decision),
            ],
        );

        if (! $response->successful() || ! (bool) data_get($response->json(), 'ok', false)) {
            Log::error('Committee Telegram request failed.', [
                'committee_decision_id' => $decision->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $response->body(),
            ];
        }

        return [
            'success' => true,
            'status' => 'sent',
            'message' => $response->body(),
        ];
    }

    public function buildCommitteeDecisionMessage(CommitteeDecision $decision): string
    {
        $decisionable = $decision->decisionable;
        $building = $decisionable instanceof HousingUnit ? $decisionable->building : $decisionable;
        $recordName = $building?->building_name ?: 'Unnamed record';
        $buildingNumber = $building?->objectid ?: '-';
        $unitLabel = $decisionable instanceof HousingUnit
            ? ' | Unit: '.($decisionable->housing_unit_number ?: $decisionable->full_name ?: (string) $decisionable->objectid)
            : '';

        return trim(implode("\n", [
            'Committee Decision',
            'Building: '.$recordName.' (#'.$buildingNumber.')'.$unitLabel,
            'Decision Type: '.($decision->decision_type ?? '-'),
            'Decision Text: '.($decision->decision_text ?? '-'),
            'Required Action: '.($decision->action_text ?? '-'),
            'Committee Notes: '.($decision->notes ?? '-'),
            'Decision Date: '.optional($decision->decision_date)->format('Y-m-d'),
        ]));
    }
}
