<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramIntegration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TelegramIntegrationFactory extends Factory
{
    protected $model = TelegramIntegration::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'created_by' => User::factory(),
            'name' => fake()->company(),
            'type' => TelegramIntegration::TYPE_USER,
            'status' => TelegramIntegration::STATUS_PENDING,
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_title' => null,
            'linked_by' => null,
            'linked_at' => null,
            'disabled_at' => null,
            'last_error' => null,
        ];
    }
}
