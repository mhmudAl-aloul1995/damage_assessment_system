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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Database\Eloquent\Builder;
use Hash;
use Spatie\Permission\Models\Role;
use App\Models\HousingUnit;
use App\Models\Building;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use function Spatie\LaravelPdf\Support\pdf;
use App\Models\Filter;
use App\Models\User;
use App\Models\AssignedAssessmentUser;
use App\Models\BuildingStatusHistory;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use App\Models\HousingStatusHistory;
use App\Models\AssessmentStatus;

class auditController extends Controller
{
    public function index(Request $request)
    {

        if ($request->ajax()) {

            $query = Building::with([
                'assignedUsers.user',
                'engineerStatus.status',
                'lawyerStatus.status'
            ])->where('field_status', 'COMPLETED');

            if ($request->filled('building_name')) {
                $query->where('building_name', 'like', '%' . $request->building_name . '%');
            }

            if ($request->filled('area')) {
                $query->where('neighborhood', 'like', '%' . $request->area . '%');
            }

            if ($request->filled('engineer_id')) {
                $query->whereHas('engineerAssignment', function ($q) use ($request) {
                    $q->where('user_id', $request->engineer_id);
                });
            }

            if ($request->filled('lawyer_id')) {
                $query->whereHas('lawyerAssignment', function ($q) use ($request) {
                    $q->where('user_id', $request->lawyer_id);
                });
            }

            if ($request->filled('eng_status')) {
                if ($request->eng_status === 'pending') {
                    $query->whereDoesntHave('engineerStatus');
                } else {
                    $query->whereHas('engineerStatus.assessment_status', function ($q) use ($request) {
                        $q->where('name', $request->eng_status);
                    });
                }
            }

            if ($request->filled('legal_status')) {
                if ($request->legal_status === 'pending') {
                    $query->whereDoesntHave('lawyerStatus');
                } else {
                    $query->whereHas('lawyerStatus.assessment_status', function ($q) use ($request) {
                        $q->where('name', $request->legal_status);
                    });
                }
            }

            if ($request->filled('final_status')) {
                $query->whereHas('finalApproval.assessment_status', function ($q) use ($request) {
                    $q->where('name', $request->final_status);
                });
            }
            return DataTables::of($query)


                // Building Name
                ->editColumn(
                    'building_name',
                    fn($row) =>
                    '<span class="text-gray-800 fw-bold">' . $row->building_name . '</span>'
                )

                // Engineer Name
                ->addColumn('engineer', function ($row) {


                    return $row->assignedUsers
                        ->where('type', 'Engineering Auditor')
                        ->first()?->user?->name ?? '-';
                })

                // Lawyer Name
                ->addColumn('lawyer', function ($row) {
                    return $row->assignedUsers
                        ->where('type', 'Legal Auditor')
                        ->first()?->user?->name ?? '-';
                })
                // finalApproval 
                ->addColumn('finalApproval', function ($row) {

                    $status = $row->finalApproval?->status?->label_en ?? 'Pending';

                    $color = str_contains(strtolower($status), 'rejected')
                        ? 'badge-light-danger'
                        : 'badge-light-success';

                    return '<span class="badge ' . $color . ' fw-bold px-4 py-3">' . $status . '</span>';
                })

                // Engineer Status
                ->addColumn('eng_status', function ($row) {


                    $status = $row->engineerStatus?->status?->label_en ?? 'Pending';
                    $statusName = $row->engineerStatus?->status?->name ?? 'Pending';


                 

              

                    return '<span class="badge ' .$this->getStatusBadge($statusName). ' fw-bold px-4 py-3">' . e($status) . '</span>';
                })

                // Lawyer Status
                ->addColumn('law_status', function ($row) {

                    $status = $row->lawyerStatus?->status?->label_en ?? 'Pending';
                    $statusName = $row->engineerStatus?->status?->name ?? 'Pending';

                    return '<span class="badge ' . $this->getStatusBadge($statusName) . ' fw-bold px-4 py-3">' . $status . '</span>';
                })
                ->addColumn('actions', function ($row) {
                    $assessmentUrl = url("/showAssessmentAudit/{$row->globalid}");

                    return '
    <div class="d-flex justify-content-end">
        <button class="btn btn-light btn-sm"
            data-kt-menu-trigger="click"
            data-kt-menu-placement="bottom-end">
            إجراءات
        </button>

        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4"
             data-kt-menu="true">

            <div class="menu-item px-3">
                <a target="_blank" href="' . $assessmentUrl . '" class="menu-link px-3">الإستبيان</a>
            </div>
         
        </div>
    </div>
    ';
                })

                ->rawColumns(['building_name', 'eng_status', 'law_status', 'actions', 'finalApproval'])
                ->make(true);
        }

        $users = User::where("id", '!=', Auth::user()->id)->get();
        $engineers = Building::distinct('assignedto')->select('assignedto')->get();
        $owners = Building::distinct('owner_name')->select('owner_name')->get();
        $municip = Building::distinct('municipalitie')->select('municipalitie')->get();
        $neighborhoods = Building::distinct()->pluck('neighborhood');
        $assessments = Assessment::all();
        $filterName = Filter::distinct('list_name')->pluck('list_name');
        $filters = Filter::all();
        $engineers = User::role('Engineering Auditor')->get();
        $lawyers = User::role('Legal Auditor')->get();

        return View::make(
            'DamageAssessment.audit',
            compact('engineers', 'lawyers', 'users', 'neighborhoods', 'filterName', 'filters', 'engineers', 'owners', 'municip', 'assessments')
        );
    }

