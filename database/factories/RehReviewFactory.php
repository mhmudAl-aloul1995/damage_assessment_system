<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Reh\Models\RehReview>
 */
class RehReviewFactory extends Factory
{
    protected $model = \App\Modules\Reh\Models\RehReview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['unit_id' => RehUnitFactory::new(), 'user_id' => UserFactory::new(), 'action' => 'claim', 'note' => 'استلام الملف للتدقيق', 'created_at' => now()];
    }
}
