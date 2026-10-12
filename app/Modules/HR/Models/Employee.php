<?php

namespace App\Modules\HR\Models;

use App\Models\Attendance;
use App\Models\User;
use Database\Factories\HrEmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'hr_employees';

    protected $fillable = ['user_id', 'employee_number', 'display_name', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
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

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
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
