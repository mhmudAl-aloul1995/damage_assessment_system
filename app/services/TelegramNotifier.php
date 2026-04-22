<?php

declare(strict_types=1);

namespace App\services;

use App\Models\TelegramDestination;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function __construct(private readonly TelegramBotService $botService) {}

    public function sendToDestinations(iterable $destinations, string $message): array
    {
        $sentCount = 0;
        $failedCount = 0;
        $seenChatIds = [];

        foreach ($destinations as $destination) {
            if (! $destination instanceof TelegramDestination || blank($destination->chat_id)) {
                $failedCount++;

                continue;
            }

            if (in_array($destination->chat_id, $seenChatIds, true)) {
                continue;
            }

            $seenChatIds[] = $destination->chat_id;

            try {
                $response = $this->botService->sendMessage($destination->chat_id, $message);

                if ($response->successful() && data_get($response->json(), 'ok', false)) {
                    $destination->forceFill(['last_notified_at' => now()])->save();
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            } catch (\Throwable $throwable) {
                Log::error('Telegram notification failed.', [
                    'telegram_destination_id' => $destination->id,
                    'message' => $throwable->getMessage(),
                ]);

                $failedCount++;
            }
        }

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ];
    }

    public function sendCollection(Collection $destinations, string $message): array
    {
        return $this->sendToDestinations($destinations, $message);
    }
}
