<?php

declare(strict_types=1);

namespace App\services;

use App\Models\TelegramIntegration;
use App\Models\TelegramLinkSession;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramConnectionService
{
    public function createIntegration(array $data, User $creator): TelegramIntegration
    {
        return DB::transaction(function () use ($data, $creator): TelegramIntegration {
            $integration = TelegramIntegration::query()->create([
                'user_id' => $data['user_id'] ?? null,
                'created_by' => $creator->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'status' => TelegramIntegration::STATUS_PENDING,
            ]);

            $this->createLinkSession($integration);

            return $integration->load(['user', 'creator', 'linkSessions']);
        });
    }

    public function createLinkSession(TelegramIntegration $integration): TelegramLinkSession
    {
        $integration->linkSessions()
            ->where('status', TelegramLinkSession::STATUS_PENDING)
            ->update(['status' => TelegramLinkSession::STATUS_DISABLED]);

        return $integration->linkSessions()->create([
            'token' => Str::random(40),
            'status' => TelegramLinkSession::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function shareableLink(TelegramIntegration $integration): ?string
    {
        $botUsername = (string) config('services.telegram.bot_username', '');
        $session = $integration->loadMissing('linkSessions')->latestPendingSession();

        if ($botUsername === '' || $session === null) {
            return null;
        }

        $parameter = 'ti_'.$session->token;

        if ($integration->type === TelegramIntegration::TYPE_GROUP) {
            $admin = 'invite_users+manage_chat+delete_messages';

            return sprintf('https://t.me/%s?startgroup=%s&admin=%s', $botUsername, $parameter, $admin);
        }

        return sprintf('https://t.me/%s?start=%s', $botUsername, $parameter);
    }

    public function refreshStatus(TelegramIntegration $integration): array
    {
        $token = (string) config('services.telegram.bot_token', '');

        if ($token === '') {
            return [
                'success' => false,
                'message' => 'Telegram bot token is not configured.',
            ];
        }

        if (blank($integration->telegram_chat_id)) {
            return [
                'success' => false,
                'message' => 'This integration is not linked to a Telegram chat yet.',
            ];
        }

        /** @var Response $response */
        $response = Http::acceptJson()->post(
            sprintf('https://api.telegram.org/bot%s/getChat', $token),
            ['chat_id' => $integration->telegram_chat_id],
        );

        if (! $response->successful() || ! data_get($response->json(), 'ok')) {
            $integration->forceFill([
                'status' => TelegramIntegration::STATUS_FAILED,
                'last_error' => $response->body(),
            ])->save();

            return [
                'success' => false,
                'message' => 'Telegram could not confirm the chat connection.',
            ];
        }

        $chat = (array) data_get($response->json(), 'result', []);

        $integration->forceFill([
            'status' => TelegramIntegration::STATUS_CONNECTED,
            'telegram_username' => Arr::get($chat, 'username'),
            'telegram_title' => Arr::get($chat, 'title') ?: trim((string) Arr::get($chat, 'first_name').' '.(string) Arr::get($chat, 'last_name')),
            'last_error' => null,
        ])->save();

        return [
            'success' => true,
            'message' => 'Telegram integration status refreshed successfully.',
        ];
    }

    public function disableIntegration(TelegramIntegration $integration): void
    {
        DB::transaction(function () use ($integration): void {
            $integration->loadMissing('user');

            $integration->linkSessions()
                ->where('status', TelegramLinkSession::STATUS_PENDING)
                ->update(['status' => TelegramLinkSession::STATUS_DISABLED]);

            if ($integration->type === TelegramIntegration::TYPE_USER && $integration->user !== null && $integration->user->telegram_chat_id === $integration->telegram_chat_id) {
                $integration->user->forceFill(['telegram_chat_id' => null])->save();
            }

            $integration->forceFill([
                'status' => TelegramIntegration::STATUS_DISABLED,
                'disabled_at' => now(),
            ])->save();
        });
    }

    public function deleteIntegration(TelegramIntegration $integration): void
    {
        DB::transaction(function () use ($integration): void {
            $integration->loadMissing('user');

            if ($integration->type === TelegramIntegration::TYPE_USER && $integration->user !== null && $integration->user->telegram_chat_id === $integration->telegram_chat_id) {
                $integration->user->forceFill(['telegram_chat_id' => null])->save();
            }

            $integration->delete();
        });
    }

    public function handleWebhookUpdate(array $update): array
    {
        $message = $this->extractMessage($update);

        if ($message === null) {
            return ['status' => 'ignored'];
        }

        $token = $this->extractSessionToken((string) Arr::get($message, 'text', ''));

        if ($token === null) {
            return ['status' => 'ignored'];
        }

        $session = TelegramLinkSession::query()
            ->with('integration.user')
            ->where('token', $token)
            ->first();

        if ($session === null || $session->status !== TelegramLinkSession::STATUS_PENDING) {
            return ['status' => 'ignored'];
        }

        $integration = $session->integration;

        if ($integration->status === TelegramIntegration::STATUS_DISABLED) {
            $session->forceFill(['status' => TelegramLinkSession::STATUS_DISABLED])->save();

            return ['status' => 'disabled'];
        }

        $chat = (array) Arr::get($message, 'chat', []);
        $from = (array) Arr::get($message, 'from', []);
        $chatType = (string) Arr::get($chat, 'type', '');
        $chatId = (string) Arr::get($chat, 'id', '');

        if ($integration->type === TelegramIntegration::TYPE_USER && $chatType !== 'private') {
            return $this->failLink($integration, $session, 'This link is intended for a direct Telegram user chat.');
        }

        if ($integration->type === TelegramIntegration::TYPE_GROUP && ! in_array($chatType, ['group', 'supergroup'], true)) {
            return $this->failLink($integration, $session, 'This link is intended for a Telegram group or supergroup.');
        }

        $duplicate = TelegramIntegration::query()
            ->where('id', '!=', $integration->id)
            ->where('type', $integration->type)
            ->where('telegram_chat_id', $chatId)
            ->whereIn('status', [
                TelegramIntegration::STATUS_PENDING,
                TelegramIntegration::STATUS_CONNECTED,
                TelegramIntegration::STATUS_FAILED,
            ])
            ->exists();

        if ($duplicate) {
            return $this->failLink($integration, $session, 'This Telegram chat is already linked to another integration.');
        }

        DB::transaction(function () use ($integration, $session, $chat, $from, $chatId): void {
            $displayName = trim((string) Arr::get($from, 'first_name').' '.(string) Arr::get($from, 'last_name'));
            $linkedBy = Arr::get($from, 'username') ? '@'.Arr::get($from, 'username') : ($displayName !== '' ? $displayName : (string) Arr::get($chat, 'title', 'Telegram'));

            $integration->forceFill([
                'status' => TelegramIntegration::STATUS_CONNECTED,
                'telegram_chat_id' => $chatId,
                'telegram_username' => Arr::get($chat, 'username') ?: Arr::get($from, 'username'),
                'telegram_title' => Arr::get($chat, 'title') ?: ($displayName !== '' ? $displayName : null),
                'linked_by' => $linkedBy,
                'linked_at' => now(),
                'disabled_at' => null,
                'last_error' => null,
            ])->save();

            $session->forceFill([
                'status' => TelegramLinkSession::STATUS_CONNECTED,
                'telegram_payload' => $chat + ['from' => $from],
                'completed_at' => now(),
            ])->save();

            if ($integration->type === TelegramIntegration::TYPE_USER && $integration->user !== null) {
                $integration->user->forceFill([
                    'telegram_chat_id' => $chatId,
                ])->save();
            }
        });

        Log::info('Telegram integration linked successfully.', [
            'telegram_integration_id' => $integration->id,
            'chat_id' => $chatId,
        ]);

        return ['status' => 'connected'];
    }

    private function failLink(TelegramIntegration $integration, TelegramLinkSession $session, string $message): array
    {
        DB::transaction(function () use ($integration, $session, $message): void {
            $integration->forceFill([
                'status' => TelegramIntegration::STATUS_FAILED,
                'last_error' => $message,
            ])->save();

            $session->forceFill([
                'status' => TelegramLinkSession::STATUS_FAILED,
                'telegram_payload' => ['error' => $message],
                'completed_at' => now(),
            ])->save();
        });

        $this->createLinkSession($integration);

        Log::warning('Telegram integration linking failed.', [
            'telegram_integration_id' => $integration->id,
            'message' => $message,
        ]);

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
        if (! preg_match('/\bti_([A-Za-z0-9]+)\b/', $text, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }
}
