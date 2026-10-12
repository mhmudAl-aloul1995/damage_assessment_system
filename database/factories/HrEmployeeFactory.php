<?php

namespace Database\Factories;

use App\Modules\HR\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class HrEmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'employee_number' => fake()->unique()->numerify('PHC-######'),
            'display_name' => fake()->name(),
            'starts_on' => '2026-01-01',
        ];
    }
}
