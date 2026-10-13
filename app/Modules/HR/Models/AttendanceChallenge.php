<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceChallenge extends Model
{
    protected $table = 'hr_attendance_challenges';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['expires_at' => \App\Modules\HR\Models\Casts\UtcInstant::class, 'consumed_at' => \App\Modules\HR\Models\Casts\UtcInstant::class, 'employee_id' => 'integer', 'credential_id' => 'integer', 'shift_assignment_id' => 'integer'];
    }
}
