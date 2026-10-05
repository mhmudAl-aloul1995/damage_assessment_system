<?php

namespace App\Modules\DamageAssessmentBorrowers\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowerPricingSetting extends Model
{
    protected $table = 'damage_assessment_borrower_pricing_settings';

    protected $fillable = [
        'exchange_rate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:4',
        ];
    }
}
