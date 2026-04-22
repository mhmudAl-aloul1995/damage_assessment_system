<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramDestinationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_destination_id',
        'notify_new_records',
        'notify_errors',
        'notify_status_changes',
        'notify_reports',
        'notify_broadcasts',
    ];

    protected function casts(): array
    {
        return [
            'telegram_destination_id' => 'integer',
            'notify_new_records' => 'boolean',
            'notify_errors' => 'boolean',
            'notify_status_changes' => 'boolean',
            'notify_reports' => 'boolean',
            'notify_broadcasts' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(TelegramDestination::class, 'telegram_destination_id');
    }
}
