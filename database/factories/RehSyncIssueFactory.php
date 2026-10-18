<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Reh\Models\RehSyncIssue>
 */
class RehSyncIssueFactory extends Factory
{
    protected $model = \App\Modules\Reh\Models\RehSyncIssue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'object_id' => fake()->unique()->numberBetween(1, 1000000),
            'reason' => 'المبنى المرتبط غير موجود في المصدر.',
            'source' => ['attributes' => ['parentglobalid' => fake()->uuid()]],
        ];
    }
}
