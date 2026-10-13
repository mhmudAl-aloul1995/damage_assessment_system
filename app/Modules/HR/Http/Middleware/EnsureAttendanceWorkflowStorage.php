<?php

namespace App\Modules\HR\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendanceWorkflowStorage
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['hr_holidays', 'hr_schedule_exceptions', 'hr_attendance_adjustments', 'hr_adjustment_decisions', 'hr_attendance_daily_summaries', 'hr_attendance_exceptions', 'hr_assignment_retirements', 'hr_holiday_cancellations'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'يلزم تشغيل ترحيل قاعدة بيانات HR الجديد على هذا السيرفر قبل استخدام صفحات الحضور.');
        }
        abort_unless(Schema::hasColumn('hr_employees', 'supervisor_user_id'), 503, 'يلزم استكمال ترحيل قاعدة بيانات HR الجديد.');
        abort_unless(Schema::hasColumn('hr_work_schedules', 'flexible') && Schema::hasColumn('hr_attendance_adjustments', 'sessions'), 503, 'يلزم تشغيل ترحيل الورديات المتعددة والدوام المرن في HR.');

        return $next($request);
    }
}
