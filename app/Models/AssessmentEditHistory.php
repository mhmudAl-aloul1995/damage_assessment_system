<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentEditHistory extends Model
{
    protected $fillable = [
        'global_id',
        'objectid',
        'type',
        'field_name',
        'old_value',
        'new_value',
        'edited_by',
        'edit_assessment_id',
        'return_request_id',
        'source',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function editAssessment(): BelongsTo
    {
        return $this->belongsTo(EditAssessment::class, 'edit_assessment_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(BuildingSurveyReturnRequest::class, 'return_request_id');
    }
}
