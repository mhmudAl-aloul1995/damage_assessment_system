<?php

namespace App\Notifications;

use App\Models\Building;
use App\Models\CommitteeDecisionSignature;
use App\Models\HousingUnit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommitteeDecisionSignatureRequested extends Notification
{
    use Queueable;

    public function __construct(private readonly CommitteeDecisionSignature $signature) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $signature = $this->signature->loadMissing([
            'committeeDecision.decisionable',
            'committeeMember',
        ]);
        $decision = $signature->committeeDecision;
        $decisionable = $decision?->decisionable;

        return [
            'title' => 'مطلوب توقيع قرار لجنة',
            'message' => sprintf(
                'يرجى تسجيل توقيعك على قرار اللجنة رقم %s.',
                $decisionable?->objectid ?? $decision?->id ?? '-',
            ),
            'committee_decision_id' => $decision?->id,
            'committee_member_id' => $signature->committee_member_id,
            'signature_id' => $signature->id,
            'record_type' => $decisionable instanceof HousingUnit ? 'housing-unit' : 'building',
            'record_objectid' => $decisionable?->objectid,
            'decision_type' => $decision?->decision_type,
            'action_url' => $this->decisionUrl($decisionable),
        ];
    }

    private function decisionUrl(?object $decisionable): ?string
    {
        if ($decisionable instanceof Building) {
            return route('committee-decisions.buildings.show', $decisionable);
        }

        if ($decisionable instanceof HousingUnit) {
            return route('committee-decisions.housing-units.show', $decisionable);
        }

        return null;
    }
}
