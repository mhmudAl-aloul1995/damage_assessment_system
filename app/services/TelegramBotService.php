<?php

declare(strict_types=1);

namespace App\services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramBotService
{
    public function __construct(private readonly TelegramSettingsService $settingsService) {}

    public function sendMessage(string $chatId, string $message, array $options = []): Response
    {
        $settings = $this->settingsService->current();

        if (! $settings->is_enabled || blank($settings->bot_token)) {
            throw new RuntimeException('Telegram is not configured or disabled.');
        }

        $response = Http::acceptJson()->post(
            sprintf('%s/bot%s/sendMessage', rtrim((string) config('services.telegram.base_url', 'https://api.telegram.org'), '/'), $settings->bot_token),
            array_merge([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $settings->parse_mode ?: 'HTML',
            ], $options),
        );

        if (! $response->successful() || ! data_get($response->json(), 'ok', false)) {
            Log::error('Telegram sendMessage failed.', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    public function setWebhook(string $url): Response
    {
        $settings = $this->settingsService->current();

        return Http::acceptJson()->post(
            sprintf('%s/bot%s/setWebhook', rtrim((string) config('services.telegram.base_url', 'https://api.telegram.org'), '/'), $settings->bot_token),
            ['url' => $url],
        );
    }

    public function getWebhookInfo(): Response
    {
        $settings = $this->settingsService->current();

        return Http::acceptJson()->post(
            sprintf('%s/bot%s/getWebhookInfo', rtrim((string) config('services.telegram.base_url', 'https://api.telegram.org'), '/'), $settings->bot_token),
        );
    }

    public function getChat(string $chatId): Response
    {
        $settings = $this->settingsService->current();

        return Http::acceptJson()->post(
            sprintf('%s/bot%s/getChat', rtrim((string) config('services.telegram.base_url', 'https://api.telegram.org'), '/'), $settings->bot_token),
            ['chat_id' => $chatId],
        );
    }
}
