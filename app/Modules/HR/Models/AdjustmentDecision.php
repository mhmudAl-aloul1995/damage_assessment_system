<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdjustmentDecision extends AppendOnlyModel
{
    protected $table = 'hr_adjustment_decisions';

    protected $fillable = ['adjustment_id', 'decision', 'reviewed_by', 'note', 'reviewed_at'];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(AttendanceAdjustment::class, 'adjustment_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
