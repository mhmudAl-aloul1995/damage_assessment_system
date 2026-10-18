<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeAssignment extends AppendOnlyModel
{
    protected $table = 'hr_employee_assignments';

    protected $fillable = ['employee_id', 'department_id', 'job_title_id', 'office_location_id', 'cost_center_id', 'manager_employee_id', 'primary', 'starts_on', 'ends_on', 'snapshot', 'created_by'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'manager_employee_id' => 'integer', 'primary' => 'boolean', 'snapshot' => 'array',
            'starts_on' => \App\Modules\HR\Models\Casts\DateOnly::class, 'ends_on' => \App\Modules\HR\Models\Casts\DateOnly::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function endings(): HasMany
    {
        return $this->hasMany(EmployeeAssignmentEnding::class, 'assignment_id');
    }

    public function latestEnding(): HasOne
    {
        return $this->hasOne(EmployeeAssignmentEnding::class, 'assignment_id')->latestOfMany();
    }

    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->where('starts_on', '<=', $date)->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $date))
            ->whereDoesntHave('latestEnding', fn (Builder $query) => $query->where('ends_on', '<', $date));
    }

    public function effectiveEndsOn(): ?string
    {
        return $this->latestEnding?->ends_on->toDateString() ?? $this->ends_on?->toDateString();
    }

    public function covers(string $date): bool
    {
        return $date >= $this->starts_on->toDateString() && ($this->effectiveEndsOn() === null || $date <= $this->effectiveEndsOn());
    }
}
