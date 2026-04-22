<?php

declare(strict_types=1);

namespace App\services;

use App\Models\TelegramDestination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TelegramDestinationResolver
{
    public function byPreference(string $preferenceKey): Collection
    {
        return $this->baseQuery()
            ->whereHas('preferences', fn (Builder $query) => $query->where($preferenceKey, true))
            ->get();
    }

    public function byScope(string $scopeType, string $preferenceKey): Collection
    {
        return $this->baseQuery()
            ->where('scope_type', $scopeType)
            ->whereHas('preferences', fn (Builder $query) => $query->where($preferenceKey, true))
            ->get();
    }

    public function byScopeAndContext(string $scopeType, int|string|null $contextId, string $preferenceKey): Collection
    {
        return $this->baseQuery()
            ->where('scope_type', $scopeType)
            ->where('context_id', $contextId)
            ->whereHas('preferences', fn (Builder $query) => $query->where($preferenceKey, true))
            ->get();
    }

    public function forBroadcastTarget(string $targetType, array $payload = []): Collection
    {
        return match ($targetType) {
            'selected' => $this->baseQuery()
                ->whereIn('id', $payload['destination_ids'] ?? [])
                ->get(),
            'scope' => $this->baseQuery()
                ->where('scope_type', $payload['scope_type'] ?? 'system')
                ->when(filled($payload['context_ids'] ?? []), fn (Builder $query) => $query->whereIn('context_id', $payload['context_ids']))
                ->whereHas('preferences', fn (Builder $query) => $query->where('notify_broadcasts', true))
                ->get(),
            default => $this->baseQuery()
                ->whereHas('preferences', fn (Builder $query) => $query->where('notify_broadcasts', true))
                ->get(),
        };
    }

    public function forRelatedModel(string $modelType, int $modelId, ?string $preferenceKey = null): Collection
    {
        return $this->baseQuery()
            ->where('related_model_type', $modelType)
            ->where('related_model_id', $modelId)
            ->when(
                $preferenceKey !== null,
                fn (Builder $query) => $query->whereHas('preferences', fn (Builder $preferenceQuery) => $preferenceQuery->where($preferenceKey, true))
            )
            ->get();
    }

    private function baseQuery(): Builder
    {
        return TelegramDestination::query()
            ->with('preferences')
            ->where('is_active', true)
            ->where('status', TelegramDestination::STATUS_CONNECTED)
            ->whereNotNull('chat_id');
    }
}
