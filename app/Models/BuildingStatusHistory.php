<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingStatusHistory extends Model
{
    protected $fillable = [
        'building_id',
        'status_id',
        'user_id',
        'notes'
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function status()
    {
        return $this->belongsTo(AssessmentStatus::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
