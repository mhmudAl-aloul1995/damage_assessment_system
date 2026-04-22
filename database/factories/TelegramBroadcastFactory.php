<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramBroadcast>
 */
class TelegramBroadcastFactory extends Factory
{
    protected $model = TelegramBroadcast::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'message' => fake()->paragraph(),
            'target_type' => 'all',
            'scope_type' => null,
            'destination_ids_json' => null,
            'user_ids_json' => null,
            'context_ids_json' => null,
            'created_by' => User::factory(),
            'sent_count' => 0,
            'failed_count' => 0,
            'status' => TelegramBroadcast::STATUS_PENDING,
            'sent_at' => null,
            'last_error' => null,
        ];
    }
}
