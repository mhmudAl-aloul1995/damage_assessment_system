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
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Hash;
use Spatie\Permission\Models\Role;
use App\Models\HousingUnit;
use App\Models\Building;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use function Spatie\LaravelPdf\Support\pdf;
use App\Models\Filter;

class buildingController extends Controller
{

    function __construct()
    {
        $this->middleware('role:Database Officer|Project Officer');
    }

    public function index()
    {
        $engineers = Building::distinct('assignedto')->select('assignedto')->get();
        $owners = Building::distinct('owner_name')->select('owner_name')->get();
        $municip = Building::distinct('municipalitie')->select('municipalitie')->get();
        $neighborhoods = Building::distinct()->pluck('neighborhood');
        $assessments = Assessment::all();
        $filterName = Filter::distinct('list_name')->pluck('list_name');
        $filters = Filter::all();

        return View::make('DamageAssessment.buildings', compact('neighborhoods', 'filterName', 'filters', 'engineers', 'owners', 'municip', 'assessments'));
    }


    public function show(Request $request)
    {
        // dd($request->all());
        // 1. Determine columns based on source
        $isHomepage = $request->input('hompage_building') == 1;
        $select = $isHomepage
            ? ['assignedto', 'globalid', 'objectid', 'building_name', 'owner_name', 'zone_code', 'neighborhood']
            : ['assignedto', 'globalid', 'objectid', 'building_name', 'owner_name', 'zone_code', 'units_count', 'editdate', 'field_status', 'units_nos', 'damaged_units_nos', 'neighborhood', 'municipalitie'];

        // 2. Start the query (don't call ->get() yet!)
        $query = Building::query()->select($select)->where('assignedto', '!=', '');

        // 3. Apply Filters directly to the Query Builder
        $excludeKeys = ['order', '_', 'columns', 'draw', 'start', 'length', 'search', 'hompage_building'];
        $filters = $request->except($excludeKeys);
        foreach ($filters as $key => $value) {
            if (!is_null($value) && $value !== '') {
                $query->where($key, $value);
            }
        }
        $query->orderBy('owner_name', 'desc');
        $query->orderBy('neighborhood', 'asc');

        // 4. Let Datatables handle Pagination and AJAX processing
        return Datatables::of($query)
            ->editColumn('id', function ($ctr) {
                return '<div class="form-check form-check-sm form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" value="' . $ctr->id . '" />
                    </div>';
            })
            ->editColumn('action', function ($ctr) {
                // Using route() helpers is cleaner than url()
                $housingUrl = url("/showHousing/{$ctr->globalid}");
                $assessmentUrl = url("/assessment/{$ctr->globalid}");

                return '
                <a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">إجراءات
                    <i class="ki-duotone ki-down fs-5 ms-1"></i></a>
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                    <div class="menu-item px-3">
                        <a target="_blank" href="' . $housingUrl . '" class="menu-link px-3">الوحدة السكنية</a>
                    </div>
                    <div class="menu-item px-3">
                        <a class="menu-link px-3" target="_blank" href="' . $assessmentUrl . '">الإستبيان</a>
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
        return response(['message' => 'فشلت العملية'], 500);
    }

    public function export_building(Request $request)
    {
        $data = $request->except(['_method', '_token', 'building_columns', 'format']);
        $format = $request->input('format');
        $bulding_coulmn = $request->input('building_columns');

        // Clean up empty filters
        $filters = array_filter($data, fn($value) => !is_null($value) && $value !== '');
        $bulding_coulmn = array_filter($bulding_coulmn, fn($value) => !is_null($value) && $value !== '');

        // Fetch building data
        $building = Building::select($bulding_coulmn)->where($filters)->get();

        // OPTIMIZATION: Fetch all assessment hints/labels in ONE query
        $assessmentHints = Assessment::whereIn('name', $bulding_coulmn)
            ->get(['name', 'hint', 'label'])
            ->keyBy('name');

        if ($format == 'pdf') {
            // Pass $assessmentHints to the view
            return Pdf::view('pdf.building', compact('building', 'bulding_coulmn', 'assessmentHints'))
                ->format('a4')
                ->name('building-' . time() . '.pdf');
        } else {
            // Pass $assessmentHints to your Excel Export class
            return Excel::download(new TableExport($building, $bulding_coulmn, $assessmentHints), time() . 'building.' . $format);
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
            $data['img'] = $fileName; //'/storage/app/' . $filePath;

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
