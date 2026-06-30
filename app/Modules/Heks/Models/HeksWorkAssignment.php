<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksWorkAssignment extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'source',
        'engineer_name',
        'contract_amount_ils',
        'first_payment_ils',
        'phone',
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
            'contract_amount_ils' => 'decimal:2',
            'first_payment_ils' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
