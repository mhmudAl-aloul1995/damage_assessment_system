<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CsoSurvey extends Model
{
    protected $guarded = [];

    protected $appends = [
        'assignedto',
    ];

    public function getRouteKeyName(): string
    {
        return 'globalid';
    }

    protected function casts(): array
    {
        return [
            'damage_date' => 'date',
            'creationdate' => 'datetime',
            'editdate' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function infAuditStatus(): HasOne
    {
        return $this->hasOne(CsoSurveyAuditStatus::class)->latestOfMany();
    }

    public function infAuditAssignment(): HasOne
    {
        return $this->hasOne(InfAuditAssignment::class, 'globalid', 'globalid')
            ->where('type', 'cso_survey');
    }

    public function getAssignedToAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['assignedto'] ?? null);
    }
}
