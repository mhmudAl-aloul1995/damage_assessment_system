<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_token',
        'bot_username',
        'webhook_secret',
        'is_enabled',
        'parse_mode',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

     public static function current(): ?self
    {
        return static::query()
            ->where('is_enabled', true)
            ->latest('id')
            ->first();
    }
}
