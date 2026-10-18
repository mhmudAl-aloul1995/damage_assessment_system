<?php

namespace App\Modules\HR\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftAssignment extends AppendOnlyModel
{
    protected $table = 'hr_shift_assignments';

    protected $fillable = ['employee_id', 'work_schedule_id', 'project_id', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => \App\Modules\HR\Models\Casts\DateOnly::class, 'ends_on' => \App\Modules\HR\Models\Casts\DateOnly::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AttendanceProject::class, 'project_id');
    }

    public function covers(string $date): bool
    {
        $end = $this->effectiveEndsOn();

        return $date >= $this->starts_on->toDateString()
            && ($end === null || $date <= $end->toDateString());
    }

    public function retirements(): HasMany
    {
        return $this->hasMany(AssignmentRetirement::class, 'shift_assignment_id');
    }

    public function effectiveEndsOn(): ?CarbonImmutable
    {
        return $this->retirements->sortByDesc('id')->first()?->ends_on ?? $this->ends_on;
    }
}
