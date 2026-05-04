<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadFacilityAuditHistory extends Model
{
    protected $guarded = [];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(RoadFacilitySurvey::class, 'globalid', 'globalid');
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
