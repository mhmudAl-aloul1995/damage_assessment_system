<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLinkSession extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'telegram_integration_id',
        'token',
        'status',
        'telegram_payload',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_integration_id' => 'integer',
            'telegram_payload' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(TelegramIntegration::class, 'telegram_integration_id');
    }
}
