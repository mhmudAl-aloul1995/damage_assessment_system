<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramIntegration;
use App\Models\TelegramLinkSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TelegramLinkSessionFactory extends Factory
{
    protected $model = TelegramLinkSession::class;

    public function definition(): array
    {
        return [
            'telegram_integration_id' => TelegramIntegration::factory(),
            'token' => Str::random(40),
            'status' => TelegramLinkSession::STATUS_PENDING,
            'telegram_payload' => null,
            'completed_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }
}
