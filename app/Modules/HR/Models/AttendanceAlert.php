<?php

namespace App\Modules\HR\Models;

class AttendanceAlert extends AppendOnlyModel
{
    protected $table = 'hr_attendance_alerts';

    public $timestamps = false;

    protected $fillable = ['attendance_exception_id', 'recipient_id', 'source_fingerprint', 'created_at'];
}
