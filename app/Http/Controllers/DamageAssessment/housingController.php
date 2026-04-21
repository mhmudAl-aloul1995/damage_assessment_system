<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Exports\TableExport;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Assessment;
use App\Models\Building;
use App\Models\Filter;
use App\Models\HousingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use View;
use Yajra\Datatables\Datatables;

class housingController extends Controller
{
    public function index($globalid = null)
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

        return View::make('DamageAssessment.housing', [
            'filters' => $filters,
            'filterName' => $filterName,
            'globalid' => $globalid,
            'areas' => Area::query()->get(),
            'engineers' => Building::query()->distinct()->orderBy('assignedto')->pluck('assignedto')->filter()->values(),
            'owners' => Building::query()->distinct()->orderBy('owner_name')->pluck('owner_name')->filter()->values(),
            'municip' => Building::query()->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),
            'assessments' => Assessment::query()->get(),
            'groupedFilters' => $filters->groupBy('list_name'),
        ]);
    }

    public function show(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            if ($value == null || $value == '' || in_array($key, ['_', 'columns', 'draw', 'start', 'length', 'search'], true)) {
                unset($data[$key]);
            }
        }

        $users = HousingUnit::query();

        if (count($data) > 0) {
            $users->where($data);
        }

        return Datatables::of($users->orderBy('objectid', 'asc'))
            ->editColumn('assignedto', function ($ctr) {
                return Building::where('globalid', $ctr->parentglobalid)->first()['assignedto'];
            })
            ->editColumn('id', function ($ctr) {
                return '<div class="form-check form-check-sm form-check-custom form-check-solid">
																<input class="form-check-input" type="checkbox" value="'.$ctr->id.'" />
															</div>';
            })
            ->editColumn('action', function ($ctr) {
                return '<a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">'.e(__('ui.damage_common.actions')).'
															<i class="ki-duotone ki-down fs-5 ms-1"></i></a>
															<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
																<div class="menu-item px-3">
																	<a class="menu-link px-3" target="_blank" href="'.url('').'/assessment/'.$ctr->parentglobalid.'" data-kt-users-table-filter="delete_row">'.e(__('ui.damage_common.assessment')).'</a>
																</div>
															</div>';
            })
            ->setRowId('{{$objectid}}')
            ->rawColumns(['action' => 'action', 'id' => 'id'])
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
        $data = $request->all();

        $format = $data['format'];
        $housingColumns = $data['housing_columns'];
        unset($data['_method'], $data['_token'], $data['housing_columns'], $data['format']);

        foreach ($data as $key => $value) {
            if ($value == null || $value == '' || in_array($key, ['_method', 'housing_columns', 'format', '_token'], true)) {
                unset($data[$key]);
            }
        }

        $housing = HousingUnit::select($housingColumns)->where($data)->get();

        if ($format == 'pdf') {
            return Pdf::view('pdf.building', compact('building', 'housingColumns'))
                ->format('a4')
                ->name('building-'.time().'.pdf');
        }

        return Excel::download(new TableExport($housing, $housingColumns), time().'housing.'.$format);
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
