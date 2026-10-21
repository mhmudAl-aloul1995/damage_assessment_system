<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehCostEstimate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'total_usd' => 'decimal:2', 'exchange_rate' => 'decimal:6'];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RehCostLine::class, 'estimate_id');
    }

    public function book(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RehPriceBook::class, 'book_id');
    }

    public function approver(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
}
