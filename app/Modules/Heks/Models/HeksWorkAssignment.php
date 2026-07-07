<?php

namespace App\Modules\Heks\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeksWorkAssignment extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'source',
        'engineer_name',
        'engineer_user_id',
        'contract_amount_ils',
        'first_payment_ils',
        'phone',
        'raw_data',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(HeksBeneficiary::class, 'heks_beneficiary_id');
    }

    public function engineerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_user_id');
    }

    protected function casts(): array
    {
        return [
            'heks_beneficiary_id' => 'integer',
            'engineer_user_id' => 'integer',
            'contract_amount_ils' => 'decimal:2',
            'first_payment_ils' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
