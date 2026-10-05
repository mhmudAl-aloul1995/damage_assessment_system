<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignedAssessmentUser extends Model
{
    protected $guarded = [];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
