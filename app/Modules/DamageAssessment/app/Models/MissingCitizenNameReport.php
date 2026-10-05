<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissingCitizenNameReport extends Model
{
    protected $fillable = [
        'housing_unit_id',
        'owner_name',
        'normalized_owner_name',
    ];
}
