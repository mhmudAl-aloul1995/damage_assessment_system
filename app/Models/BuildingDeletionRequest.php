<?php

namespace App\Models;

use App\Enums\BuildingDeletionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BuildingDeletionRequest extends Model
{
    protected $fillable = [
        'building_id',
        'building_globalid',
        'building_objectid',
        'requested_by',
        'reason',
        'notes',
        'status',
        'requires_field_engineer_approvals',
        'team_leader_reviewed_by',
        'team_leader_reviewed_at',
        'team_leader_notes',
        'area_manager_reviewed_by',
        'area_manager_reviewed_at',
        'area_manager_notes',
        'gis_reviewed_by',
        'gis_reviewed_at',
        'gis_notes',
        'snapshot_verified_at',
        'execution_started_at',
        'executed_by',
        'executed_at',
        'failed_step',
        'failure_reason',
        'last_successful_step',
        'retry_count',
        'deletion_plan',
        'execution_results',
    ];

    protected function casts(): array
    {
        return [
            'status' => BuildingDeletionStatus::class,
            'requires_field_engineer_approvals' => 'boolean',
            'team_leader_reviewed_at' => 'datetime',
            'area_manager_reviewed_at' => 'datetime',
            'gis_reviewed_at' => 'datetime',
            'snapshot_verified_at' => 'datetime',
            'execution_started_at' => 'datetime',
            'executed_at' => 'datetime',
            'retry_count' => 'integer',
            'deletion_plan' => 'array',
            'execution_results' => 'array',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function gisReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gis_reviewed_by');
    }

    public function teamLeaderReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_reviewed_by');
    }

    public function areaManagerReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'area_manager_reviewed_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(BuildingDeletionSnapshot::class, 'request_id');
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(BuildingDeletionSnapshot::class, 'request_id')->latestOfMany();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BuildingDeletionAuditLog::class, 'request_id');
    }
}
