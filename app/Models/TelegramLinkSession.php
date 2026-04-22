<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLinkSession extends Model
{
    use HasFactory;

    protected $table = 'telegram_destination_link_sessions';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'telegram_destination_id',
        'token',
        'status',
        'telegram_payload',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_destination_id' => 'integer',
            'telegram_payload' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(TelegramDestination::class, 'telegram_destination_id');
    }
}
