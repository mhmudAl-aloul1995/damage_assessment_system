<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\HeksShelter\Models\ShelterCase;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShelterCaseFactory extends Factory
{
    protected $model = ShelterCase::class;

    public function definition(): array
    {
        $identity = fake()->unique()->numerify('#########');
        $name = fake()->name();

        return ['code' => 'HEKS-3-'.fake()->unique()->uuid(), 'phase' => 3, 'identity_number' => $identity, 'name' => $name,
            'phone' => '0599000000', 'governorate' => 'gaza', 'area' => 'غزة', 'engineer_id' => User::factory(), 'visit_date' => today(),
            'answers' => ['name' => $name, 'identity_number' => $identity, 'phone' => '0599000000', 'visit_date' => today()->toDateString(), 'governorate' => 'gaza',
                'eligibility' => 'safe', 'safe_environment' => 'yes', 'safe_entry' => 'yes', 'structurally_safe' => 'yes', 'ownership_proof' => 'yes',
                'uxo' => 'no', 'legal_dispute' => 'no', 'owner_permission_obstacle' => 'no', 'can_manage_cash' => 'yes',
                'family_total' => 4, 'unit_area' => 90, 'damage' => 'moderate', 'address' => 'عنوان تجريبي', 'recommendations' => ['repair']],
            'status' => 'received', 'engineering_status' => 'pending', 'social_status' => 'pending', 'version' => 1, 'grant_amount' => 0];
    }

    public function reviewed(): static
    {
        return $this->state(fn (): array => ['engineering_status' => 'approved', 'social_status' => 'approved', 'grant_amount' => 1000, 'status' => 'review']);
    }

    public function approved(): static
    {
        return $this->reviewed()->state(fn (): array => ['status' => 'approved', 'approved_at' => now()]);
    }
}
