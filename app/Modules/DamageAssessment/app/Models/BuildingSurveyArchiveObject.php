<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingSurveyArchiveObject extends Model
{
    protected $fillable = [
        'building_objectid',
        'building_globalid',
        'housing_unit_objectid',
        'housing_unit_globalid',
        'source_type',
        'return_request_id',
        'committee_decision_id',
        'archived_by',
        'archived_at',
        'notes',
        'building_snapshot',
        'housing_unit_snapshot',
        'committee_decision_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'building_snapshot' => 'array',
            'housing_unit_snapshot' => 'array',
            'committee_decision_snapshot' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BuildingSurveyReturnRequest::class, 'return_request_id');
    }

    public function committeeDecision(): BelongsTo
    {
        return $this->belongsTo(CommitteeDecision::class, 'committee_decision_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_objectid', 'objectid');
    }
}
