<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingSurveyReturnRequestLog extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'action',
        'step',
        'notes',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(BuildingSurveyReturnRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
