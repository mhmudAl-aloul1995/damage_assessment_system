<?php

declare(strict_types=1);

namespace App\services;

use App\Models\CommitteeDecision;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function sendCommitteeDecision(CommitteeDecision $decision, User $recipient): array
    {
        $endpoint = (string) config('services.committee_decisions.whatsapp.endpoint', '');
        $token = (string) config('services.committee_decisions.whatsapp.token', '');
        $message = $this->buildCommitteeDecisionMessage($decision);

        if ($endpoint === '') {
            Log::warning('Committee WhatsApp endpoint is not configured.', ['committee_decision_id' => $decision->id]);

            return [
                'success' => false,
                'status' => 'not_configured',
                'message' => 'WhatsApp endpoint is not configured.',
            ];
        }

        $payload = [
            'to' => $recipient->phone,
            'message' => $message,
            'decision_id' => $decision->id,
        ];

        $request = Http::acceptJson();

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        /** @var Response $response */
        $response = $request->post($endpoint, $payload);

        if (! $response->successful()) {
            Log::error('Committee WhatsApp request failed.', [
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
        $building = $decisionable instanceof \App\Models\HousingUnit ? $decisionable->building : $decisionable;
        $recordName = $building?->building_name ?: 'بدون اسم';
        $buildingNumber = $building?->objectid ?: '-';
        $unitLabel = $decisionable instanceof \App\Models\HousingUnit
            ? ' | الوحدة: '.($decisionable->housing_unit_number ?: $decisionable->full_name ?: (string) $decisionable->objectid)
            : '';

        return trim(implode("\n", [
            'قرار لجنة فنية',
            'المبنى: '.$recordName.' (#'.$buildingNumber.')'.$unitLabel,
            'نوع القرار: '.($decision->decision_type ?? '-'),
            'نص القرار: '.($decision->decision_text ?? '-'),
            'الإجراء المطلوب: '.($decision->action_text ?? '-'),
            'ملاحظات اللجنة: '.($decision->notes ?? '-'),
            'تاريخ القرار: '.optional($decision->decision_date)->format('Y-m-d'),
        ]));
    }
}
