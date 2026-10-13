<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\ArchiveEmployeeProfileRequest;
use App\Modules\HR\Http\Requests\EmployeeDirectoryRequest;
use App\Modules\HR\Http\Requests\EndEmployeeAssignmentRequest;
use App\Modules\HR\Http\Requests\StoreEmployeeAssignmentRequest;
use App\Modules\HR\Http\Requests\StoreEmployeeDocumentRequest;
use App\Modules\HR\Http\Requests\StoreEmploymentNoteRequest;
use App\Modules\HR\Http\Requests\StoreManagedEmployeeRequest;
use App\Modules\HR\Http\Requests\UpdateEmployeeProfileRequest;
use App\Modules\HR\Http\Requests\WithdrawEmployeeDocumentRequest;
use App\Modules\HR\Models\CostCenter;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeAssignment;
use App\Modules\HR\Models\EmployeeDocument;
use App\Modules\HR\Models\JobTitle;
use App\Modules\HR\Models\OfficeLocation;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\EmployeeDocuments;
use App\Modules\HR\Services\EmployeeManagement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(EmployeeDirectoryRequest $request, EmployeePolicy $policy): View
    {
        $filters = $request->validated();
        $archive = $filters['archive'] ?? 'active';
        $query = $policy->profilesVisibleTo($request->user())->with(['profile', 'currentOrganizationAssignment.department', 'currentOrganizationAssignment.jobTitle', 'currentOrganizationAssignment.manager']);
        if ($archive === 'archived') {
            $query->whereHas('profile', fn ($query) => $query->whereNotNull('archived_at'));
        } elseif ($archive === 'active') {
            $query->whereDoesntHave('profile', fn ($query) => $query->whereNotNull('archived_at'));
        }
        $query->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($query) => $query->where('display_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%')));
        $query->when($filters['department_id'] ?? null, fn ($query, $id) => $query->whereHas('currentOrganizationAssignment', fn ($query) => $query->where('department_id', $id)));

        return view('hr::employees', ['employees' => $query->orderBy('employee_number')->paginate(25)->withQueryString(), 'filters' => $filters, 'departments' => Department::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('hr.employees.manage');
        Gate::authorize('hr.employees.view');

        return view('hr::employee-create');
    }

    public function store(StoreManagedEmployeeRequest $request, EmployeeManagement $employees): RedirectResponse
    {
        $employee = $employees->create($request->user(), $request->validated());

        return to_route('hr.employees.show', $employee)->with('success', 'أُنشئت هوية الموظف باستخدام جدول المستخدمين الموجود.');
    }

    public function show(Request $request, Employee $employee, EmployeePolicy $policy): View
    {
        Gate::authorize('viewProfile', $employee);
        $employee->load(['profile', 'user', 'organizationAssignments' => fn ($query) => $query->with(['department', 'jobTitle', 'manager', 'office', 'costCenter', 'latestEnding'])->latest('starts_on'), 'employmentHistory' => fn ($query) => $query->with('author')->latest('occurred_on')]);

        return view('hr::employee-show', [
            'employee' => $employee, 'sensitive' => $policy->sensitiveProfile($request->user(), $employee),
            'documents' => $request->user()->can('viewDocuments', $employee) ? $employee->documents()->with(['replacement', 'withdrawal'])->latest('id')->get() : collect(),
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(),
            'titles' => JobTitle::query()->where('active', true)->orderBy('name')->get(),
            'offices' => OfficeLocation::query()->where('active', true)->orderBy('name')->get(),
            'costCenters' => CostCenter::query()->where('active', true)->orderBy('name')->get(),
            'managers' => $policy->profilesVisibleTo($request->user())->whereKeyNot($employee->id)->orderBy('display_name')->get(['id', 'display_name']),
        ]);
    }

    public function update(UpdateEmployeeProfileRequest $request, Employee $employee, EmployeeManagement $employees): RedirectResponse
    {
        $employees->update($request->user(), $employee, $request->validated());

        return back()->with('success', 'حُفظت بيانات الموظف وسُجلت أسماء الحقول المعدلة في التدقيق.');
    }

    public function assign(StoreEmployeeAssignmentRequest $request, Employee $employee, EmployeeManagement $employees): RedirectResponse
    {
        $employees->assign($request->user(), $employee, $request->validated());

        return back()->with('success', 'حُفظ التكليف التنظيمي وتاريخه دون تعديل التكليفات السابقة.');
    }

    public function endAssignment(EndEmployeeAssignmentRequest $request, EmployeeAssignment $assignment, EmployeeManagement $employees): RedirectResponse
    {
        $employees->endAssignment($request->user(), $assignment, $request->validated());

        return back()->with('success', 'سُجل إنهاء فترة التكليف.');
    }

    public function note(StoreEmploymentNoteRequest $request, Employee $employee, EmployeeManagement $employees): RedirectResponse
    {
        $employees->note($request->user(), $employee, $request->validated());

        return back()->with('success', 'أُضيفت الملاحظة المشفرة إلى التاريخ الوظيفي.');
    }

    public function archive(ArchiveEmployeeProfileRequest $request, Employee $employee, EmployeeManagement $employees): RedirectResponse
    {
        $employees->archive($request->user(), $employee, $request->boolean('archive'));

        return back()->with('success', 'حُفظت حالة الأرشفة؛ سجل الموظف ومستخدم النظام محفوظان.');
    }

    public function upload(StoreEmployeeDocumentRequest $request, Employee $employee, EmployeeDocuments $documents): RedirectResponse
    {
        $documents->upload($request->user(), $employee, $request->safe()->except('file'), $request->file('file'));

        return back()->with('success', 'حُفظ المستند مشفراً في التخزين الخاص.');
    }

    public function download(Request $request, EmployeeDocument $document, EmployeeDocuments $documents): StreamedResponse
    {
        return $documents->download($request->user(), $document);
    }

    public function withdraw(WithdrawEmployeeDocumentRequest $request, EmployeeDocument $document, EmployeeDocuments $documents): RedirectResponse
    {
        $documents->withdraw($request->user(), $document, $request->validated('reason'));

        return back()->with('success', 'سُجل سحب المستند من الاستخدام مع حفظ النسخة الأصلية.');
    }
}
