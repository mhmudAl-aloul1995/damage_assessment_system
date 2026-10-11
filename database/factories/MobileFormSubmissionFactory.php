<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\MobileFormSubmission> */
class MobileFormSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'submission_uuid' => fake()->uuid(), 'module' => 'damage_assessment_borrowers',
            'form_key' => 'borrower-survey', 'form_version' => str_repeat('a', 64), 'record_id' => 1,
            'record_version' => str_repeat('b', 64), 'request_hash' => str_repeat('c', 64), 'changes' => ['before' => [], 'after' => []]];
    }
}