    public function housingUnitsByBuilding(Request $request)
    {
        $query = HousingUnit::query();

        if ($request->globalid) {
            $query->where('parentglobalid', $request->globalid);
        }
        $type = auth()->user()->roles->first()->name;

        return DataTables::of($query->orderBy('floor_number', 'asc')
            ->orderBy('housing_unit_number', 'asc'))


            ->addColumn('current_status', function ($row) use ($type) {
                return optional($row->statusByType($type)?->first()?->assessment_status)->name;
            })

            ->editColumn('owner_name', function ($row) {
                // لو عندك full_name بدل owner_name
                return $row->owner_name ?? $row->full_name ?? '-';
            })

            ->editColumn('unit_direction', function ($row) {
                return $row->unit_direction ?? '-';
            })


            // finalApproval 
            ->addColumn('final_approval_status', function ($row) {

                $status = $row->finalApproval?->assessment_status?->label_en ?? 'Pending';

                $statusName = strtolower($status);

                if (str_contains($statusName, 'reject')) {
                    $color =  'badge-danger';
                } elseif (str_contains($statusName, 'accepted')) {
                    $color = 'badge-success';
                } elseif (str_contains($statusName, 'review')) {
                    $color = 'badge-warning';
                } elseif (str_contains($statusName, 'assigned')) {
                    $color = 'badge-primary';
                } else {
                    $color = 'badge-secondary';
                }

                return '<span class="badge ' . $color . ' fw-bold px-4 py-3">' . e($status) . '</span>';
            })

            // Engineer Status
            ->addColumn('engineering_audit_status', function ($row) {

                $status = $row->engineerStatus?->assessment_status?->label_en ?? 'Pending';



                $statusName = strtolower($status);

                if (str_contains($statusName, 'reject')) {
                    $color =  'badge-danger';
                } elseif (str_contains($statusName, 'accepted')) {
                    $color = 'badge-success';
                } elseif (str_contains($statusName, 'review')) {
                    $color = 'badge-warning';
                } elseif (str_contains($statusName, 'assigned')) {
                    $color = 'badge-primary';
                } else {
                    $color = 'badge-secondary';
                }

                return '<span class="badge ' . $color . ' fw-bold px-4 py-3">' . e($status) . '</span>';
            })

            // Lawyer Status
            ->addColumn('legal_audit_status', function ($row) {

                $status = $row->lawyerStatus?->assessment_status?->label_en ?? 'Pending';

                $statusName = strtolower($status);

                if (str_contains($statusName, 'reject')) {
                    $color =  'badge-danger';
                } elseif (str_contains($statusName, 'accepted')) {
                    $color = 'badge-success';
                } elseif (str_contains($statusName, 'review')) {
                    $color = 'badge-warning';
                } elseif (str_contains($statusName, 'assigned')) {
                    $color = 'badge-primary';
                } else {
                    $color = 'badge-secondary';
                }

                return '<span class="badge ' . $color . ' fw-bold px-4 py-3">' . e($status) . '</span>';
            })

            ->rawColumns([
                'legal_audit_status',
                'engineering_audit_status',
                'final_approval_status'
            ])

            ->make(true);
    }


