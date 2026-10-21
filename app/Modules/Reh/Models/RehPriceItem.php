<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehPriceItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rate' => 'decimal:6'];
    }

    public function components(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RehPriceComponent::class, 'item_id');
    }

    public function book(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RehPriceBook::class, 'book_id');
    }
}
