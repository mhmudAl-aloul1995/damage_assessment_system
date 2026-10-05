<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingStatus extends Model
{
    protected $fillable = [
        'building_id',
        'status_id',
        'user_id',
        'type',
        'notes',
    ];

    public function status()
    {
        return $this->belongsTo(AssessmentStatus::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id', 'objectid');
    }

    public function assessment_status()
    {
        return $this->belongsTo(AssessmentStatus::class, 'status_id');
    }
}
