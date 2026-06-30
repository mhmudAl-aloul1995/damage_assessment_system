<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksBoqItem extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'source',
        'section',
        'item_code',
        'description',
        'unit',
        'quantity',
        'unit_price_ils',
        'total_price_ils',
        'notes',
        'raw_data',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(HeksBeneficiary::class, 'heks_beneficiary_id');
    }

    protected function casts(): array
    {
        return [
            'heks_beneficiary_id' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price_ils' => 'decimal:2',
            'total_price_ils' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
