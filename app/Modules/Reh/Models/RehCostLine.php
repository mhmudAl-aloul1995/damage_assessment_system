<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehCostLine extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'rate' => 'decimal:6', 'amount' => 'decimal:2'];
    }
}
