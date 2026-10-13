<?php

namespace App\Modules\HR\Models;

use App\Models\Attendance;
use App\Models\User;
use Database\Factories\HrEmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'hr_employees';

    protected $fillable = ['user_id', 'supervisor_user_id', 'employee_number', 'display_name', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'supervisor_user_id' => 'integer', 'starts_on' => \App\Modules\HR\Models\Casts\DateOnly::class, 'ends_on' => \App\Modules\HR\Models\Casts\DateOnly::class];
    }

    protected static function newFactory(): HrEmployeeFactory
    {
        return HrEmployeeFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function organizationAssignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    public function currentOrganizationAssignment(): HasOne
    {
        return $this->hasOne(EmployeeAssignment::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('primary', true)->effectiveOn(now(config('hr.timezone'))->toDateString()));
    }

    public function employmentHistory(): HasMany
    {
        return $this->hasMany(EmploymentHistory::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function legacyAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'user_id', 'user_id');
    }

    public function employedOn(string $date): bool
    {
        return $date >= $this->starts_on->toDateString()
            && ($this->ends_on === null || $date <= $this->ends_on->toDateString());
    }
}
