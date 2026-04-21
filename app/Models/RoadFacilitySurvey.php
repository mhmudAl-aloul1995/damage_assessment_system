<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoadFacilitySurvey extends Model
{
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'objectid';
    }

    protected function casts(): array
    {
        return [
            'submission_date' => 'datetime',
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
        return $this->hasMany(RoadFacilitySurveyItem::class);
    }
}
