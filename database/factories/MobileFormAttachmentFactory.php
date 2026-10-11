<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\MobileFormAttachment> */
class MobileFormAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'submission_uuid' => fake()->uuid(), 'form_key' => 'borrower-survey',
            'path' => 'mobile-form-attachments/'.fake()->uuid().'.pdf', 'name' => 'document.pdf',
            'mime' => 'application/pdf', 'size' => 100, 'content_hash' => hash('sha256', fake()->uuid())];
    }
}
