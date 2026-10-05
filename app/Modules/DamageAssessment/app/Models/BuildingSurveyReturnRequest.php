<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BuildingSurveyReturnRequest extends Model
{
    protected $fillable = [
        'building_id',
        'building_objectid',
        'building_globalid',
        'requested_by',
        'team_leader_id',
        'area_manager_id',
        'current_step',
        'status',
        'reason',
        'team_leader_notes',
        'area_manager_notes',
        'requested_at',
        'team_leader_approved_at',
        'area_manager_approved_at',
        'rejected_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'team_leader_approved_at' => 'datetime',
            'area_manager_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_objectid', 'objectid');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function areaManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'area_manager_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BuildingSurveyReturnRequestLog::class, 'request_id');
    }

    public function archiveObject(): HasOne
    {
        return $this->hasOne(BuildingSurveyArchiveObject::class, 'return_request_id');
    }
}
