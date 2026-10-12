<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends AppendOnlyModel
{
    public $timestamps = false;

    protected $table = 'hr_attendance_events';

    protected $fillable = ['employee_id', 'shift_assignment_id', 'work_date', 'action', 'occurred_at', 'source', 'idempotency_key', 'created_at'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'shift_assignment_id' => 'integer', 'work_date' => 'immutable_date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }
}
