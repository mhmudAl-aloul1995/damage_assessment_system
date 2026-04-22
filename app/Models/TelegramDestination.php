<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TelegramDestination extends Model
{
    use HasFactory;

    public const TYPE_USER = 'user';

    public const TYPE_GROUP = 'group';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'type',
        'scope_type',
        'name',
        'status',
        'chat_id',
        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'telegram_link_token',
        'related_model_type',
        'related_model_id',
        'context_id',
        'linked_by',
        'is_active',
        'linked_at',
        'last_notified_at',
        'meta_json',
        'extra_settings',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'related_model_id' => 'integer',
            'context_id' => 'integer',
            'linked_by' => 'integer',
            'is_active' => 'boolean',
            'linked_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'meta_json' => 'array',
            'extra_settings' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function relatedModel(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_model_type', 'related_model_id');
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(TelegramDestinationPreference::class);
    }

    public function linkSessions(): HasMany
    {
        return $this->hasMany(TelegramLinkSession::class)->latest('id');
    }

    public function linkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function discoveredChats(): HasMany
    {
        return $this->hasMany(TelegramDiscoveredChat::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && $this->is_active && filled($this->chat_id);
    }
}
