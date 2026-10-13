<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\EmployeeDirectoryRequest;
use App\Modules\HR\Http\Requests\EndDepartmentScopeRequest;
use App\Modules\HR\Http\Requests\StoreDepartmentScopeRequest;
use App\Modules\HR\Http\Requests\StoreOrganizationCatalogRequest;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\DepartmentScope;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\OrganizationCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller
{
    public function chart(EmployeeDirectoryRequest $request, EmployeePolicy $policy): View
    {
        $filters = $request->validated();
        $employees = $policy->profilesVisibleTo($request->user())->with(['currentOrganizationAssignment.department', 'currentOrganizationAssignment.jobTitle'])
            ->withExists(['organizationAssignments as has_organization_history' => fn ($query) => $query->where('primary', true)->where('starts_on', '<=', now(config('hr.timezone'))->toDateString())])
            ->whereDoesntHave('profile', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->whereHas('currentOrganizationAssignment', fn ($query) => $query->where('department_id', $id)))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($query) => $query->where('display_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%')))
            ->orderBy('display_name')->get();
        $userEmployees = $employees->whereNotNull('user_id')->keyBy('user_id');
        $employeeNodes = $employees->map(fn (Employee $employee): array => [
            'id' => $employee->id, 'parent_id' => $employee->currentOrganizationAssignment?->manager_employee_id ?? (! $employee->has_organization_history ? $userEmployees->get($employee->supervisor_user_id)?->id : null),
            'name' => $employee->display_name, 'number' => $employee->employee_number,
            'department' => $employee->currentOrganizationAssignment?->department->name,
            'title' => $employee->currentOrganizationAssignment?->jobTitle->name,
        ]);
        $departments = Department::query()->orderBy('name')->get();

        return view('hr::organization-chart', [
            'filters' => $filters, 'departments' => $departments,
            'departmentTree' => $this->forest($departments->map(fn (Department $department): array => ['id' => $department->id, 'parent_id' => $department->parent_id, 'name' => $department->name, 'active' => $department->active])),
            'employeeTree' => $this->forest($employeeNodes), 'employeeCount' => $employees->count(),
        ]);
    }

    /** @param Collection<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function forest(Collection $nodes): array
    {
        $ids = $nodes->pluck('id')->flip();
        $children = $nodes->groupBy(fn (array $node): int => $ids->has($node['parent_id']) ? $node['parent_id'] : 0);
        $visited = [];
        $build = function (array $node) use (&$build, &$visited, $children): array {
            $visited[$node['id']] = true;
            $node['children'] = [];
            foreach ($children->get($node['id'], collect()) as $child) {
                if (! isset($visited[$child['id']])) {
                    $node['children'][] = $build($child);
                }
            }

            return $node;
        };
        $tree = [];
        foreach ($children->get(0, collect()) as $root) {
            $tree[] = $build($root);
        }
        foreach ($nodes as $node) {
            if (! isset($visited[$node['id']])) {
                $tree[] = $build($node);
            }
        }

        return $tree;
    }

    public function index(string $kind, OrganizationCatalog $catalog): View
    {
        Gate::authorize('hr.organization.manage');
        $class = $catalog->model($kind);
        $query = $class::query()->orderBy('name');
        if ($kind === 'departments') {
            $query->with('parent');
        }

        return view('hr::organization', [
            'kind' => $kind, 'records' => $query->paginate(25), 'departments' => Department::query()->orderBy('name')->get(),
            'scopes' => $kind === 'departments' ? DepartmentScope::query()->with(['department', 'user', 'latestEnding'])->latest('id')->paginate(10, ['*'], 'scopes_page') : null,
        ]);
    }

    public function store(StoreOrganizationCatalogRequest $request, string $kind, OrganizationCatalog $catalog): RedirectResponse
    {
        $catalog->save($request->user(), $kind, $request->validated());

        return back()->with('success', 'حُفظ السجل التنظيمي.');
    }

    public function update(StoreOrganizationCatalogRequest $request, string $kind, int $record, OrganizationCatalog $catalog): RedirectResponse
    {
        $catalog->save($request->user(), $kind, $request->validated(), $record);

        return back()->with('success', 'حُفظ التحديث وسُجل في التدقيق؛ الأرشفة لا تحذف التاريخ.');
    }

    public function scope(StoreDepartmentScopeRequest $request, OrganizationCatalog $catalog): RedirectResponse
    {
        $catalog->scope($request->user(), $request->validated());

        return back()->with('success', 'حُفظ نطاق القسم؛ يحتاج الحساب أيضاً صلاحية عرض الفريق المناسبة.');
    }

    public function endScope(EndDepartmentScopeRequest $request, DepartmentScope $scope, OrganizationCatalog $catalog): RedirectResponse
    {
        $catalog->endScope($request->user(), $scope, $request->validated());

        return back()->with('success', 'سُجل تاريخ نهاية النطاق.');
    }
}
