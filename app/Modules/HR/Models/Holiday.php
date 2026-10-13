<?php

namespace App\Modules\HR\Models;

use Database\Factories\HrHolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Holiday extends AppendOnlyModel
{
    use HasFactory;

    protected $table = 'hr_holidays';

    protected $fillable = ['name', 'starts_on', 'ends_on', 'work_schedule_id', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => \App\Modules\HR\Models\Casts\DateOnly::class, 'ends_on' => \App\Modules\HR\Models\Casts\DateOnly::class, 'work_schedule_id' => 'integer'];
    }

    protected static function newFactory(): HrHolidayFactory
    {
        return HrHolidayFactory::new();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function covers(string $date, ?int $scheduleId): bool
    {
        return $this->activeOn($date)
            && ($this->work_schedule_id === null || $this->work_schedule_id === $scheduleId);
    }

    public function activeOn(string $date): bool
    {
        return $this->starts_on->toDateString() <= $date && $this->ends_on->toDateString() >= $date
            && ($this->cancellation === null || $date < $this->cancellation->cancelled_from->toDateString());
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(HolidayCancellation::class);
    }
}
