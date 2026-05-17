<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VBuildingAudited extends Model
{
    protected $table = 'v_buildings_audited';

    protected $primaryKey = 'objectid';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