    public function setStatus(Request $request)
    {
        $request->validate([
            'globalid' => ['required', 'string'],
            'status'   => ['required', 'in:rejected,accepted,need_review'],
            'notes'    => ['nullable', 'string'],
        ]);

        DB::beginTransaction();

        try {
            $user = Auth::user();

            $building = Building::where('globalid', $request->globalid)->first();

            if (!$building) {
                return response()->json([
                    'status' => false,
                    'message' => 'المبنى غير موجود',
                ], 404);
            }

            $type = null;

            if ($user->hasRole('Engineering Auditor')) {
                $type = 'Engineering Auditor';
            } elseif ($user->hasRole('Legal Auditor')) {
                $type = 'Legal Auditor';
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'ليس لديك صلاحية لتحديث حالة المبنى',
                ], 403);
            }

            $roleType = $type === 'Engineering Auditor' ? 'engineer' : 'lawyer';

            $statusMap = [
                'rejected'    => 'rejected_by_' . $roleType,
                'accepted'    => 'accepted_by_' . $roleType,
                'need_review' => 'need_review',
            ];

            $statusName = $statusMap[$request->status] ?? null;

            $assessmentStatus = AssessmentStatus::where('name', $statusName)->first();

            if (!$assessmentStatus) {
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير موجودة في جدول AssessmentStatus',
                ], 422);
            }

            $buildingStatus = BuildingStatus::updateOrCreate(
                [
                    'building_id' => $building->objectid,
                    'type'       => $type,
                ],
                [
                    'status_id' => $assessmentStatus->id,
                    'user_id'   => Auth::id(),
                    'notes'     => $request->notes,
                ]
            );

            BuildingStatusHistory::create([
                'building_id' => $building->objectid,
                'status_id'   => $assessmentStatus->id,
                'user_id'     => Auth::id(),
                'notes'       => $request->notes,
                'type'        => $type,
            ]);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث حالة المبنى بنجاح',
                'data'    => [
                    'building_objectid' => $building->objectid,
                    'building_globalid' => $building->globalid,
                    'type'              => $type,
                    'status_id'         => $assessmentStatus->id,
                    'status_name'       => $assessmentStatus->name,
                    'record_id'         => $buildingStatus->id,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء تحديث حالة المبنى',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function setHousingStatus(Request $request)
    {
        $request->validate([
            'globalid' => ['required', 'string'],
            'status'   => ['required', 'in:rejected,accepted,need_review'],
            'notes'    => ['nullable', 'string'],
        ]);


        DB::beginTransaction();

        try {
            $user = Auth::user();

            $housing = HousingUnit::where('globalid', $request->globalid)->first();

            if (!$housing) {
                return response()->json([
                    'status' => false,
                    'message' => 'الوحدة السكنية غير موجودة',
                ], 404);
            }

            $type = null;

            if ($user->hasRole('Engineering Auditor')) {
                $type = 'Engineering Auditor';
            } elseif ($user->hasRole('Legal Auditor')) {
                $type = 'Legal Auditor';
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'ليس لديك صلاحية لتحديث حالة الوحدة',
                ], 403);
            }

            $roleType = $type === 'Engineering Auditor' ? 'engineer' : 'lawyer';

            $statusMap = [
                'rejected'    => 'rejected_by_' . $roleType,
                'accepted'    => 'accepted_by_' . $roleType,
                'need_review' => 'need_review',
            ];
            $statusName = $statusMap[$request->status] ?? null;

            $assessmentStatus = AssessmentStatus::where('name', $statusName)->first();

            if (!$assessmentStatus) {
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير موجودة في جدول AssessmentStatus',
                ], 422);
            }

            $housingStatus = HousingStatus::updateOrCreate(
                [
                    'housing_id' => $housing->objectid,
                    'type'       => $type,
                ],
                [
                    'status_id' => $assessmentStatus->id,
                    'user_id'   => Auth::id(),
                    'notes'     => $request->notes,
                ]
            );

            HousingStatusHistory::create([
                'housing_id' => $housing->objectid,
                'status_id'  => $assessmentStatus->id,
                'user_id'    => Auth::id(),
                'notes'      => $request->notes,
            ]);



            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث حالة الوحدة بنجاح',
                'data'    => [
                    'housing_objectid' => $housing->objectid,
                    'housing_globalid' => $housing->globalid,
                    'type'             => $type,
                    'status_id'        => $assessmentStatus->id,
                    'status_name'      => $assessmentStatus->name,
                    'record_id'        => $housingStatus->id,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء تحديث حالة الوحدة',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function assign(Request $request)
    {
        $request->validate([
            'building_ids' => ['required', 'array'],
            'building_ids.*' => ['required', 'exists:buildings,objectid'],
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'string'],
            'status_id' => ['nullable', 'exists:assessment_statuses,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->building_ids as $buildingId) {

                    $building = Building::where('objectid', $buildingId)->first();

                    if (!$building) {
                        continue;
                    }

                    AssignedAssessmentUser::updateOrCreate(
                        [
                            'building_id' => $buildingId,
                            'type' => $request->type,
                        ],
                        [
                            'user_id' => $request->user_id,
                            'manager_id' => Auth::id(),
                            'type' => $request->type,
                        ]
                    );

                    if (!$request->filled('status_id')) {
                        continue;
                    }

                    $buildingStatus = BuildingStatus::firstOrNew([
                        'building_id' => $buildingId,
                        'type' => $request->type,
                    ]);

                    $statusChanged =
                        !$buildingStatus->exists ||
                        (int) $buildingStatus->status_id !== (int) $request->status_id;

                    $buildingStatus->status_id = $request->status_id;
                    $buildingStatus->user_id = $request->user_id;
                    $buildingStatus->notes = $request->notes;
                    $buildingStatus->type = $request->type;
                    $buildingStatus->save();

                    if ($statusChanged) {
                        BuildingStatusHistory::create([
                            'building_id' => $buildingId,
                            'status_id' => $request->status_id,
                            'user_id' => Auth::id(),
                            'notes' => $request->notes,
                            'type' => $request->type,
                        ]);
                    }

                    $housings = HousingUnit::where('parentglobalid', $building->globalid)->get();


                    foreach ($housings as $housing) {
                        $housingStatus = HousingStatus::firstOrNew([
                            'housing_id' => $housing->objectid,
                            'type' => $request->type,
                        ]);

                        $housingStatusChanged = !$housingStatus->exists || (int) $housingStatus->status_id !== (int) $request->status_id;

                        $housingStatus->status_id = $request->status_id;
                        $housingStatus->user_id = $request->user_id;
                        $housingStatus->notes = $request->notes;
                        $housingStatus->type = $request->type;
                        $housingStatus->save();

                        if ($housingStatusChanged) {
                            HousingStatusHistory::create([
                                'housing_id' => $housing->objectid,
                                'status_id' => $request->status_id,
                                'user_id' => Auth::id(),
                                'notes' => $request->notes,
                                'type' => $request->type,
                            ]);
                        }
                    }
                }
            });

            return response()->json([
                'message' => 'Assignment completed successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assessmentAudit(Request $request)
    {
        if ($request->ajax()) {


            $user = Auth::user();
            $type = $user->hasRole('Engineering Auditor') ? 'Engineering Auditor' : ($user->hasRole('Legal Auditor') ? 'Legal Auditor' : null);

            if (!$type) {
                abort(403, 'Unauthorized');
            }
            if (!in_array($type, ['eng', 'lawyer'])) {
                abort(403, 'Unauthorized');
            }

            $statusRelation = $type === 'eng' ? 'engineerStatus.status' : 'lawyerStatus.status';

            $data = Building::with([
                'assignedUsers.user',
                $statusRelation
            ])->whereHas('assignedUsers', function ($q) use ($type, $user) {
                $q->where('type', $type)
                    ->where('user_id', $user->id);
            });

            return DataTables::of($data)
                ->addIndexColumn()

                ->editColumn('building_name', function ($row) {
                    return '<span class="text-gray-800 fw-bold">' . ($row->building_name ?? '-') . '</span>';
                })



                ->addColumn('status', function ($row) use ($type) {
                    $statusModel = $type === 'eng'
                        ? $row->engineerStatus?->status
                        : $row->lawyerStatus?->status;

                    $status = $statusModel?->label_en ?? 'Pending';
                    $statusName = strtolower($statusModel?->name ?? 'pending');
                  

                    return '<span class="badge ' . $this->getStatusBadge($statusName) . ' fw-bold px-4 py-3">' . e($status) . '</span>';
                })

                ->addColumn('actions', function ($row) {
                    $assessmentUrl = url("/showAassessmentAudit/{$row->globalid}");

                    return '
    <div class="d-flex justify-content-end">
        <button class="btn btn-light btn-sm"
            data-kt-menu-trigger="click"
            data-kt-menu-placement="bottom-end">
            إجراءات
        </button>

        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4"
             data-kt-menu="true">

            <div class="menu-item px-3">
                <a target="_blank" href="' . $assessmentUrl . '" class="menu-link px-3">الإستبيان</a>
            </div>
         
        </div>
    </div>
    ';
                })

                ->rawColumns(['building_name', 'status', 'actions'])
                ->make(true);
        }
        $users = User::where('id', '!=', Auth::id())->get();
        $engineers = Building::distinct('assignedto')->select('assignedto')->get();
        $owners = Building::distinct('owner_name')->select('owner_name')->get();
        $municip = Building::distinct('municipalitie')->select('municipalitie')->get();
        $neighborhoods = Building::distinct()->pluck('neighborhood');
        $assessments = Assessment::all();
        $filterName = Filter::distinct('list_name')->pluck('list_name');
        $filters = Filter::all();

        return View::make('DamageAssessment.auditBuilding', compact(
            'users',
            'neighborhoods',
            'filterName',
            'filters',
            'engineers',
            'owners',
            'municip',
            'assessments'
        ));
    }

    public function showAssessmentAudit(Request $request)
    {


        $globalid = $request->globalid;

        $building = Building::where('globalid', $request->globalid)->first();
        $HousingUnit = HousingUnit::where('parentglobalid', $request->globalid)->get();
        $assessments = Assessment::all();
        $buildingCurrentStatus = BuildingStatus::with('status')
            ->whereHas('building', function ($q) use ($globalid) {
                $q->where('globalid', $globalid);
            })
            ->first()?->status?->name;

        return View::make('DamageAssessment.assessmentAudit', compact('buildingCurrentStatus', 'globalid', 'building', 'assessments', 'HousingUnit'));
    }

    public function auditBuilding(Request $request)
    {
        if ($request->ajax()) {


            $user = Auth::user();

            $type = $user->hasRole('Engineering Auditor') ? 'Engineering Auditor' : ($user->hasRole('Legal Auditor') ? 'Legal Auditor' : null);

            /*  if (!$type) {
                abort(403, 'Unauthorized');
            }
            if (!in_array($type, ['eng', 'lawyer'])) {
                abort(403, 'Unauthorized');
            } */

            $statusRelation = $type === 'eng' ? 'engineerStatus.status' : 'lawyerStatus.status';

            $data = Building::with([
                'assignedUsers.user',
                $statusRelation
            ])->whereHas('assignedUsers', function ($q) use ($type, $user) {
                $q->where('type', $type)
                    ->where('user_id', $user->id);
            });

            return DataTables::of($data)
                ->addIndexColumn()

                ->editColumn('building_name', function ($row) {
                    return '<span class="text-gray-800 fw-bold">' . ($row->building_name ?? '-') . '</span>';
                })

                ->addColumn('assigned_user', function ($row) use ($type) {
                    return $row->assignedUsers
                        ->where('type', $type)
                        ->first()?->user?->name ?? '-';
                })

                ->addColumn('status', function ($row) use ($type) {
                    $statusModel = $type === 'Engineering Auditor'
                        ? $row->engineerStatus?->status
                        : $row->lawyerStatus?->status;

                    $status = $statusModel?->label_en ?? 'Pending';
                    $statusName = strtolower($statusModel?->name ?? 'pending');

                

                    return '<span class="badge ' . $this->getStatusBadge($statusName) . ' fw-bold px-4 py-3">' . e($status) . '</span>';
                })
                ->editColumn('actions', function ($ctr) {
                    // Using route() helpers is cleaner than url()
                    $assessmentUrl = url("/showAssessmentAudit/{$ctr->globalid}");

                    return '
                <a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">إجراءات
                    <i class="ki-duotone ki-down fs-5 ms-1"></i></a>
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                    
                    <div class="menu-item px-3">
                        <a class="menu-link px-3" target="_blank" href="' . $assessmentUrl . '">الإستبيان</a>
                    </div>
                </div>';
                })

                ->rawColumns(['building_name', 'status', 'actions'])
                ->make(true);
        }
        $users = User::where('id', '!=', Auth::id())->get();
        $engineers = Building::distinct('assignedto')->select('assignedto')->get();
        $owners = Building::distinct('owner_name')->select('owner_name')->get();
        $municip = Building::distinct('municipalitie')->select('municipalitie')->get();
        $neighborhoods = Building::distinct()->pluck('neighborhood');
        $assessments = Assessment::all();
        $filterName = Filter::distinct('list_name')->pluck('list_name');
        $filters = Filter::all();

        return View::make('DamageAssessment.auditBuilding', compact(
            'users',
            'neighborhoods',
            'filterName',
            'filters',
            'engineers',
            'owners',
            'municip',
            'assessments'
        ));
    }
    public function updateInlineAssessment(Request $request)
    {
        $request->validate([
            'type' => 'required|in:building_table,housing_table',
            'globalid' => 'required|string',
            'field' => 'required|string',
            'value' => 'nullable',
        ]);

        $modelClass = $request->type === 'building_table'
            ? \App\Models\Building::class
            : \App\Models\HousingUnit::class;

        $fillable = (new $modelClass())->getFillable();

        if (!in_array($request->field, $fillable)) {
            return response()->json([
                'status' => false,
                'message' => 'هذا الحقل غير قابل للتعديل'
            ], 422);
        }

        $value = $request->value;

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        \App\Models\EditAssessment::create(
            [
                'global_id' => $request->globalid,
                'type' => $request->type,
                'field_name' => $request->field,
                'user_id' => auth()->id(),
                'field_value' => $value,

            ]

        );

        return response()->json([
            'status' => true,
            'message' => 'تم حفظ التعديل بنجاح'
        ]);
    }

    public function housingUnitAudit(Request $request)
    {
        $globalid = $request->globalid;
        $data = HousingUnit::query()->where('global_id', $globalid);

        return DataTables::of($data)

            ->addColumn('final_approval_status', function ($row) {
                return $row->final_approval_status ?? 'Pending';
            })

            ->addColumn('legal_audit_status', function ($row) {
                $status = $row->legal_audit_status ?? '-';

                if ($status === 'Rejected By Lawyer') {
                    return '<span class="badge badge-light-danger w-100 d-inline-block py-3">' . $status . '</span>';
                }

                return '<span class="badge badge-light-warning">' . $status . '</span>';
            })

            ->addColumn('engineering_audit_status', function ($row) {
                return $row->engineering_audit_status ?? '-';
            })

            ->addColumn('unit_direction', function ($row) {
                return $row->unit_direction ?? '-';
            })

            ->addColumn('owner_name', function ($row) {
                return $row->owner_name ?? '-';
            })

            ->addColumn('unit_number', function ($row) {
                return $row->housing_unit_number ?? '-';
            })

            ->addColumn('floor_number', function ($row) {
                return $row->floor_number ?? '-';
            })

            ->addColumn('damage_status', function ($row) {
                return $row->unit_damage_status ?? '-';
            })

            ->addColumn('unit_type', function ($row) {
                return $row->housing_unit_type ?? '-';
            })

            ->rawColumns(['edit', 'legal_audit_status'])
            ->make(true);
    }

    public function buildingHistory(Request $request)
    {
        $building = Building::where('globalid', $request->globalid)->first();

        if (!$building) {
            return [];
        }

        return BuildingStatusHistory::with(['user.roles', 'status'])
            ->where('building_id', $building->objectid)
            ->latest()
            ->get()
            ->map(function ($item) {
                $statusName  = $item->status->name ?? '-';
                $statusLabel = $item->status->label_en ?? $statusName;
                $roleName    = $item->user?->roles?->first()?->name ?? '-';

                return [
                    'status_name' => '<span class="' . $this->getStatusBadge($statusName, $roleName) . '">' . e($statusLabel) . '</span>',
                    'user_name'   => $item->user->name ?? '-',
                    'role_name'   => $roleName,
                    'notes'       => $item->notes ?? '-',
                    'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                ];
            });
    }

    public function housingHistory(Request $request)
    {
        $housing = HousingUnit::where('globalid', $request->globalid)->first();

        if (!$housing) {
            return [];
        }

        return HousingStatusHistory::with(['user.roles', 'assessment_status'])
            ->where('housing_id', $housing->objectid)
            ->latest()
            ->get()
            ->map(function ($item) {
                $statusName  = $item->assessment_status->name ?? '-';
                $statusLabel = $item->assessment_status->label_en ?? $statusName;
                $roleName    = $item->user?->roles?->first()?->name ?? '-';

                return [
                    'status_name' => '<span class="' . $this->getStatusBadge($statusName, $roleName) . '">' . e($statusLabel) . '</span>',
                    'user_name'   => $item->user->name ?? '-',
                    'role_name'   => $roleName,
                    'notes'       => $item->notes ?? '-',
                    'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                ];
            });
    }

    private function getStatusBadge($statusName, $role = null)
    {
        return match ($statusName) {
            'assigned_to_lawyer' => 'badge badge-light-primary fw-bold',
            'assigned_to_engineer' => 'badge badge-light-primary fw-bold',
            'accepted_by_engineer',
            'accepted'             => 'badge badge-light-success fw-bold',
            'rejected_by_engineer',
            'rejected'             => 'badge badge-light-danger fw-bold',
            'need_review'          => 'badge badge-light-warning fw-bold',
            'legal_notes'          => 'badge badge-light-primary fw-bold',
            default                => 'badge badge-light-secondary fw-bold',
        };
    }
}
