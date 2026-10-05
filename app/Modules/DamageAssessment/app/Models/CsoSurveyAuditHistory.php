<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsoSurveyAuditHistory extends Model
{
    protected $guarded = [];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(CsoSurvey::class, 'cso_survey_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(InfAuditStatus::class, 'status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
