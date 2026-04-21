<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommitteeDecisionFactory extends Factory
{
    protected $model = CommitteeDecision::class;

    public function definition(): array
    {
        return [
            'decisionable_type' => Building::class,
            'decisionable_id' => 1,
            'decision_type' => 'accepted',
            'decision_text' => fake()->sentence(),
            'action_text' => fake()->sentence(),
            'notes' => fake()->sentence(),
            'decision_date' => now()->toDateString(),
            'status' => CommitteeDecision::STATUS_PENDING_SIGNATURES,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'committee_manager_id' => User::factory(),
        ];
    }
}
