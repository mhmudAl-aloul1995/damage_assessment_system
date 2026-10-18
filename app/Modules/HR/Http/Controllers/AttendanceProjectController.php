<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\StoreAttendanceProjectRequest;
use App\Modules\HR\Models\AttendanceProject;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\ShiftAssignment;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Services\AttendanceSetup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AttendanceProjectController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->is_active, 403);
        Gate::authorize('hr.attendance.setup');

        return view('hr::projects', [
            'projects' => AttendanceProject::query()->withCount('assignments')->latest('id')->paginate(15, ['*'], 'projects_page'),
            'projectOptions' => AttendanceProject::query()->orderBy('name')->get(),
            'employees' => Employee::query()->orderBy('display_name')->get(['id', 'employee_number', 'display_name']),
            'schedules' => WorkSchedule::query()->orderBy('name')->get(['id', 'name']),
            'assignments' => ShiftAssignment::query()->whereNotNull('project_id')->with(['employee', 'schedule', 'project', 'retirements'])
                ->latest('id')->paginate(25, ['*'], 'assignments_page'),
        ]);
    }

    public function store(StoreAttendanceProjectRequest $request, AttendanceSetup $setup): RedirectResponse
    {
        $setup->project($request->user(), $request->validated());

        return to_route('hr.attendance.projects')->with('success', 'أُضيف المشروع. يمكنك الآن إسناد الموظفين وجداول دوامهم إليه.');
    }
}
