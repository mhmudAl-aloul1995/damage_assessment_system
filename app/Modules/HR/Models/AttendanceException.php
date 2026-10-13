<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceException extends Model
{
    protected $table = 'hr_attendance_exceptions';

    protected $fillable = ['daily_summary_id', 'kind', 'status', 'resolved_at'];

    public function summary(): BelongsTo
    {
        return $this->belongsTo(DailySummary::class, 'daily_summary_id');
    }
}
