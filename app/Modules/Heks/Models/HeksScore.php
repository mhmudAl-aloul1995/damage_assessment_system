<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksScore extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'source',
        'grant_amount',
        'payment_1',
        'payment_2',
        'payment_3',
        'social_score',
        'technical_score',
        'total_score',
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
            'payment_1' => 'decimal:2',
            'payment_2' => 'decimal:2',
            'payment_3' => 'decimal:2',
            'social_score' => 'decimal:2',
            'technical_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
