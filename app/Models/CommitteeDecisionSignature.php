<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeDecisionSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'committee_decision_id',
        'committee_member_id',
        'status',
        'notes',
        'signed_at',
        'signed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'committee_decision_id' => 'integer',
            'committee_member_id' => 'integer',
            'signed_by_user_id' => 'integer',
            'signed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function committeeDecision(): BelongsTo
    {
        return $this->belongsTo(CommitteeDecision::class);
    }

    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(CommitteeMember::class);
    }

    public function signedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }
}
