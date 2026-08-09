<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissingCitizenIdentityReport extends Model
{
    protected $fillable = [
        'housing_unit_id',
        'owner_name',
        'id_number',
    ];
}
