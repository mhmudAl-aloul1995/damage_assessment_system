<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Exports\AttendanceExport;
use App\Modules\HR\Http\Requests\AttendanceReportRequest;
use App\Modules\HR\Http\Requests\RetireShiftAssignmentRequest;
use App\Modules\HR\Http\Requests\StoreEmployeeRequest;
use App\Modules\HR\Http\Requests\StoreShiftAssignmentRequest;
use App\Modules\HR\Http\Requests\StoreWorkScheduleRequest;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\ShiftAssignment;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceReport;
use App\Modules\HR\Services\AttendanceSetup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    public function index(AttendanceReportRequest $request, AttendanceReport $report): View
    {
        $filters = $request->validated();
        $employees = $report->query($request->user(), $filters)->paginate(25)->withQueryString();
        $rows = $report->rows($employees, $filters);

        return view('hr::attendance', [
            'employees' => $employees,
            'rows' => $rows,
            'filters' => $filters,
            'dates' => $report->dates($filters),
            'totals' => $report->totals($request->user(), $filters),
        ]);
    }

    public function setup(): View
    {
        Gate::authorize('hr.attendance.setup');

        return view('hr::setup', [
            'employees' => Employee::query()->orderBy('employee_number')->paginate(25, ['*'], 'employees_page'),
            'schedules' => WorkSchedule::query()->latest('id')->paginate(25, ['*'], 'schedules_page'),
            'assignments' => ShiftAssignment::query()->with(['employee', 'schedule', 'retirements'])->latest('id')->paginate(25, ['*'], 'assignments_page'),
        ]);
    }

    public function employee(StoreEmployeeRequest $request, AttendanceSetup $setup): RedirectResponse
    {
        $setup->employee($request->user(), $request->validated());

        return to_route('hr.attendance.setup')->with('success', 'تم إنشاء هوية الحضور.');
    }

    public function schedule(StoreWorkScheduleRequest $request, AttendanceSetup $setup): RedirectResponse
    {
        $setup->schedule($request->user(), $request->validated());

        return to_route('hr.attendance.setup')->with('success', 'تم إنشاء إصدار جدول العمل.');
    }

    public function assignment(StoreShiftAssignmentRequest $request, AttendanceSetup $setup): RedirectResponse
    {
        $setup->assign($request->user(), $request->validated());

        return to_route('hr.attendance.setup')->with('success', 'تم إسناد الوردية.');
    }

    public function retireAssignment(RetireShiftAssignmentRequest $request, ShiftAssignment $assignment, AttendanceSetup $setup): RedirectResponse
    {
        $setup->retire($request->user(), $assignment, $request->validated());

        return to_route('hr.attendance.setup')->with('success', 'تم تسجيل نهاية التكليف مع حفظ تاريخه الأصلي.');
    }

    public function export(AttendanceReportRequest $request, AttendanceReport $report): BinaryFileResponse
    {
        $filters = $request->validated();
        $query = $report->query($request->user(), $filters);
        abort_if((clone $query)->count() > 500, 422, 'للنطاق الكبير استخدم طلب Excel في صفحة تقارير الحضور ليُجهّز في الخلفية.');

        return Excel::download(new AttendanceExport($report->rows($query->get(), $filters)), 'hr-attendance.xlsx');
    }
}
