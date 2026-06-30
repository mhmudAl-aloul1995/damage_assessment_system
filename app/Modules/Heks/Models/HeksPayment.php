<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksPayment extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'source',
        'grant_amount',
        'payment_1_amount',
        'payment_2_amount',
        'payment_3_amount',
        'payment_1_date',
        'payment_2_date',
        'payment_3_date',
        'payment_1_words',
        'payment_2_words',
        'payment_3_words',
        'grant_words',
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
            'grant_amount' => 'decimal:2',
            'payment_1_amount' => 'decimal:2',
            'payment_2_amount' => 'decimal:2',
            'payment_3_amount' => 'decimal:2',
            'payment_1_date' => 'date',
            'payment_2_date' => 'date',
            'payment_3_date' => 'date',
            'raw_data' => 'array',
        ];
    }
}
