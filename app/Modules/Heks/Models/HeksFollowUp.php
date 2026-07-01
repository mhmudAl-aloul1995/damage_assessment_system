<?php

namespace App\Modules\Heks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeksFollowUp extends Model
{
    protected $fillable = [
        'heks_beneficiary_id',
        'code',
        'visit_number',
        'visit_date',
        'engineer_name',
        'working_condition',
        'other_condition',
        'completed_amount_ils',
        'completion_percentage',
        'engineer_recommendations',
        'boq_filename',
        'boq_url',
        'raw_data',
    ];

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(HeksBeneficiary::class, 'heks_beneficiary_id');
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(HeksBoqItem::class, 'heks_follow_up_id');
    }

    protected function casts(): array
    {
        return [
            'heks_beneficiary_id' => 'integer',
            'visit_date' => 'date',
            'completed_amount_ils' => 'decimal:2',
            'completion_percentage' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }
}
