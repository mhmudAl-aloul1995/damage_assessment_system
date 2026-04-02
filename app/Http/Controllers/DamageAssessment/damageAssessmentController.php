<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Buildings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Project;
use Yajra\Datatables\Datatables;
use Rap2hpoutre\FastExcel\FastExcel;
use Yajra\Datatables\Enginges\EloquentEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Database\Eloquent\Builder;
use Hash;
use Spatie\Permission\Models\Role;
use App\Models\HousingUnit;
use App\Models\Building;
use App\Exports\BuildingExport;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Illuminate\Support\Facades\Http;
use App\Models\Filter;
use App\Models\EditAssessment;
use Illuminate\Support\Facades\Auth;
use App\Services\ArcgisService;

class damageAssessmentController extends Controller
{

    function __construct()
    {
        //$this->middleware('role:Database Officer|Team Leader|Auditing Supervisor|Area Manager|Project Officer');
        /*   $this->middleware('permission:user-create', ['only' => ['create', 'store']]);
           $this->middleware('permission:user-edit', ['only' => ['edit', 'update']]);
           $this->middleware('permission:user-delete', ['only' => ['destroy']]);*/
    }

    public function index($objectid = null)
    {


        $arcgis = app(ArcgisService::class);
        $token = $arcgis->getToken();

        $startDate = '2026-01-01';
        $endDate = Carbon::today()->toDateString();

        $data = [
            'buildings' => Building::selectRaw("
        COALESCE(SUM(field_status = 'Not_Completed'), 0) as not_completed,
        COALESCE(SUM(field_status = 'COMPLETED'), 0) as completed,
        COALESCE(SUM(field_status NOT IN ('COMPLETED', 'Not_Completed')), 0) as pending,
        COALESCE(SUM(building_damage_status = 'fully_damaged'), 0) as fully_damaged,
        COALESCE(SUM(building_damage_status = 'partially_damaged'), 0) as partially_damaged,
        COALESCE(SUM(building_damage_status = 'committee_review'), 0) as committee_review,
        COALESCE(SUM(security_situation = 'Unsafe'), 0) as security_unsafe,
        COALESCE(SUM(uxo_present = 'yes3'), 0) as uxo,
        COALESCE(SUM(bodies_present = 'yes3'), 0) as bodies,
        COALESCE(SUM(building_debris_exist = 'yes'), 0) as debris
    ")
                //   ->whereBetween('editdate', [$startDate, $endDate])
                ->first(),

            'units' => HousingUnit::selectRaw("
        COALESCE(SUM(unit_damage_status = 'fully_damaged2'), 0) as fully_damaged,
        COALESCE(SUM(unit_damage_status = 'partially_damaged2'), 0) as partially_damaged,
        COALESCE(SUM(unit_damage_status = 'committee_review2'), 0) as committee_review,
        COALESCE(SUM(has_fire = 'yes'), 0) as has_fire,
        COALESCE(SUM(unit_stripping = 'yes'), 0) as has_strip,
        COALESCE(SUM(is_the_housing_unit_or_living_habitable = 'yes'), 0) as habitable,
        COALESCE(SUM(security_situation_unit = 'Unsafe'), 0) as security_unsafe,
        COALESCE(SUM(unit_stripping = 'yes'), 0) as unit_stripping,
        COALESCE(SUM(unit_support_needed = 'yes'), 0) as unit_support_needed
    ")
                //   ->whereBetween('creationdate', [$startDate, $endDate])
                ->first()
        ];

        $unitStats = [
            'fully_damaged' => $data['units']->fully_damaged,
            'partially_damaged' => $data['units']->partially_damaged,
            'committee_review' => $data['units']->committee_review,
            'has_fire' => $data['units']->has_fire,
            'has_strip' => $data['units']->has_strip,
            'habitable' => $data['units']->habitable,
            'security_unsafe' => $data['units']->security_unsafe,
            'unit_stripping' => $data['units']->unit_stripping,
            'unit_support_needed' => $data['units']->unit_support_needed,
        ];
        $buildingStats = [
            'not_completed' => $data['buildings']->not_completed,
            'completed' => $data['buildings']->completed,
            'pending' => $data['buildings']->pending,
            'fully_damaged' => $data['buildings']->fully_damaged,
            'partially_damaged' => $data['buildings']->partially_damaged,
            'committee_review' => $data['buildings']->committee_review,
            'security_unsafe' => $data['buildings']->security_unsafe,
            'uxo' => $data['buildings']->uxo,
            'bodies' => $data['buildings']->bodies,
            'debris' => $data['buildings']->debris,
        ];

        return View::make(
            'DamageAssessment.damageAssessment',
            compact(
                'token',
                'unitStats',
                'buildingStats',
            )
        );
    }


    public function search(Request $request)
    {
        $term = $request->search;

        $results = Building::where('building_name', 'LIKE', "%{$term}%")
            ->orWhereHas('housing_unit', function ($q) use ($term) {
                $q->where('q_9_3_1_first_name', 'LIKE', "%{$term}%");
                $q->where('q_9_3_2_second_name__father', 'LIKE', "%{$term}%");
                $q->where('q_9_3_3_third_name__grandfather', 'LIKE', "%{$term}%");
                $q->where('q_9_3_4_last_name', 'LIKE', "%{$term}%");
                $q->select('q_9_3_1_first_name', 'q_9_3_2_second_name__father', 'q_9_3_3_third_name__grandfather', 'q_9_3_4_last_name');
            })
            ->with('housing_unit')
            ->limit(10)
            ->select('building_name')
            ->get();

        return response()->json($results);
    }


    public function showBuildings(Request $request)
    {
        return $this->renderAssessmentTable(
            modelClass: Building::class,
            globalid: $request->globalid,
            type: 'building_table'
        );
    }

    public function showHousings(Request $request)
    {
        return $this->renderAssessmentTable(
            modelClass: HousingUnit::class,
            globalid: $request->globalid,
            type: 'housing_table'
        );
    }

    private function renderAssessmentTable(
        string $modelClass,
        ?string $globalid,
        string $type
    ) {
        $arcgis = app(ArcgisService::class);
        $token = $arcgis->getToken();

        $model = $modelClass::where('globalid', $globalid)->first();
        $record = $model?->toArray() ?? [];

        $fillable = (new $modelClass())->getFillable();

        // ✅ جلب criteria
        $assessments = Assessment::query()
            ->select('name', 'label', 'hint', 'criteria')
            ->whereIn('name', $fillable);

        if (!empty(request()->search['value'])) {
            $search = request()->search['value'];

            $assessments->where(function ($query) use ($search) {
                $query->where('label', 'like', "%{$search}%")
                    ->orWhere('hint', 'like', "%{$search}%");
            });
        }

        // edits
        $edits = collect();
        $allEdits = collect();

        if ($globalid) {
            $edits = EditAssessment::with('user')
                ->where('type', $type)
                ->where('global_id', $globalid)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->groupBy('field_name')
                ->map(fn($group) => $group->first());

            $allEdits = EditAssessment::with('user')
                ->where('type', $type)
                ->where('global_id', $globalid)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->groupBy('field_name');
        }

        $layerId = $arcgis->getLayerId($modelClass);

        $attachments = collect();
        if ($model && $model->objectid && $token) {
            $attachments = collect(
                $arcgis->getAttachments($model->objectid, $layerId, $token)
            );
        }

        $filtersMap = Filter::pluck('label', 'name');

        return DataTables::of($assessments)

            // 🔥🔥🔥 أهم جزء: تلوين الصف
            ->setRowClass(function ($row) use ($record, $allEdits) {

                $original = $record[$row->name] ?? null;
                $lastEdit = $allEdits->get($row->name)?->first();
                $edited = $lastEdit?->field_value;

                $value = ($edited !== null && $edited !== '') ? $edited : $original;
                $value = trim(strip_tags((string) $value));

                $fields = [
                    'dm1',
                    'dm2',
                    'dm3',
                    'dm4',
                    'dm5',
                    'dm6',
                    'dm7',
                    'dm8',
                    'dm10',
                    'dm11',
                    'dm12'
                ];

                // فقط الحقول المطلوبة
                if (!in_array($row->name, $fields, true)) {
                    return '';
                }

                $sizeOfUnit = (float) ($record['damaged_area_m2'] ?? 0);
                $floorNumber = (float) ($record['floor_number'] ?? 0);
                $criteria = (float) ($row->criteria ?? 0);


                $newCriteria = ($sizeOfUnit * $criteria) / 100;


                if (
                    in_array($row->name, ['dm7', 'dm8', 'dm12'], true) &&
                    $floorNumber > 0 &&
                    is_numeric($value) &&
                    (float) $value > 0
                ) {
                    return 'table-danger';
                }


                if (
                    is_numeric($value) &&
                    $newCriteria > 0 &&
                    (float) $value > $newCriteria
                ) {
                    return 'table-danger';
                }

                return '';
            })

            ->addColumn('question', function ($row) {
                return $row->label . '<br>' . $row->hint;
            })

            ->addColumn('answer', function ($row) use ($record, $allEdits, $model, $attachments, $token, $arcgis, $layerId, $filtersMap) {

                if ($row->name === 'attachments') {
                    if (!$model || !$model->objectid || !$token || $attachments->isEmpty()) {
                        return '<span class="text-muted">لا يوجد مرفقات</span>';
                    }

                    $html = '<div class="d-flex flex-wrap gap-2">';
                    foreach ($attachments as $a) {
                        $attachmentId = $a['id'] ?? null;
                        if (!$attachmentId)
                            continue;

                        $url = $arcgis->buildUrl(
                            $model->objectid,
                            $attachmentId,
                            $layerId,
                            $token
                        );

                        $html .= '
                    <a href="' . e($url) . '" target="_blank">
                        <img src="' . e($url) . '"
                             style="width:100px;height:100px;object-fit:cover"
                             class="rounded border">
                    </a>';
                    }

                    return $html . '</div>';
                }

                $lastEdit = $allEdits->get($row->name)?->first();

                $originalRaw = $record[$row->name] ?? null;
                $editedRaw = $lastEdit?->field_value;

                $original = $filtersMap[$originalRaw] ?? $originalRaw;
                $edited = $filtersMap[$editedRaw] ?? $editedRaw;

                if ((is_null($original) || $original === '') && !$lastEdit) {
                    return '<span class="text-muted">-</span>';
                }

                return e($edited ?? $original ?? '-');
            })

            ->addColumn('editAnswer', function ($row) use ($record, $edits, $globalid, $type) {

                if ($row->name === 'attachments')
                    return '';

                $lastEdit = $edits->get($row->name);
                $edited = $lastEdit?->field_value;
                $original = $record[$row->name] ?? '';

                $value = ($edited !== null && $edited !== '') ? $edited : $original;

                return '
                <input type="text"
                    class="form-control form-control-sm inline-edit-input"
                    value="' . e($value) . '"
                    data-field="' . e($row->name) . '"
                    data-globalid="' . e($globalid) . '"
                    data-type="' . e($type) . '">
            ';
            })

            ->rawColumns(['question', 'answer', 'editAnswer'])
            ->make(true);
    }
    private function updateValue($value)
    {
        return match ($value) {
            'yes' => 'نعم',
            'no' => 'لا',
            'yes1' => 'نعم',
            'no1' => 'لا',
            'yes2' => 'نعم',
            'no2' => 'لا',
            'yes3' => 'نعم',
            'no3' => 'لا',
            'yes4' => 'نعم',
            'no4' => 'لا',
            'yes5' => 'نعم',
            'no5' => 'لا',
            'Yes' => 'نعم',
            'No' => 'لا',
            default => $value,
        };
    }
}
