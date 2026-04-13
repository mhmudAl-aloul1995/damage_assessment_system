<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\Filter;
use App\Models\HousingUnit;
use App\services\ArcgisService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Yajra\Datatables\Datatables;

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

        $completion = $total > 0 ? $completed / $total * 100 : 0;
        $completion = intval($completion);
        $assignedto = $request->assignedto;

        return View::make('DamageAssessment.engineerAssessments', compact('assignedto', 'completion', 'completed', 'notCompleted'));
    }

    public function assessmentAll(Request $request)
    {
        return View::make('DamageAssessment.assessmentAll');
    }

    public function filter(Request $request)
    {
        $status = $request->status;
        $assignedto = $request->assignedto;
        $search = $request->search;
        $query = Building::with('housing_unit:parentglobalid,q_13_3_1_first_name,q_13_3_4_last_name__family,objectid')
            ->select([
                'building_damage_status',
                'objectid',
                'field_status',
                'damaged_units_nos',
                'date_of_damage',
                'assignedto',
                'owner_name',
                'building_name',
                'globalid',
            ]);
        if ($request->assignedto != null) {
            $query->where('assignedto', $assignedto);
        }

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
            'search' => $search,
        ]);

        $building_damage_ststus = [
            'fully_damaged' => 'ضرر كلي',
            'partially_damaged' => 'ضرر جزئي',
            'committee_review' => 'مراجعة لجنة',
            '' => 'غير محدد',
        ];

        return view(
            'DamageAssessment.partials.engineers_cards',
            compact('engineers', 'building_damage_ststus')
        )->render();
    }

    public function showAssessment(string $globalid)
    {
        $building = Building::query()->where('globalid', $globalid)->firstOrFail();
        $HousingUnit = HousingUnit::query()->where('parentglobalid', $globalid)->get();
        $assessments = Assessment::all();
        $buildingTitle = $this->resolveBuildingTitle($building);

        return View::make('DamageAssessment.assessment', compact('globalid', 'building', 'buildingTitle', 'assessments', 'HousingUnit'));
    }

    public function exportAssessmentPdf(string $globalid)
    {
        $building = Building::query()->where('globalid', $globalid)->firstOrFail();
        $housingUnits = HousingUnit::query()->where('parentglobalid', $globalid)->get();

        $buildingTitle = $this->resolveBuildingTitle($building);
        $buildingRows = $this->buildAssessmentRows($building, 'building_table');
        $buildingAttachments = $this->buildAttachmentItems($building);

        $housingSections = $housingUnits->map(function (HousingUnit $housingUnit) {
            return [
                'title' => $this->resolveHousingTitle($housingUnit),
                'rows' => $this->buildAssessmentRows($housingUnit, 'housing_table'),
                'attachments' => $this->buildAttachmentItems($housingUnit),
            ];
        });

        return Pdf::view('pdf.assessment', compact(
            'building',
            'buildingTitle',
            'buildingRows',
            'buildingAttachments',
            'housingSections'
        ))
            ->format('a4')
            ->name('assessment-'.($building->objectid ?? $building->globalid).'.pdf')
            ->withBrowsershot(function (Browsershot $browsershot) {
                $browsershot
                    ->setNodeBinary('C:\\Program Files\\nodejs\\node.exe')
                    ->setNpmBinary('C:\\Program Files\\nodejs\\npm.cmd')
                    ->setNodeModulePath(base_path('node_modules'))
                    ->setChromePath('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe')
                    ->showBackground()
                    ->addChromiumArguments([
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                    ]);
            });
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
																	<a onclick="showModal(`user`,'.$ctr->id.')" href="javascript:;" class="menu-link px-3">تعديل</a>
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

    private function resolveBuildingTitle(Building $building): string
    {
        return $building->building_name
            ?: 'Building #'.($building->objectid ?? $building->globalid);
    }

    private function resolveHousingTitle(HousingUnit $housingUnit): string
    {
        $fullName = trim((string) ($housingUnit->full_name ?? ''));

        if ($fullName !== '') {
            return 'Housing Unit - '.$fullName;
        }

        if (! empty($housingUnit->objectid)) {
            return 'Housing Unit #'.$housingUnit->objectid;
        }

        return 'Housing Unit - '.$housingUnit->globalid;
    }

    private function buildAssessmentRows(Model $model, string $type): Collection
    {
        $record = $model->toArray();
        $fillable = (new $model)->getFillable();
        $filtersMap = Filter::query()->pluck('label', 'name');
        $latestEdits = EditAssessment::query()
            ->where('type', $type)
            ->where('global_id', $model->globalid)
            ->orderByDesc('updated_at')
            ->get()
            ->unique('field_name')
            ->keyBy('field_name');

        return Assessment::query()
            ->whereIn('name', $fillable)
            ->orderBy('id')
            ->get(['name', 'label', 'hint'])
            ->map(function (Assessment $assessment) use ($record, $filtersMap, $latestEdits) {
                $editedValue = $latestEdits->get($assessment->name)?->field_value;
                $rawValue = ($editedValue !== null && $editedValue !== '')
                    ? $editedValue
                    : ($record[$assessment->name] ?? null);

                $mappedValue = $filtersMap[$rawValue] ?? $rawValue;
                $answer = $this->normalizeAssessmentValue($mappedValue);

                return [
                    'label' => $assessment->label,
                    'hint' => $assessment->hint,
                    'answer' => filled((string) $answer) ? $answer : '-',
                ];
            });
    }

    private function buildAttachmentItems(Model $model): Collection
    {
        if (empty($model->objectid)) {
            return collect();
        }

        try {
            $arcgis = app(ArcgisService::class);
            $token = $arcgis->getToken();
            $layerId = $arcgis->getLayerId($model::class);
            $attachments = collect($arcgis->getAttachments($model->objectid, $layerId, $token));

            return $attachments
                ->map(function (array $attachment) use ($arcgis, $layerId, $model, $token) {
                    $attachmentId = $attachment['id'] ?? null;

                    if (! $attachmentId) {
                        return null;
                    }

                    return [
                        'name' => $attachment['name'] ?? ('Attachment '.$attachmentId),
                        'content_type' => $attachment['contentType'] ?? '',
                        'url' => $arcgis->buildUrl($model->objectid, $attachmentId, $layerId, $token),
                    ];
                })
                ->filter()
                ->values();
        } catch (\Throwable $exception) {
            return collect();
        }
    }

    private function normalizeAssessmentValue(mixed $value): mixed
    {
        return match ($value) {
            'yes', 'yes1', 'yes2', 'yes3', 'yes4', 'yes5', 'Yes' => 'نعم',
            'no', 'no1', 'no2', 'no3', 'no4', 'no5', 'No' => 'لا',
            default => $value,
        };
    }

    public function gitPush(Request $request)
    {
        $repoPath = 'D:/myProjects/phc';
        $commitMessage = trim($request->input('message', 'update from system'));

        try {
            if (! is_dir($repoPath.'/.git')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Git repository not found.',
                ], 404);
            }

            // 1) git status
            $statusResult = Process::path($repoPath)
                ->timeout(120)
                ->run('cmd /c git -c safe.directory="'.$repoPath.'" status --short');

            if ($statusResult->failed()) {
                return response()->json([
                    'status' => false,
                    'step' => 'status',
                    'message' => 'Failed to get git status.',
                    'output' => $statusResult->output(),
                    'error' => $statusResult->errorOutput(),
                ], 500);
            }

            $statusOutput = trim($statusResult->output());

            if ($statusOutput === '') {
                return response()->json([
                    'status' => true,
                    'message' => 'No changes to commit.',
                    'steps' => [
                        'status' => 'clean',
                    ],
                ]);
            }

            // 2) git add .
            $addResult = Process::path($repoPath)
                ->timeout(120)
                ->run('cmd /c git -c safe.directory="'.$repoPath.'" add .');

            if ($addResult->failed()) {
                return response()->json([
                    'status' => false,
                    'step' => 'add',
                    'message' => 'Failed to add files.',
                    'output' => $addResult->output(),
                    'error' => $addResult->errorOutput(),
                ], 500);
            }

            // 3) git commit
            $commitCommand = 'cmd /c git -c safe.directory="'.$repoPath.'" commit -m "'.addslashes($commitMessage).'"';

            $commitResult = Process::path($repoPath)
                ->timeout(120)
                ->run($commitCommand);

            $commitOutput = trim($commitResult->output()."\n".$commitResult->errorOutput());

            // إذا لا يوجد شيء للـ commit بعد add
            if (
                str_contains(strtolower($commitOutput), 'nothing to commit') ||
                str_contains(strtolower($commitOutput), 'working tree clean')
            ) {
                return response()->json([
                    'status' => true,
                    'message' => 'No changes to commit after git add.',
                    'steps' => [
                        'status' => $statusOutput,
                        'add' => 'done',
                        'commit' => 'nothing to commit',
                    ],
                    'output' => $commitOutput,
                ]);
            }

            if ($commitResult->failed()) {
                return response()->json([
                    'status' => false,
                    'step' => 'commit',
                    'message' => 'Failed to commit changes.',
                    'output' => $commitResult->output(),
                    'error' => $commitResult->errorOutput(),
                ], 500);
            }

            // 4) git push
            $pushResult = Process::path($repoPath)
                ->timeout(300) // أو forever() إذا الريبو كبير
                ->run('cmd /c git -c safe.directory="'.$repoPath.'" push');

            if ($pushResult->failed()) {
                return response()->json([
                    'status' => false,
                    'step' => 'push',
                    'message' => 'Failed to push changes.',
                    'output' => $pushResult->output(),
                    'error' => $pushResult->errorOutput(),
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Git push completed successfully.',
                'steps' => [
                    'status' => 'done',
                    'add' => 'done',
                    'commit' => 'done',
                    'push' => 'done',
                ],
                'status_output' => $statusOutput,
                'commit_output' => $commitResult->output(),
                'push_output' => $pushResult->output(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
