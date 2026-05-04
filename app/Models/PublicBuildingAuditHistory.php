<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicBuildingAuditHistory extends Model
{
    protected $guarded = [];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(PublicBuildingSurvey::class, 'public_building_survey_id');
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
