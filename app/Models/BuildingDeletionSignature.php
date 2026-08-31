<?php

namespace App\Models;

use App\Enums\BuildingDeletionSignatureAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingDeletionSignature extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'role',
        'action',
        'signature_path',
        'notes',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => BuildingDeletionSignatureAction::class,
            'signed_at' => 'datetime',
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
