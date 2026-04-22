<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramDiscoveredChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'chat_type',
        'title',
        'username',
        'last_message_text',
        'last_seen_at',
        'meta_json',
        'telegram_destination_id',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'meta_json' => 'array',
            'telegram_destination_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(TelegramDestination::class, 'telegram_destination_id');
    }
}
