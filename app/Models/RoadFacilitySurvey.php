<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RoadFacilitySurvey extends Model
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
            'submissiondate' => 'datetime',
            'creationdate' => 'datetime',
            'editdate' => 'datetime',
            'blockage_reason' => 'array',
            'road_type' => 'array',
            'sidewalk_damage_type' => 'array',
            'pole_type' => 'array',
            'traffic_signs_type' => 'array',
            'raw_payload' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RoadFacilitySurveyItem::class, 'parentglobalid', 'globalid');
    }

    public function infAuditStatus(): HasOne
    {
        return $this->hasOne(RoadFacilityAuditStatus::class, 'globalid', 'globalid')->latestOfMany();
    }

    public function infAuditAssignment(): HasOne
    {
        return $this->hasOne(InfAuditAssignment::class, 'globalid', 'globalid')
            ->where('type', 'road_facility');
    }

    public function getAssignedToAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['assignedto'] ?? null);
    }
}
