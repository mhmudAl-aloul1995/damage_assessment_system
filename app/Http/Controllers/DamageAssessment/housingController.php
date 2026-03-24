<?php

namespace App\Http\Controllers\DamageAssessment;
use App\Exports\TableExport;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Buildings;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Project;
use Yajra\Datatables\Datatables;
use Rap2hpoutre\FastExcel\FastExcel;
use Yajra\Datatables\Enginges\EloquentEngine;
use Illuminate\Support\Facades\DB;
use View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Hash;
use Spatie\Permission\Models\Role;
use App\Models\HousingUnit;
use App\Models\Building;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use App\Models\Area;
use App\Models\Filter;
class housingController extends Controller
{
    /*
        function __construct()
        {
            $this->middleware('permission:user-list|user-create|user-edit|user-delete', ['only' => ['index', 'show']]);
            $this->middleware('permission:user-create', ['only' => ['create', 'store']]);
            $this->middleware('permission:user-edit', ['only' => ['edit', 'update']]);
            $this->middleware('permission:user-delete', ['only' => ['destroy']]);
        }*/

    public function index($globalid = null)
    {
        $engineers = Building::distinct('assignedto')->select('assignedto')->get();
        $owners = Building::distinct('owner_name')->select('owner_name')->get();
        $municip = Building::distinct('municipalitie')->select('municipalitie')->get();
        $assessments = Assessment::all();
        $areas = Area::all();
        $filterName = Filter::distinct('list_name')->pluck('list_name');
        $filters = Filter::all();


        return View::make('DamageAssessment.housing', compact('filters', 'filterName', 'globalid', 'areas', 'engineers', 'owners', 'municip', 'assessments'));

    }


    public function show(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {

            if ($value == null || $value == '' || $key == '_' || $key == 'columns' || $key == 'draw' || $key == 'start' || $key == 'length' || $key == 'search') {
                unset($data[$key]);
            }
        }
        
/* 
        $marged_data = array_merge(
            array_keys($data),
            [
                'globalid',
                'objectid',
                'housing_unit_type',
                'unit_damage_status',
                'floor_number',
                'housing_unit_number',
                'unit_direction',
                'damaged_area_m2',
                'infra_type2',
                'house_unit_ownership',
                'number_of_rooms',
                'q_9_3_1_first_name',
                'q_9_3_4_last_name',
                'q_9_3_2_second_name__father',
                'parentglobalid'

            ]
        ); */

        $users = HousingUnit::query();

        if (count($data) > 0) {

            $users->where($data);
        }

        return Datatables::of($users->orderBy('objectid', 'asc'))
            /*   ->addColumn('roles', function ($ctr) {
                  $roles = '';
                  $color = ['', '#e91e63', '#4caf50', '#00bcd4', '#ff5722', '#9c27b0', '#cddc39', '#4caf50', '#009688', '#673ab7', '#4caf50',];
                  foreach ($ctr->roles as $key => $value) {
                      $roles .= '<span  style=" font-size:70%;  margin:1px; color:white;background-color:' . $color[$value->id] . '" class=" btn  btn-group-red">' . $value->name . '</span>';
                  }
                  return $roles;

              }) */

            ->editColumn('assignedto', function ($ctr) {
                return Building::where('globalid', $ctr->parentglobalid)->first()['assignedto'];

            })

            ->editColumn('id', function ($ctr) {
                return '<div class="form-check form-check-sm form-check-custom form-check-solid">
																<input class="form-check-input" type="checkbox" value="' . $ctr->id . '" />
															</div>';
            })
            ->editColumn('action', function ($ctr) {

                return '<a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">إجراءات
															<i class="ki-duotone ki-down fs-5 ms-1"></i></a>
															<!--begin::Menu-->
															<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
															
																<!--begin::Menu item-->
																<div class="menu-item px-3">
																	<a  class="menu-link px-3" target="_blank"  href="' . url("") . '/assessment/' . $ctr->parentglobalid . '" data-kt-users-table-filter="delete_row">الإستبيان</a>
																</div>
																<!--end::Menu item-->
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
        return response(['message' => 'فشلت العملية'], 500);

    }

    public function export_building(Request $request)
    {
        $data = $request->all();

        $format = $data['format'];
        $housing_columns = $data['housing_columns'];
        unset($data['_method']);
        unset($data['_token']);
        unset($data['housing_columns']);
        unset($data['format']);

        foreach ($data as $key => $value) {

            if ($value == null || $value == '' || $key == '_method' || $key == 'housing_columns' || $key == 'format' || $key == '_token') {
                unset($data[$key]);
            }
        }
        $housing = HousingUnit::select($housing_columns)->where($data)->get();

        if ($format == 'pdf') {


            return Pdf::view('pdf.building', compact('building', 'housing_columns'))
                ->format('a4')
                ->name('building-' . time() . '.pdf');

        } else {

            return Excel::download(new TableExport($housing, $housing_columns), time() . 'housing.' . $format);

        }



    }


    public function update(Request $request)
    {
        $data = $request->all();
        $data = array_filter($data);
        if ($request->file()) {

            if (
                !$request->validate([
                    "avatar" => "required"
                ])
            ) {
                return response()->json([
                    'success' => FALSE,
                    // 'message' => "يجب أن يكون الملف pdf"

                ]);
            }
            $fileName = time() . '_file.' . $request->avatar->getClientOriginalName();
            $filePath = $request->file('avatar')->storeAs('avatar', $fileName);
            $data['img'] = $fileName;//'/storage/app/' . $filePath;

        }

        $user = user::find($data['id']);
        $user->update($data);

        if (isset($data['roles']) && $data['roles'] != null) {

            DB::table('model_has_roles')->where('model_id', $data['id'])->delete();
            $user->assignRole($data['roles']);
        }
        if (!$user) {
            return response()->json([
                'success' => TRUE,
                'message' => "حدث حطأ أثناء التعديل"

            ]);
        }
        return response()->json([
            'success' => TRUE,
            'avatar' => $user->avatar,
            'message' => "تم التعديل بنجاح"
        ]);
    }

    public function destroy(Request $request, $id)
    {

        if (user::find($id)->delete()) {
            return response()->json([
                'message' => 'تمت العملية بنجاح',
                'success' => true
            ]);
        }

        return response(['message' => 'فشلت العملية'], 500);
    }


}
