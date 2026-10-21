<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehPriceComponent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'rate' => 'decimal:6'];
    }
}
