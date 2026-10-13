<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySummary extends Model
{
    protected $table = 'hr_attendance_daily_summaries';

    protected $fillable = ['employee_id', 'work_date', 'status', 'expected_minutes', 'worked_minutes', 'late_minutes', 'early_minutes', 'overtime_minutes', 'source_fingerprint', 'rules_version', 'payload', 'reconciled_at'];

    protected function casts(): array
    {
        return ['work_date' => \App\Modules\HR\Models\Casts\DateOnly::class, 'payload' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AttendanceException::class, 'daily_summary_id');
    }
}
