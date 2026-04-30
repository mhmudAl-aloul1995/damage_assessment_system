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
            'creationdate' => 'datetime',
            'editdate' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(PublicBuildingSurvey::class, 'parentglobalid', 'globalid');
    }
}
