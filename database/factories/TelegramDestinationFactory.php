<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramDestination;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramDestination>
 */
class TelegramDestinationFactory extends Factory
{
    protected $model = TelegramDestination::class;

    public function definition(): array
    {
        return [
            'type' => TelegramDestination::TYPE_USER,
            'scope_type' => 'system',
            'name' => fake()->name(),
            'status' => TelegramDestination::STATUS_PENDING,
            'chat_id' => null,
            'telegram_link_token' => fake()->unique()->regexify('[A-Za-z0-9]{40}'),
            'related_model_type' => User::class,
            'related_model_id' => User::factory(),
            'context_id' => null,
            'linked_by' => null,
            'is_active' => true,
            'linked_at' => null,
            'last_notified_at' => null,
            'meta_json' => null,
            'extra_settings' => null,
            'last_error' => null,
        ];
    }
}
