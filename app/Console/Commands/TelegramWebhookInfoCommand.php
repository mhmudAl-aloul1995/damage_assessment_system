<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;

class TelegramWebhookInfoCommand extends Command
{
    protected $signature = 'telegram:webhook-info';
    protected $description = 'Show Telegram webhook info';

    public function handle(TelegramBotService $telegramBot): int
    {
        $result = $telegramBot->getWebhookInfo();

        if (!$result['ok']) {
            $this->error('Failed to fetch webhook info.');
            $this->warn('Telegram message: ' . ($result['message'] ?? 'Unknown'));
            $this->warn('HTTP status: ' . ($result['status'] ?? 'N/A'));
            $this->line('Raw response: ' . ($result['raw'] ?? ''));
            return self::FAILURE;
        }

        $info = data_get($result, 'response.result', []);

        $this->info('Webhook info loaded successfully.');
        $this->line('URL: ' . (data_get($info, 'url') ?: '-'));
        $this->line('Has custom certificate: ' . (data_get($info, 'has_custom_certificate') ? 'yes' : 'no'));
        $this->line('Pending update count: ' . data_get($info, 'pending_update_count', 0));
        $this->line('Last error date: ' . (data_get($info, 'last_error_date') ?: '-'));
        $this->line('Last error message: ' . (data_get($info, 'last_error_message') ?: '-'));
        $this->line('Max connections: ' . (data_get($info, 'max_connections') ?: '-'));
        $this->line('Allowed updates: ' . json_encode(data_get($info, 'allowed_updates', []), JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
