<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CsoAuditLog>
 */
class CsoAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_type' => 'cso_survey',
            'record_globalid' => fake()->uuid(),
            'field_name' => 'building_name',
            'old_value' => fake()->company(),
            'new_value' => fake()->company(),
            'user_id' => \App\Models\User::factory(),
            'created_at' => now(),
        ];
    }
}
