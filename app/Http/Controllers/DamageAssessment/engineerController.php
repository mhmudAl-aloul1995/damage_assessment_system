<?php
namespace App\Http\Controllers\DamageAssessment;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Buildings;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Enginges\EloquentEngine;
use Illuminate\Support\Facades\DB;
use View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\HousingUnit;
use App\Models\Building;
use App\Exports\BuildingExport;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;

class engineerController extends Controller
{
    /*
        function __construct()
        {
            $this->middleware('permission:user-list|user-create|user-edit|user-delete', ['only' => ['index', 'show']]);
            $this->middleware('permission:user-create', ['only' => ['create', 'store']]);
            $this->middleware('permission:user-edit', ['only' => ['edit', 'update']]);
            $this->middleware('permission:user-delete', ['only' => ['destroy']]);
        }*/

    public function index()
    {
        $engineers = Building::distinct('assignedto')->select(columns: 'assignedto')->get();

        return View::make('DamageAssessment.engineers', compact('engineers'));

    }

    public function engineerAssessments(Request $request)
    {
        $completed = Building::where('assignedto', $request->assignedto)
            ->where('field_status', 'COMPLETED')
            ->count();

        $notCompleted = Building::where('assignedto', $request->assignedto)
            ->where('field_status', 'NOT_COMPLETED')
            ->count();

        $total = Building::where('assignedto', $request->assignedto)->count();
        $completion = $total > 0 ? ($completed / $total) * 100 : 0;

        $assignedto = $request->assignedto;



        return View::make('DamageAssessment.engineerAssessments', compact('assignedto', 'completion', 'completed', 'notCompleted'));
    }

    public function filter(Request $request)
    {

        $status = $request->status;
        $assignedto = $request->assignedto;
        $search = $request->search;

        $query = Building::where('assignedto', $assignedto)
            ->with('housing_unit:parentglobalid,q_13_3_1_first_name,q_13_3_4_last_name__family,objectid')
            ->select([
                'building_damage_status',
                'objectid',
                'field_status',
                'damaged_units_nos',
                'date_of_damage',
                'assignedto',
                'owner_name',
                'building_name',
                'globalid'
            ]);

        if ($status != 'all') {
            $query->where('field_status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {

                $q->where('owner_name', 'like', "%$search%")
                    ->orWhere('building_name', 'like', "%$search%")
                    ->orWhere('objectid', 'like', "%$search%");

            });
        }

        $engineers = $query->paginate(9)->appends([
            'status' => $status,
            'assignedto' => $assignedto,
            'search' => $search
        ]);

        $building_damage_ststus = [
            'fully_damaged' => 'ضرر كلي',
            'partially_damaged' => 'ضرر جزئي',
            'committee_review' => 'مراجعة لجنة',
            '' => 'غير محدد'
        ];

        return view(
            'DamageAssessment.partials.engineers_cards',
            compact('engineers', 'building_damage_ststus')
        )->render();

    }
    public function showAssessment(Request $request)
    {
        $globalid = $request->globalid;

        $building = Building::where('globalid', $request->globalid)->first();
        $HousingUnit = HousingUnit::where('parentglobalid', $request->globalid)->get();
        $assessments = Assessment::all();

        return View::make('DamageAssessment.assessment', compact('globalid', 'building', 'assessments', 'HousingUnit'));

    }
    public function show(Request $request)
    {
        $data = $request->all();


        $engineers = Building::distinct('assignedto')->select('assignedto');


        return Datatables::of($engineers)


            ->addColumn('all', function ($ctr) {
                return $ctr->where(['assignedto' => $ctr->assignedto])->count();
            })
            ->addColumn('complete', function ($ctr) {
                return $ctr->where(['field_status' => 'COMPLETED', 'assignedto' => $ctr->assignedto])->count();
            })
            ->addColumn('in_complete', function ($ctr) {
                return $ctr->where(['field_status' => 'NOT_COMPLETED', 'assignedto' => $ctr->assignedto])->count();
            })

            ->addColumn('action', function ($ctr) {

                return '<a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">إجراءات
															<i class="ki-duotone ki-down fs-5 ms-1"></i></a>
															<!--begin::Menu-->
															<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
																<!--begin::Menu item-->
																<div class="menu-item px-3">
																	<a onclick="showModal(`user`,' . $ctr->id . ')" href="javascript:;" class="menu-link px-3">تعديل</a>
																</div>
																<!--end::Menu item-->
																<!--begin::Menu item-->
																<div class="menu-item px-3">
																	<a href="#" class="menu-link px-3" data-kt-users-table-filter="delete_row">حذف</a>
																</div>
																<!--end::Menu item-->
															</div>';
            })
            ->rawColumns(['action' => 'action', 'id' => 'id'])
            ->make(true);
    }


}
