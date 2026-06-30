<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeksBeneficiary extends Model
{
    protected $fillable = [
        'code',
        'name',
        'identity_number',
        'phone',
        'alternate_phone',
        'field_engineer',
        'visit_date',
        'governorate',
        'area',
        'address',
        'household_head_gender',
        'marital_status',
        'displacement_status',
        'occupancy_status',
        'damage_status',
        'grant_amount',
        'payment_1',
        'payment_2',
        'payment_3',
        'social_notes',
        'engineer_notes',
        'recommendations',
        'raw_data',
    ];

    public function labels(): HasMany
    {
        return $this->hasMany(HeksLabel::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(HeksFollowUp::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(HeksScore::class);
    }

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'grant_amount' => 'decimal:2',
            'payment_1' => 'decimal:2',
            'payment_2' => 'decimal:2',
            'payment_3' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
