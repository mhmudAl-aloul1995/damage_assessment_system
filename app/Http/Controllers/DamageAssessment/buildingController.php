<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Exports\TableExport;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Building;
use App\Models\Filter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Yajra\Datatables\Datatables;

class buildingController extends Controller
{
    public function __construct()
    {
        // $this->middleware('role:Database Officer|Project Officer');
    }

    public function index()
    {
        $filterColumns = ['id', 'list_name', 'name', 'label'];

        if (Schema::hasColumn('filters', 'list_name_arabic')) {
            $filterColumns[] = 'list_name_arabic';
        }

        $filters = Filter::query()
            ->select($filterColumns)
            ->orderBy('list_name')
            ->get();

        $filterName = Schema::hasColumn('filters', 'list_name_arabic')
            ? $filters->whereNotNull('list_name_arabic')->pluck('list_name', 'list_name_arabic')
            : $filters->pluck('list_name', 'list_name');

        return View::make('DamageAssessment.buildings', [
            'engineers' => Building::query()->distinct()->orderBy('assignedto')->pluck('assignedto')->filter()->values(),
            'owners' => Building::query()->distinct()->orderBy('owner_name')->pluck('owner_name')->filter()->values(),
            'municip' => Building::query()->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),
            'neighborhoods' => Building::query()->distinct()->orderBy('neighborhood')->pluck('neighborhood')->filter()->values(),
            'assessments' => Assessment::query()->get(),
            'filterName' => $filterName,
            'filters' => $filters,
            'groupedFilters' => $filters->groupBy('list_name'),
        ]);
    }

    public function show(Request $request)
    {
        $isHomepage = $request->input('hompage_building') == 1;
        $select = $isHomepage
            ? ['assignedto', 'globalid', 'objectid', 'building_name', 'owner_name', 'zone_code', 'neighborhood']
            : ['assignedto', 'globalid', 'objectid', 'building_name', 'owner_name', 'zone_code', 'units_count', 'editdate', 'field_status', 'units_nos', 'damaged_units_nos', 'neighborhood', 'municipalitie'];

        $query = Building::query()->select($select)->where('assignedto', '!=', '');

        $excludeKeys = ['order', '_', 'columns', 'draw', 'start', 'length', 'search', 'hompage_building'];
        $filters = $request->except($excludeKeys);

        foreach ($filters as $key => $value) {
            if (! is_null($value) && $value !== '') {
                $query->where($key, $value);
            }
        }

        return Datatables::of($query)
            ->editColumn('id', function ($ctr) {
                return '<div class="form-check form-check-sm form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" value="'.$ctr->id.'" />
                    </div>';
            })
            ->editColumn('action', function ($ctr) {
                $housingUrl = url("/showHousing/{$ctr->globalid}");
                $assessmentUrl = url("/assessment/{$ctr->globalid}");

                return '
                <a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">'.e(__('ui.damage_common.actions')).'
                    <i class="ki-duotone ki-down fs-5 ms-1"></i></a>
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                    <div class="menu-item px-3">
                        <a target="_blank" href="'.$housingUrl.'" class="menu-link px-3">'.e(__('ui.damage_common.housing_unit')).'</a>
                    </div>
                    <div class="menu-item px-3">
                        <a class="menu-link px-3" target="_blank" href="'.$assessmentUrl.'">'.e(__('ui.damage_common.assessment')).'</a>
                    </div>
                </div>';
            })
            ->setRowId('globalid')
            ->rawColumns(['action', 'id'])
            ->make(true);
    }

    public function edit(Request $request, $id)
    {
        $user = Building::find($id);

        if ($user) {
            return response()->json([
                'user' => $user,
            ]);
        }

        return response(['message' => __('ui.damage_common.operation_failed')], 500);
    }

    public function export_building(Request $request)
    {
        $data = $request->except(['_method', '_token', 'building_columns', 'format']);
        $format = $request->input('format');
        $buildingColumns = $request->input('building_columns');

        $filters = array_filter($data, fn ($value) => ! is_null($value) && $value !== '');
        $buildingColumns = array_filter($buildingColumns, fn ($value) => ! is_null($value) && $value !== '');

        $building = Building::select($buildingColumns)->where($filters)->get();

        $assessmentHints = Assessment::whereIn('name', $buildingColumns)
            ->get(['name', 'hint', 'label'])
            ->keyBy('name');

        if ($format == 'pdf') {
            return Pdf::view('pdf.building', compact('building', 'buildingColumns', 'assessmentHints'))
                ->format('a4')
                ->name('building-'.time().'.pdf');
        }

        return Excel::download(new TableExport($building, $buildingColumns, $assessmentHints), time().'building.'.$format);
    }

    public function update(Request $request)
    {
        $data = array_filter($request->all());

        if ($request->file()) {
            if (! $request->validate([
                'avatar' => 'required',
            ])) {
                return response()->json([
                    'success' => false,
                ]);
            }

            $fileName = time().'_file.'.$request->avatar->getClientOriginalName();
            $request->file('avatar')->storeAs('avatar', $fileName);
            $data['img'] = $fileName;
        }

        $user = user::find($data['id']);
        $user->update($data);

        if (isset($data['roles']) && $data['roles'] != null) {
            DB::table('model_has_roles')->where('model_id', $data['id'])->delete();
            $user->assignRole($data['roles']);
        }

        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => __('ui.damage_common.update_error'),
            ]);
        }

        return response()->json([
            'success' => true,
            'avatar' => $user->avatar,
            'message' => __('ui.damage_common.updated_success'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if (user::find($id)->delete()) {
            return response()->json([
                'message' => __('ui.damage_common.action_success'),
                'success' => true,
            ]);
        }

        return response(['message' => __('ui.damage_common.operation_failed')], 500);
    }
}
