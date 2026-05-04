<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PublicBuildingSurvey extends Model
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
            'date_of_damage' => 'date',
            'creationdate' => 'datetime',
            'editdate' => 'datetime',
            'benef_type' => 'array',
            'building_roof_type' => 'array',
            'ground_floor_use' => 'array',
            'raw_payload' => 'array',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(PublicBuildingSurveyUnit::class, 'parentglobalid', 'globalid');
    }

    public function infAuditStatus(): HasOne
    {
        return $this->hasOne(PublicBuildingAuditStatus::class, 'public_building_survey_id');
    }

    public function infAuditAssignment(): HasOne
    {
        return $this->hasOne(InfAuditAssignment::class, 'globalid', 'globalid')
            ->where('type', 'public_building');
    }

    public function getAssignedToAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['assignedto'] ?? null);
    }
}
