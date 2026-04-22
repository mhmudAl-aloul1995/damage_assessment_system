<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramBroadcast extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'title',
        'message',
        'target_type',
        'scope_type',
        'destination_ids_json',
        'user_ids_json',
        'context_ids_json',
        'created_by',
        'sent_count',
        'failed_count',
        'status',
        'sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'destination_ids_json' => 'array',
            'user_ids_json' => 'array',
            'context_ids_json' => 'array',
            'created_by' => 'integer',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
