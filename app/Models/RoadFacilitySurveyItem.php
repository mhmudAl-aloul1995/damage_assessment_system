<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadFacilitySurveyItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'creationdate' => 'datetime',
            'editdate' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(RoadFacilitySurvey::class, 'parentglobalid', 'globalid');
    }
}
