<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadFacilityAuditStatus extends Model
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
