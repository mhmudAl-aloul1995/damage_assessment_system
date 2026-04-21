<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\CommitteeMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommitteeDecisionSignatureFactory extends Factory
{
    protected $model = CommitteeDecisionSignature::class;

    public function definition(): array
    {
        return [
            'committee_decision_id' => CommitteeDecision::factory(),
            'committee_member_id' => CommitteeMember::factory(),
            'status' => 'pending',
            'notes' => null,
            'signed_at' => null,
            'signed_by_user_id' => null,
        ];
    }

    public function approved(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'signed_at' => now(),
            'signed_by_user_id' => ($user ?? User::factory()->create())->id,
        ]);
    }
}
