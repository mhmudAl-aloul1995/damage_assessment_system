<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function getAssignedToAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['assignedto'] ?? null);
    }
}
