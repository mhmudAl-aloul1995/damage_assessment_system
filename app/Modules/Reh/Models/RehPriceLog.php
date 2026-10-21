<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehPriceLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
