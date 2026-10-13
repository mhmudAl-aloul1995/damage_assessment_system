<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\AttendanceReportRequest;
use App\Modules\HR\Http\Requests\AttendanceWorkflowRequest;
use App\Modules\HR\Http\Requests\CancelHolidayRequest;
use App\Modules\HR\Http\Requests\DecideAttendanceAdjustmentRequest;
use App\Modules\HR\Http\Requests\ReconcileAttendanceRequest;
use App\Modules\HR\Http\Requests\StoreAttendanceAdjustmentRequest;
use App\Modules\HR\Http\Requests\StoreHolidayRequest;
use App\Modules\HR\Http\Requests\StoreScheduleExceptionRequest;
use App\Modules\HR\Jobs\ReconcileAttendance;
use App\Modules\HR\Models\AttendanceAdjustment;
use App\Modules\HR\Models\AttendanceException;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Holiday;
use App\Modules\HR\Models\ScheduleException;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceCalendar;
use App\Modules\HR\Services\AttendanceCorrection;
use App\Modules\HR\Services\AttendanceReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AttendanceWorkflowController extends Controller
{
    public function calendar(Request $request, EmployeePolicy $policy): View
    {
        abort_unless($request->user()->can('hr.attendance.setup') || $request->user()->can('hr.attendance.exceptions.manage'), 403);
        $employeeIds = $policy->visibleTo($request->user())->select('id');

        return view('hr::calendar', [
            'holidays' => Holiday::query()->with(['schedule', 'cancellation'])->latest('id')->paginate(25, ['*'], 'holidays_page'),
            'exceptions' => ScheduleException::query()->with(['employee', 'schedule'])->whereIn('employee_id', $employeeIds)->latest('id')->paginate(25, ['*'], 'exceptions_page'),
            'schedules' => WorkSchedule::query()->latest('id')->limit(100)->get(),
        ]);
    }

    public function holiday(StoreHolidayRequest $request, AttendanceCalendar $calendar): RedirectResponse
    {
        $calendar->holiday($request->user(), $request->validated());

        return to_route('hr.attendance.calendar')->with('success', 'تم تسجيل العطلة.');
    }

    public function cancelHoliday(CancelHolidayRequest $request, Holiday $holiday, AttendanceCalendar $calendar): RedirectResponse
    {
        $calendar->cancelHoliday($request->user(), $holiday, $request->validated());

        return to_route('hr.attendance.calendar')->with('success', 'تم تسجيل إلغاء العطلة مع حفظ سجلها الأصلي.');
    }

    public function scheduleException(StoreScheduleExceptionRequest $request, AttendanceCalendar $calendar): RedirectResponse
    {
        $calendar->exception($request->user(), $request->validated());

        return to_route('hr.attendance.calendar')->with('success', 'تم تسجيل إصدار الاستثناء مع حفظ التاريخ السابق.');
    }

    public function corrections(AttendanceWorkflowRequest $request, EmployeePolicy $policy, AttendanceReport $report): View
    {
        $filters = $request->validated();
        $query = AttendanceAdjustment::query()->with(['employee', 'requester', 'decision.reviewer'])
            ->whereIn('employee_id', $policy->visibleTo($request->user())->select('id'))
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id));
        if (! empty($filters['date']) || ! empty($filters['month'])) {
            $query->whereBetween('work_date', $report->dates($filters));
        }
        $state = $filters['state'] ?? 'pending';
        if ($state === 'pending') {
            $query->doesntHave('decision');
        } elseif ($state !== 'all') {
            $query->whereHas('decision', fn ($query) => $query->where('decision', $state));
        }

        return view('hr::corrections', ['adjustments' => $query->latest('id')->paginate(25)->withQueryString(), 'filters' => $filters, 'state' => $state]);
    }

    public function createCorrection(AttendanceReportRequest $request, Employee $employee, AttendanceCalculator $calculator): View
    {
        Gate::authorize('requestCorrection', $employee);
        $date = $request->validated('date') ?? now(config('hr.timezone'))->toDateString();

        return view('hr::correction-form', ['employee' => $employee, 'row' => $calculator->day($employee, $date), 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function submitCorrection(StoreAttendanceAdjustmentRequest $request, Employee $employee, AttendanceCorrection $correction): RedirectResponse
    {
        $correction->submit($request->user(), $employee, $request->validated());

        return to_route('hr.attendance.corrections')->with('success', 'تم تقديم التصحيح للمراجعة.');
    }

    public function decideCorrection(DecideAttendanceAdjustmentRequest $request, AttendanceAdjustment $adjustment, AttendanceCorrection $correction): RedirectResponse
    {
        $correction->decide($request->user(), $adjustment, $request->validated());

        return to_route('hr.attendance.corrections', ['state' => 'all'])->with('success', 'تم حفظ قرار المراجعة.');
    }

    public function exceptions(AttendanceReportRequest $request, EmployeePolicy $policy, AttendanceReport $report): View
    {
        $filters = $request->validated();
        $employeeIds = $policy->visibleTo($request->user())->select('id');
        $query = AttendanceException::query()->with('summary.employee')->where('status', 'open')
            ->whereHas('summary', fn ($query) => $query->whereIn('employee_id', $employeeIds)
                ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id)));
        if (! empty($filters['date']) || ! empty($filters['month'])) {
            $query->whereHas('summary', fn ($query) => $query->whereBetween('work_date', $report->dates($filters)));
        }

        return view('hr::exceptions', ['exceptions' => $query->latest('id')->paginate(25)->withQueryString()]);
    }

    public function reconcile(ReconcileAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        ReconcileAttendance::dispatch($request->user()->id, $data['from'], $data['to'], $data['employee_id'] ?? null);

        return to_route('hr.attendance.exceptions')->with('success', 'أُضيفت التسوية إلى طابور المعالجة؛ تظهر النتائج بعد اكتمال المهمة.');
    }

    public function audit(): View
    {
        Gate::authorize('hr.attendance.audit');

        return view('hr::audit', ['logs' => AuditLog::query()->with('actor')->latest('id')->paginate(50)]);
    }
}
