<?php

namespace App\Modules\HR\Models;

use Database\Factories\HrWorkScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends AppendOnlyModel
{
    use HasFactory;

    protected $table = 'hr_work_schedules';

    protected $fillable = ['name', 'timezone', 'working_days', 'starts_at', 'ends_at', 'grace_minutes', 'break_minutes'];

    protected function casts(): array
    {
        return ['working_days' => 'array', 'grace_minutes' => 'integer', 'break_minutes' => 'integer'];
    }

    protected static function newFactory(): HrWorkScheduleFactory
    {
        return HrWorkScheduleFactory::new();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }
}
