<?php

namespace Database\Factories;

use App\Modules\HR\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkSchedule> */
class HrWorkScheduleFactory extends Factory
{
    protected $model = WorkSchedule::class;

    public function definition(): array
    {
        return [
            'name' => 'Test schedule',
            'timezone' => 'Asia/Hebron',
            'working_days' => [1, 2, 3, 4, 7],
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
            'grace_minutes' => 5,
            'break_minutes' => 30,
        ];
    }
}
