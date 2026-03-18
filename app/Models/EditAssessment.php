<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditAssessment extends Model
{

    protected $guarded = [];
    protected $fillable = [
        'global_id',
        'type',
        'field_name',
        'field_value',
        'user_id',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function housingUnit()
    {
        return $this->belongsTo(HousingUnit::class);
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
