<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentStatus extends Model
{
    protected $fillable = [
        'name',
        'label_en',
        'label_ar',
        'stage',
        'order_step'
    ];

    public function buildingStatuses()
    {
        return $this->hasMany(BuildingStatus::class, 'status_id');
    }

    public function histories()
    {
        return $this->hasMany(BuildingStatusHistory::class, 'status_id');
    }
}