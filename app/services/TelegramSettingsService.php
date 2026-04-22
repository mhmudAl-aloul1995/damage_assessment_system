<?php

declare(strict_types=1);

namespace App\services;

use App\Models\TelegramSetting;

class TelegramSettingsService
{
    public function current(): TelegramSetting
    {
        return TelegramSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'bot_token' => config('services.telegram.bot_token'),
                'bot_username' => config('services.telegram.bot_username'),
                'webhook_secret' => config('services.telegram.webhook_secret'),
                'is_enabled' => false,
                'parse_mode' => 'HTML',
            ],
        );
    }

    public function enabled(): bool
    {
        $settings = $this->current();

        return $settings->is_enabled && filled($settings->bot_token);
    }
}
