<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditedHousingUnit extends Model
{
    protected $table = 'audited_housing_units';

    protected $primaryKey = 'objectid';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'parentglobalid', 'globalid');
    }
}
