<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditedBuilding extends Model
{
    protected $table = 'audited_buildings';

    protected $primaryKey = 'objectid';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
