<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmploymentContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmploymentContract> */
class HrEmploymentContractFactory extends Factory
{
    protected $model = EmploymentContract::class;

    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'created_by' => User::factory(), 'number' => fake()->unique()->bothify('CON-########'), 'primary' => true];
    }
}
