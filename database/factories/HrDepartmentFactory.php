<?php

namespace Database\Factories;

use App\Modules\HR\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrDepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('DEP-####'), 'name' => fake()->company(), 'active' => true];
    }
}
