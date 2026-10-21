<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehPriceBook extends Model
{
    protected $guarded = [];

    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected static function newFactory(): \Database\Factories\RehPriceBookFactory
    {
        return \Database\Factories\RehPriceBookFactory::new();
    }

    protected function casts(): array
    {
        return ['exchange_rate' => 'decimal:6', 'effective_date' => 'date', 'approved_at' => 'datetime', 'revision' => 'integer'];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RehPriceItem::class, 'book_id');
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RehPriceLog::class, 'book_id');
    }
}
