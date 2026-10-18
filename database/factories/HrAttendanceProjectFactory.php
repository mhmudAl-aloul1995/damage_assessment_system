<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\HR\Models\AttendanceProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\HR\Models\AttendanceProject>
 */
class HrAttendanceProjectFactory extends Factory
{
    protected $model = AttendanceProject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PRJ-####??'),
            'name' => fake()->words(3, true),
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'created_by' => User::factory(),
        ];
    }
}
