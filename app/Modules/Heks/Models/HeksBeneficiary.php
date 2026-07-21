<?php

namespace App\Modules\Heks\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeksBeneficiary extends Model
{
    protected $fillable = [
        'code',
        'name',
        'identity_number',
        'phone',
        'alternate_phone',
        'field_engineer',
        'field_engineer_user_id',
        'visit_date',
        'governorate',
        'area',
        'address',
        'household_head_gender',
        'marital_status',
        'displacement_status',
        'occupancy_status',
        'damage_status',
        'grant_amount',
        'payment_1',
        'payment_2',
        'payment_3',
        'social_notes',
        'engineer_notes',
        'recommendations',
        'is_selected',
        'selection_source',
        'selection_status',
        'payment_status',
        'work_group_source',
        'raw_data',
    ];

    public function labels(): HasMany
    {
        return $this->hasMany(HeksLabel::class);
    }

    public function fieldEngineerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_engineer_user_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(HeksFollowUp::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(HeksScore::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(HeksPayment::class);
    }

    public function workAssignments(): HasMany
    {
        return $this->hasMany(HeksWorkAssignment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HeksAttachment::class);
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(HeksBoqItem::class);
    }

    public function surveyValueHistories(): HasMany
    {
        return $this->hasMany(HeksSurveyValueHistory::class);
    }

    public function responsibleEngineerName(): ?string
    {
        if (filled($this->fieldEngineerUser?->name)) {
            return (string) $this->fieldEngineerUser->name;
        }

        if (filled($this->field_engineer) && ! self::isRawEngineerCode((string) $this->field_engineer)) {
            return (string) $this->field_engineer;
        }

        if ($this->relationLoaded('followUps')) {
            $followUp = $this->followUps->first(
                fn (HeksFollowUp $followUp): bool => filled($followUp->engineerUser?->name) || filled($followUp->engineer_name)
            );

            if (filled($followUp?->engineerUser?->name)) {
                return (string) $followUp->engineerUser->name;
            }

            if (filled($followUp?->engineer_name) && ! self::isRawEngineerCode((string) $followUp->engineer_name)) {
                return (string) $followUp->engineer_name;
            }
        }

        return null;
    }

    public static function isRawEngineerCode(string $value): bool
    {
        $value = trim($value);

        return $value === ''
            || preg_match('/^[0-9_\-\s]+$/', $value) === 1
            || preg_match('/\b[A-Za-z][A-Za-z0-9_.-]{2,30}\s+_{2,}\d*\b/', $value) === 1;
    }

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'field_engineer_user_id' => 'integer',
            'grant_amount' => 'decimal:2',
            'payment_1' => 'decimal:2',
            'payment_2' => 'decimal:2',
            'payment_3' => 'decimal:2',
            'is_selected' => 'boolean',
            'raw_data' => 'array',
        ];
    }
}
