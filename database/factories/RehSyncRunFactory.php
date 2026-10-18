<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Reh\Models\RehSyncRun>
 */
class RehSyncRunFactory extends Factory
{
    protected $model = \App\Modules\Reh\Models\RehSyncRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => UserFactory::new(), 'status' => 'queued'];
    }
}
