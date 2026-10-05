<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;

class HeksBoqCatalogItem extends Model
{
    protected $fillable = [
        'section',
        'item_code',
        'description',
        'unit',
        'unit_price_ils',
        'notes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_ils' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
