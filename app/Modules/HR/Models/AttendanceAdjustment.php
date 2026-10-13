<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceAdjustment extends AppendOnlyModel
{
    protected $table = 'hr_attendance_adjustments';

    protected $fillable = ['employee_id', 'work_date', 'requested_status', 'check_in_at', 'check_out_at', 'timezone', 'reason', 'basis_fingerprint', 'idempotency_key', 'requested_by', 'sessions', 'close_open_session'];

    protected function casts(): array
    {
        return ['work_date' => \App\Modules\HR\Models\Casts\DateOnly::class, 'employee_id' => 'integer', 'requested_by' => 'integer', 'sessions' => 'array', 'close_open_session' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decision(): HasOne
    {
        return $this->hasOne(AdjustmentDecision::class, 'adjustment_id');
    }
}
