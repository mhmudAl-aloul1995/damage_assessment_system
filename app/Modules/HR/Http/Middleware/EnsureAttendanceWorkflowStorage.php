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
        abort_unless(Schema::hasTable('hr_attendance_projects') && Schema::hasColumn('hr_shift_assignments', 'project_id'), 503, 'يلزم تشغيل تحديث قاعدة بيانات HR لدعم موظفي المشاريع.');
        foreach (['hr_holidays', 'hr_schedule_exceptions', 'hr_attendance_adjustments', 'hr_adjustment_decisions', 'hr_attendance_daily_summaries', 'hr_attendance_exceptions', 'hr_assignment_retirements', 'hr_holiday_cancellations', 'hr_attendance_report_exports', 'hr_attendance_alerts', 'hr_department_scopes', 'hr_employee_assignments', 'hr_attendance_session_closures'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'يلزم تشغيل ترحيل قاعدة بيانات HR الجديد على هذا السيرفر قبل استخدام صفحات الحضور.');
        }
        abort_unless(Schema::hasColumn('hr_employees', 'supervisor_user_id'), 503, 'يلزم استكمال ترحيل قاعدة بيانات HR الجديد.');
        abort_unless(Schema::hasColumn('hr_work_schedules', 'flexible') && Schema::hasColumn('hr_attendance_adjustments', 'sessions'), 503, 'يلزم تشغيل ترحيل الورديات المتعددة والدوام المرن في HR.');
        abort_unless(Schema::hasColumn('hr_audit_logs', 'subject_key'), 503, 'يلزم استكمال تحديث سجل تدقيق HR عبر أمر التثبيت.');

        return $next($request);
    }
}
