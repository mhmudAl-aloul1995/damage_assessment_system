<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramDiscoveredChat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramDiscoveredChat>
 */
class TelegramDiscoveredChatFactory extends Factory
{
    protected $model = TelegramDiscoveredChat::class;

    public function definition(): array
    {
        return [
            'chat_id' => '-100'.fake()->unique()->numerify('#######'),
            'chat_type' => 'supergroup',
            'title' => fake()->company(),
            'username' => fake()->optional()->userName(),
            'last_message_text' => fake()->sentence(),
            'last_seen_at' => now(),
            'meta_json' => ['source' => 'factory'],
            'telegram_destination_id' => null,
        ];
    }
}
