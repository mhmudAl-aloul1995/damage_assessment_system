<?php

namespace App\Console\Commands;

use App\Models\TelegramSetting;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook';
    protected $description = 'Set Telegram webhook';

    public function handle(TelegramBotService $telegramBot): int
    {
        $setting = TelegramSetting::current();

        if (!$setting) {
            $this->error('Telegram settings record not found.');
            return self::FAILURE;
        }

        if (blank($setting->bot_token)) {
            $this->error('Telegram bot token is missing.');
            return self::FAILURE;
        }

        if (blank($setting->webhook_secret)) {
            $this->error('Telegram webhook secret is missing.');
            return self::FAILURE;
        }


        $baseUrl = rtrim(config('app.url'), '/');
        $url = $baseUrl . '/api/telegram/webhook/' . $setting->webhook_secret;

        $this->line('Webhook URL: ' . $url);

        if (!str_starts_with($url, 'https://')) {
            $this->error('Webhook URL must start with https://');
            return self::FAILURE;
        }

        $result = $telegramBot->setWebhook($url);

        if (!$result['ok']) {
            $this->error('Failed to set webhook.');
            $this->warn('Telegram message: ' . ($result['message'] ?? 'Unknown'));
            $this->warn('HTTP status: ' . ($result['status'] ?? 'N/A'));
            $this->line('Raw response: ' . ($result['raw'] ?? ''));
            return self::FAILURE;
        }

        $this->info('Webhook set successfully.');
        return self::SUCCESS;
    }
}
