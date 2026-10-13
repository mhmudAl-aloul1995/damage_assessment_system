<?php

namespace Database\Factories;

use App\Modules\HR\Models\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrJobTitleFactory extends Factory
{
    protected $model = JobTitle::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('JOB-####'), 'name' => fake()->jobTitle(), 'active' => true];
    }
}
