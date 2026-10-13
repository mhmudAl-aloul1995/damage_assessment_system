<?php

namespace App\Modules\HR\Models;

class AttendanceSessionClosure extends AppendOnlyModel
{
    protected $table = 'hr_attendance_session_closures';

    public $timestamps = false;

    protected $fillable = ['check_in_event_id', 'adjustment_id', 'closed_by', 'note', 'resolved_at'];

    protected $hidden = ['note'];

    protected function casts(): array
    {
        return ['note' => 'encrypted'];
    }
}
