<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoadFacilityFilter extends Model
{
    public $timestamps = false;

    protected $table = 'road_facility_filters';

    protected $fillable = [
        'list_name',
        'name',
        'label',
        'group_value',
        'sort_order',
    ];
}
