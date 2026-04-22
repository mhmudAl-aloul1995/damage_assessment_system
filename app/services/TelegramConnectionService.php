<?php

declare(strict_types=1);

namespace App\services;

use App\Models\TelegramDestination;
use App\Models\TelegramDiscoveredChat;
use App\Models\TelegramLinkSession;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramConnectionService
{
    public function __construct(
        private readonly TelegramSettingsService $settingsService,
        private readonly TelegramBotService $botService,
    ) {}

    public function createDestination(array $data, User $creator): TelegramDestination
    {
        return DB::transaction(function () use ($data, $creator): TelegramDestination {
            $destination = TelegramDestination::query()->create([
                'type' => $data['type'],
                'scope_type' => $data['scope_type'] ?? 'system',
                'name' => $data['name'],
                'status' => TelegramDestination::STATUS_PENDING,
                'related_model_type' => $data['related_model_type'] ?? null,
                'related_model_id' => $data['related_model_id'] ?? null,
                'context_id' => $data['context_id'] ?? null,
                'linked_by' => $creator->id,
                'is_active' => true,
                'extra_settings' => $data['extra_settings'] ?? null,
            ]);

            $destination->preferences()->create([
                'notify_status_changes' => true,
            ]);

            $this->createLinkSession($destination);

            return $destination->load(['preferences', 'linkedByUser', 'linkSessions']);
        });
    }

    public function ensureUserDestinationLink(User $user, User $creator): TelegramDestination
    {
        $destination = TelegramDestination::query()
            ->where('type', TelegramDestination::TYPE_USER)
            ->where('related_model_type', User::class)
            ->where('related_model_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if ($destination === null) {
            return $this->createDestination([
                'name' => $user->name.' Telegram',
                'type' => TelegramDestination::TYPE_USER,
                'scope_type' => 'system',
                'related_model_type' => User::class,
                'related_model_id' => $user->id,
            ], $creator);
        }

        DB::transaction(function () use ($destination, $creator): void {
            if (! $destination->is_active || $destination->status === TelegramDestination::STATUS_DISABLED) {
                $destination->forceFill([
                    'is_active' => true,
                    'status' => blank($destination->chat_id) ? TelegramDestination::STATUS_PENDING : $destination->status,
                    'linked_by' => $creator->id,
                    'last_error' => null,
                ])->save();
            }

            $this->createLinkSession($destination);
        });

        return $destination->fresh(['preferences', 'linkedByUser', 'linkSessions']);
    }

    public function updatePreferences(TelegramDestination $destination, array $preferences): void
    {
        $destination->preferences()->updateOrCreate([], $preferences);
    }

    public function createLinkSession(TelegramDestination $destination): TelegramLinkSession
    {
        $destination->linkSessions()
            ->where('status', TelegramLinkSession::STATUS_PENDING)
            ->update(['status' => TelegramLinkSession::STATUS_DISABLED]);

        $token = Str::random(40);

        $destination->forceFill([
            'telegram_link_token' => $token,
            'status' => $destination->isConnected() ? TelegramDestination::STATUS_CONNECTED : TelegramDestination::STATUS_PENDING,
            'last_error' => null,
        ])->save();

        return $destination->linkSessions()->create([
            'token' => $token,
            'status' => TelegramLinkSession::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function shareableLink(TelegramDestination $destination): ?string
    {
        $settings = $this->settingsService->current();
        $session = $destination->loadMissing('linkSessions')->linkSessions->firstWhere('status', TelegramLinkSession::STATUS_PENDING);

        if (blank($settings->bot_username) || $session === null) {
            return null;
        }

        $parameter = 'td_'.$session->token;

        if ($destination->type === TelegramDestination::TYPE_GROUP) {
            $admin = 'invite_users+manage_chat+delete_messages';

            return sprintf('https://t.me/%s?startgroup=%s&admin=%s', $settings->bot_username, $parameter, $admin);
        }

        return sprintf('https://t.me/%s?start=%s', $settings->bot_username, $parameter);
    }

    public function refreshStatus(TelegramDestination $destination): array
    {
        if (! $this->settingsService->enabled()) {
            return [
                'success' => false,
                'message' => 'Telegram is disabled or not configured.',
            ];
        }

        if (blank($destination->chat_id)) {
            return [
                'success' => false,
                'message' => 'This destination is not linked to a Telegram chat yet.',
            ];
        }

        $response = $this->botService->getChat($destination->chat_id);

        if (! $response->successful() || ! data_get($response->json(), 'ok')) {
            $destination->forceFill([
                'status' => TelegramDestination::STATUS_FAILED,
                'last_error' => $response->body(),
            ])->save();

            return [
                'success' => false,
                'message' => 'Telegram could not confirm the destination connection.',
            ];
        }

        $chat = (array) data_get($response->json(), 'result', []);

        $destination->forceFill([
            'status' => TelegramDestination::STATUS_CONNECTED,
            'telegram_username' => Arr::get($chat, 'username'),
            'telegram_first_name' => Arr::get($chat, 'first_name'),
            'telegram_last_name' => Arr::get($chat, 'last_name'),
            'meta_json' => array_merge($destination->meta_json ?? [], ['chat' => $chat]),
            'last_error' => null,
        ])->save();

        return [
            'success' => true,
            'message' => 'Telegram destination status refreshed successfully.',
        ];
    }

    public function disableDestination(TelegramDestination $destination): void
    {
        DB::transaction(function () use ($destination): void {
            $destination->linkSessions()
                ->where('status', TelegramLinkSession::STATUS_PENDING)
                ->update(['status' => TelegramLinkSession::STATUS_DISABLED]);

            $destination->forceFill([
                'status' => TelegramDestination::STATUS_DISABLED,
                'is_active' => false,
            ])->save();
        });
    }

    public function regenerateLink(TelegramDestination $destination): TelegramDestination
    {
        $this->createLinkSession($destination);

        return $destination->fresh(['preferences', 'linkedByUser', 'linkSessions']);
    }

    public function deleteDestination(TelegramDestination $destination): void
    {
        $destination->delete();
    }

    public function unlinkDestination(TelegramDestination $destination): void
    {
        DB::transaction(function () use ($destination): void {
            $destination->forceFill([
                'status' => TelegramDestination::STATUS_PENDING,
                'chat_id' => null,
                'telegram_user_id' => null,
                'telegram_username' => null,
                'telegram_first_name' => null,
                'telegram_last_name' => null,
                'linked_at' => null,
                'last_error' => null,
                'meta_json' => null,
            ])->save();

            $this->createLinkSession($destination);
        });
    }

    public function promoteDiscoveredChat(TelegramDiscoveredChat $discoveredChat, array $data, User $creator): TelegramDestination
    {
        return DB::transaction(function () use ($discoveredChat, $data, $creator): TelegramDestination {
            $destination = TelegramDestination::query()->create([
                'type' => TelegramDestination::TYPE_GROUP,
                'scope_type' => $data['scope_type'] ?? 'system',
                'name' => $data['name'] ?? ($discoveredChat->title ?: $discoveredChat->chat_id),
                'status' => TelegramDestination::STATUS_CONNECTED,
                'chat_id' => $discoveredChat->chat_id,
                'telegram_username' => $discoveredChat->username,
                'meta_json' => $discoveredChat->meta_json,
                'context_id' => $data['context_id'] ?? null,
                'linked_by' => $creator->id,
                'is_active' => true,
                'linked_at' => now(),
            ]);

            $destination->preferences()->create([
                'notify_broadcasts' => true,
            ]);

            $discoveredChat->forceFill([
                'telegram_destination_id' => $destination->id,
            ])->save();

            return $destination->load(['preferences', 'linkedByUser']);
        });
    }

    public function handleWebhookUpdate(array $update): array
    {
        $message = $this->extractMessage($update);

        if ($message === null) {
            return ['status' => 'ignored'];
        }

        $chat = (array) Arr::get($message, 'chat', []);
        $chatType = (string) Arr::get($chat, 'type', '');

        if (in_array($chatType, ['group', 'supergroup'], true)) {
            $this->recordDiscoveredChat($message);
        }

        $token = $this->extractSessionToken((string) Arr::get($message, 'text', ''));

        if ($token === null) {
            if ($chatType === 'private' && $this->settingsService->enabled()) {
                $this->sendPrivateInstructions((string) Arr::get($chat, 'id'));
            }

            return ['status' => 'ignored'];
        }

        $session = TelegramLinkSession::query()
            ->with('destination')
            ->where('token', $token)
            ->first();

        if ($session === null || $session->status !== TelegramLinkSession::STATUS_PENDING) {
            return ['status' => 'ignored'];
        }

        $destination = $session->destination;

        if (! $destination->is_active || $destination->status === TelegramDestination::STATUS_DISABLED) {
            $session->forceFill(['status' => TelegramLinkSession::STATUS_DISABLED])->save();

            return ['status' => 'disabled'];
        }

        $from = (array) Arr::get($message, 'from', []);
        $chatId = (string) Arr::get($chat, 'id', '');

        if ($destination->type === TelegramDestination::TYPE_USER && $chatType !== 'private') {
            return $this->failLink($destination, $session, 'This link is intended for a direct Telegram user chat.');
        }

        if ($destination->type === TelegramDestination::TYPE_GROUP && ! in_array($chatType, ['group', 'supergroup'], true)) {
            return $this->failLink($destination, $session, 'This link is intended for a Telegram group or supergroup.');
        }

        $duplicate = TelegramDestination::query()
            ->where('id', '!=', $destination->id)
            ->where('chat_id', $chatId)
            ->where('is_active', true)
            ->exists();

        if ($duplicate) {
            return $this->failLink($destination, $session, 'This Telegram chat is already linked to another destination.');
        }

        DB::transaction(function () use ($destination, $session, $chat, $from, $chatId): void {
            $destination->forceFill([
                'status' => TelegramDestination::STATUS_CONNECTED,
                'chat_id' => $chatId,
                'telegram_user_id' => Arr::get($from, 'id'),
                'telegram_username' => Arr::get($from, 'username') ?: Arr::get($chat, 'username'),
                'telegram_first_name' => Arr::get($from, 'first_name'),
                'telegram_last_name' => Arr::get($from, 'last_name'),
                'linked_at' => now(),
                'last_error' => null,
                'meta_json' => array_merge($destination->meta_json ?? [], ['chat' => $chat, 'from' => $from]),
            ])->save();

            $session->forceFill([
                'status' => TelegramLinkSession::STATUS_CONNECTED,
                'telegram_payload' => ['chat' => $chat, 'from' => $from],
                'completed_at' => now(),
            ])->save();

            TelegramDiscoveredChat::query()
                ->where('chat_id', $chatId)
                ->update(['telegram_destination_id' => $destination->id]);
        });

        Log::info('Telegram destination linked successfully.', [
            'telegram_destination_id' => $destination->id,
            'chat_id' => $chatId,
        ]);

        return ['status' => 'connected'];
    }

    public function discoveredChatsQuery()
    {
        return TelegramDiscoveredChat::query()->with('destination')->latest('last_seen_at');
    }

    private function recordDiscoveredChat(array $message): void
    {
        $chat = (array) Arr::get($message, 'chat', []);

        TelegramDiscoveredChat::query()->updateOrCreate(
            ['chat_id' => (string) Arr::get($chat, 'id')],
            [
                'chat_type' => (string) Arr::get($chat, 'type'),
                'title' => Arr::get($chat, 'title'),
                'username' => Arr::get($chat, 'username'),
                'last_message_text' => Arr::get($message, 'text'),
                'last_seen_at' => now(),
                'meta_json' => $message,
            ],
        );
    }

    private function sendPrivateInstructions(string $chatId): void
    {
        if ($chatId === '') {
            return;
        }

        try {
            $this->botService->sendMessage($chatId, 'This bot is linked from inside the system. Please use the generated link from the platform to connect your account.');
        } catch (\Throwable $throwable) {
            Log::warning('Telegram private instruction message failed.', [
                'chat_id' => $chatId,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function failLink(TelegramDestination $destination, TelegramLinkSession $session, string $message): array
    {
        DB::transaction(function () use ($destination, $session, $message): void {
            $destination->forceFill([
                'status' => TelegramDestination::STATUS_FAILED,
                'last_error' => $message,
            ])->save();

            $session->forceFill([
                'status' => TelegramLinkSession::STATUS_FAILED,
                'telegram_payload' => ['error' => $message],
                'completed_at' => now(),
            ])->save();
        });

        $this->createLinkSession($destination);

        return ['status' => 'failed'];
    }

    private function extractMessage(array $update): ?array
    {
        $message = Arr::get($update, 'message');

        if (is_array($message)) {
            return $message;
        }

        $channelPost = Arr::get($update, 'channel_post');

        return is_array($channelPost) ? $channelPost : null;
    }

    private function extractSessionToken(string $text): ?string
    {
        if (! preg_match('/\btd_([A-Za-z0-9]+)\b/', $text, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }
}
