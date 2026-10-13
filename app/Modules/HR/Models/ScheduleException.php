<?php

namespace App\Modules\HR\Models;

use Database\Factories\HrScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleException extends AppendOnlyModel
{
    use HasFactory;

    protected $table = 'hr_schedule_exceptions';

    protected $fillable = ['employee_id', 'work_date', 'kind', 'work_schedule_id', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['work_date' => \App\Modules\HR\Models\Casts\DateOnly::class];
    }

    protected static function newFactory(): HrScheduleExceptionFactory
    {
        return HrScheduleExceptionFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }
}
