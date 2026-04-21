<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommitteeDecision extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_SIGNATURES = 'pending_signatures';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'decisionable_type',
        'decisionable_id',
        'decision_type',
        'decision_text',
        'action_text',
        'notes',
        'decision_date',
        'status',
        'created_by',
        'updated_by',
        'committee_manager_id',
        'completed_at',
        'whatsapp_status',
        'whatsapp_sent_at',
        'whatsapp_last_attempt_at',
        'whatsapp_last_error',
        'arcgis_synced_at',
        'arcgis_last_attempt_at',
        'arcgis_sync_status',
        'arcgis_last_error',
        'arcgis_last_response',
    ];

    protected function casts(): array
    {
        return [
            'decisionable_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'committee_manager_id' => 'integer',
            'decision_date' => 'date',
            'completed_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
            'whatsapp_last_attempt_at' => 'datetime',
            'arcgis_synced_at' => 'datetime',
            'arcgis_last_attempt_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function decisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function committeeManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committee_manager_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(CommitteeDecisionSignature::class)->orderBy('id');
    }

    public function requiredPendingSignaturesCount(): int
    {
        return $this->signatures
            ->filter(fn (CommitteeDecisionSignature $signature): bool => $signature->committeeMember?->is_active && $signature->committeeMember?->is_required)
            ->filter(fn (CommitteeDecisionSignature $signature): bool => $signature->status !== 'approved')
            ->count();
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
