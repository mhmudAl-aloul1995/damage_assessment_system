<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VHousingUnitAudited extends Model
{
    protected $table = 'v_housing_units_audited';

    protected $primaryKey = 'objectid';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
