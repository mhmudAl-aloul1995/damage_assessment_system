<?php

namespace App\Modules\DamageAssessmentBorrowers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerResidentHousehold extends Model
{
    protected $table = 'damage_assessment_borrower_resident_households';

    protected $fillable = [
        'damage_assessment_borrower_id',
        'head_name',
        'id_number',
        'members_count',
        'phone',
        'employment_status',
        'source_index',
    ];

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(DamageAssessmentBorrower::class, 'damage_assessment_borrower_id');
    }

    protected function casts(): array
    {
        return [
            'members_count' => 'integer',
            'source_index' => 'integer',
        ];
    }
}
