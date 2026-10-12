<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignment extends AppendOnlyModel
{
    protected $table = 'hr_shift_assignments';

    protected $fillable = ['employee_id', 'work_schedule_id', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function covers(string $date): bool
    {
        return $date >= $this->starts_on->toDateString()
            && ($this->ends_on === null || $date <= $this->ends_on->toDateString());
    }
}
