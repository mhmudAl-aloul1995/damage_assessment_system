<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingDeletionSnapshot extends Model
{
    protected $fillable = [
        'request_id',
        'building_id',
        'building_globalid',
        'building_objectid',
        'snapshot_version',
        'base_data',
        'audited_data',
        'target_data',
        'related_data',
        'attachments_data',
        'metadata',
        'schema_data',
        'snapshot_hash',
        'created_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'base_data' => 'array',
            'audited_data' => 'array',
            'target_data' => 'array',
            'related_data' => 'array',
            'attachments_data' => 'array',
            'metadata' => 'array',
            'schema_data' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BuildingDeletionRequest::class, 'request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
