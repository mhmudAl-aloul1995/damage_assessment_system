<?php

namespace App\Modules\DamageAssessmentBorrowers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerAttachment extends Model
{
    protected $table = 'damage_assessment_borrower_attachments';

    protected $fillable = [
        'damage_assessment_borrower_id',
        'filename',
        'url',
        'source_index',
    ];

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(DamageAssessmentBorrower::class, 'damage_assessment_borrower_id');
    }

    protected function casts(): array
    {
        return [
            'source_index' => 'integer',
        ];
    }
}
