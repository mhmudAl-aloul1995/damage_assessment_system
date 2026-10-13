<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\ScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduleException> */
class HrScheduleExceptionFactory extends Factory
{
    protected $model = ScheduleException::class;

    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'work_date' => '2026-10-12', 'kind' => 'leave', 'reason' => 'Approved leave reference', 'created_by' => User::factory()];
    }
}
