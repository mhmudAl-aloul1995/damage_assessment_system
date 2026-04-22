<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TelegramBroadcast;
use App\services\TelegramDestinationResolver;
use App\services\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTelegramBroadcastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $telegramBroadcastId) {}

    public function handle(TelegramDestinationResolver $resolver, TelegramNotifier $notifier): void
    {
        $broadcast = TelegramBroadcast::query()->find($this->telegramBroadcastId);

        if ($broadcast === null) {
            return;
        }

        $broadcast->forceFill(['status' => TelegramBroadcast::STATUS_PROCESSING])->save();

        $destinations = $resolver->forBroadcastTarget($broadcast->target_type, [
            'destination_ids' => $broadcast->destination_ids_json ?? [],
            'scope_type' => $broadcast->scope_type,
            'context_ids' => $broadcast->context_ids_json ?? [],
        ]);

        $result = $notifier->sendToDestinations(
            $destinations,
            sprintf("<b>%s</b>\n\n%s\n\n<code>%s</code>", $broadcast->title, $broadcast->message, now()->format('Y-m-d H:i'))
        );

        $broadcast->forceFill([
            'sent_count' => $result['sent_count'],
            'failed_count' => $result['failed_count'],
            'status' => $result['failed_count'] > 0 && $result['sent_count'] === 0
                ? TelegramBroadcast::STATUS_FAILED
                : TelegramBroadcast::STATUS_COMPLETED,
            'sent_at' => now(),
        ])->save();
    }
}
