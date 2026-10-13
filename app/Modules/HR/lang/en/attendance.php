<?php

return ['statuses' => [
    'unscheduled' => 'No schedule',
    'off_day' => 'Non-working day',
    'outside_employment' => 'Outside employment',
    'pending' => 'Awaiting shift end',
    'absence_candidate' => 'Potential absence — review required',
    'present' => 'Completed attendance',
    'working' => 'Working',
    'missing_checkout' => 'Missing check-out',
    'missing_checkin' => 'Missing check-in',
    'missing_session' => 'Incomplete assigned shift',
    'legacy_present' => 'Present — legacy record',
    'legacy_absent' => 'Absent — legacy record',
    'conflict' => 'Source conflict',
    'holiday' => 'Official holiday',
    'leave' => 'Approved attendance leave',
    'excused_absence' => 'Approved excused absence',
    'off_day_worked' => 'Worked on a holiday or day off',
    'approved_present' => 'Approved corrected presence — times unknown',
    'approved_absent' => 'Approved corrected absence',
], 'actions' => [
    'employee' => ['mapped' => 'Attendance identity created'],
    'schedule' => ['created' => 'Schedule version created', 'exception_recorded' => 'Approved schedule exception recorded'],
    'shift' => ['assigned' => 'Shift assigned', 'retired' => 'Assignment end recorded'],
    'holiday' => ['created' => 'Holiday recorded', 'cancelled' => 'Holiday cancellation recorded'],
    'attendance' => [
        'simulated' => 'Test attendance event', 'correction_requested' => 'Attendance correction requested',
        'correction_approved' => 'Attendance correction approved', 'correction_rejected' => 'Attendance correction rejected',
        'reconciled' => 'Attendance summary reconciled', 'view_permission_granted' => 'Attendance viewing granted',
    ],
]];
