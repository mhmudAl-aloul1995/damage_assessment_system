<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommitteeMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommitteeMemberFactory extends Factory
{
    protected $model = CommitteeMember::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->numerify('059#######'),
            'title' => fake()->jobTitle(),
            'is_active' => true,
            'is_required' => true,
            'sort_order' => fake()->numberBetween(0, 10),
            'signature_path' => null,
        ];
    }

    public function linkedUser(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => ($user ?? User::factory()->create())->id,
        ]);
    }
}
