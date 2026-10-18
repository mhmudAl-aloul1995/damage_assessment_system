<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Database\Factories\HrAttendanceProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceProject extends AppendOnlyModel
{
    use HasFactory;

    protected $table = 'hr_attendance_projects';

    protected $fillable = ['code', 'name', 'starts_on', 'ends_on', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => Casts\DateOnly::class, 'ends_on' => Casts\DateOnly::class];
    }

    protected static function newFactory(): HrAttendanceProjectFactory
    {
        return HrAttendanceProjectFactory::new();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'project_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
