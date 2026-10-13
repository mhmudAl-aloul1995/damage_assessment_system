<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\HR\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Holiday> */
class HrHolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return ['name' => 'Test holiday', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12', 'created_by' => User::factory()];
    }
}
