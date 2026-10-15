<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsoSurveyUnit extends Model
{
    protected $guarded = [];

    public function scopeForSurvey(Builder $query, CsoSurvey $survey): void
    {
        $query->where(function (Builder $query) use ($survey): void {
            $query->where('parentglobalid', $survey->globalid)
                ->orWhereIn('parentglobalid', $survey->organizations()->select('globalid'));
        });
    }

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
        return $this->belongsTo(CsoSurvey::class, 'parentglobalid', 'globalid');
    }
}
