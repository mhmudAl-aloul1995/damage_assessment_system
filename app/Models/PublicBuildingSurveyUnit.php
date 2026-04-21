<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicBuildingSurveyUnit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'select_document' => 'array',
            'raw_payload' => 'array',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(PublicBuildingSurvey::class, 'public_building_survey_id');
    }
}
