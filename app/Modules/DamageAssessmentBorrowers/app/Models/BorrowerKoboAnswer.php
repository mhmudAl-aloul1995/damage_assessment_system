<?php

namespace App\Modules\DamageAssessmentBorrowers\Models;

use App\Models\KoboRestSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerKoboAnswer extends Model
{
    protected $table = 'damage_assessment_borrower_kobo_answers';

    protected $fillable = [
        'damage_assessment_borrower_id',
        'kobo_rest_submission_id',
        'field_hash',
        'field_key',
        'field_label',
        'value',
        'raw_value',
        'sort_order',
    ];

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(DamageAssessmentBorrower::class, 'damage_assessment_borrower_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KoboRestSubmission::class, 'kobo_rest_submission_id');
    }

    protected function casts(): array
    {
        return [
            'damage_assessment_borrower_id' => 'integer',
            'kobo_rest_submission_id' => 'integer',
            'raw_value' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
