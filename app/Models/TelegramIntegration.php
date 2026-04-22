<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramIntegration extends Model
{
    use HasFactory;

    public const TYPE_USER = 'user';

    public const TYPE_GROUP = 'group';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'user_id',
        'created_by',
        'name',
        'type',
        'status',
        'telegram_chat_id',
        'telegram_username',
        'telegram_title',
        'linked_by',
        'linked_at',
        'disabled_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'created_by' => 'integer',
            'linked_at' => 'datetime',
            'disabled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function linkSessions(): HasMany
    {
        return $this->hasMany(TelegramLinkSession::class)->latest('id');
    }

    public function latestPendingSession(): ?TelegramLinkSession
    {
        return $this->linkSessions
            ->first(fn (TelegramLinkSession $session): bool => $session->status === TelegramLinkSession::STATUS_PENDING);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }
}
