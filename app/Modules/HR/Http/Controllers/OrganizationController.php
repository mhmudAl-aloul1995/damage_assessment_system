<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\EndDepartmentScopeRequest;
use App\Modules\HR\Http\Requests\StoreDepartmentScopeRequest;
use App\Modules\HR\Http\Requests\StoreOrganizationCatalogRequest;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\DepartmentScope;
use App\Modules\HR\Services\OrganizationCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller
{
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
