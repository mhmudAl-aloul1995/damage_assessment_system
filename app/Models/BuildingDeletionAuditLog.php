<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingDeletionAuditLog extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'step',
        'status',
        'message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BuildingDeletionRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
