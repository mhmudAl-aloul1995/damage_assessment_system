<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamLeaderFieldEngineer extends Model
{
    protected $fillable = [
        'team_leader_id',
        'field_engineer_id',
        'created_by',
    ];

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function fieldEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_engineer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
