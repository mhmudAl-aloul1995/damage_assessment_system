<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommitteeMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'title',
        'is_active',
        'is_required',
        'sort_order',
        'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_active' => 'boolean',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decisionSignatures(): HasMany
    {
        return $this->hasMany(CommitteeDecisionSignature::class);
    }
}
