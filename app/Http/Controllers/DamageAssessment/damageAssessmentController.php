<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\Filter;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Services\ArcgisService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Yajra\Datatables\Datatables;

class damageAssessmentController extends Controller
{
    public function __construct()
    {
    }

    public function index($objectid = null)
    {
        $arcgis = app(ArcgisService::class);
        $token = $arcgis->getToken();

        $startDate = '2026-01-01';
        $endDate = Carbon::today()->toDateString();

        $data = [
            'buildings' => Building::whereBetween('creationdate', [$startDate, $endDate])
                ->selectRaw("COALESCE(SUM(field_status = 'Not_Completed'), 0) as not_completed,COALESCE(SUM(field_status = 'COMPLETED'), 0) as completed,COALESCE(SUM(field_status NOT IN ('COMPLETED', 'Not_Completed')), 0) as pending,COALESCE(SUM(building_damage_status = 'fully_damaged'), 0) as fully_damaged,COALESCE(SUM(building_damage_status = 'partially_damaged'), 0) as partially_damaged,COALESCE(SUM(building_damage_status = 'committee_review'), 0) as committee_review,COALESCE(SUM(security_situation = 'Unsafe'), 0) as security_unsafe,COALESCE(SUM(uxo_present = 'yes3'), 0) as uxo,COALESCE(SUM(bodies_present = 'yes3'), 0) as bodies,COALESCE(SUM(building_debris_exist = 'yes'), 0) as debris")
                ->first(),
            'units' => HousingUnit::whereBetween('creationdate', [$startDate, $endDate])->selectRaw("COALESCE(SUM(unit_damage_status = 'fully_damaged2'), 0) as fully_damaged,COALESCE(SUM(unit_damage_status = 'partially_damaged2'), 0) as partially_damaged,COALESCE(SUM(unit_damage_status = 'committee_review2'), 0) as committee_review,COALESCE(SUM(has_fire = 'yes'), 0) as has_fire,COALESCE(SUM(unit_stripping = 'yes'), 0) as has_strip,COALESCE(SUM(is_the_housing_unit_or_living_habitable = 'yes'), 0) as habitable,COALESCE(SUM(security_situation_unit = 'Unsafe'), 0) as security_unsafe,COALESCE(SUM(unit_stripping = 'yes'), 0) as unit_stripping,COALESCE(SUM(unit_support_needed = 'yes'), 0) as unit_support_needed")
                ->first(),
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


        $publicBuildingStats = [
            'total_surveys' => PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),

            'damaged_buildings' => PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '')
                ->count(),

            'total_units' => (int) PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->withCount('units')
                ->get()
                ->sum('units_count'),

            'municipalities' => PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('municipalitie')
                ->where('municipalitie', '!=', '')
                ->distinct()
                ->count('municipalitie'),

            'neighborhoods' => PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '')
                ->distinct()
                ->count('neighborhood'),

            'assigned_staff' => PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('assigned_to')
                ->where('assigned_to', '!=', '')
                ->distinct()
                ->count('assigned_to'),

            'occupied_buildings' => PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('is_building_occupied', 'yes')
                ->count(),

            'bodies_present' => PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('is_bodies', 'yes')
                ->count(),

            'uxo_present' => PublicBuildingSurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('is_uxo', 'yes')
                ->count(),
        ];

        $roadFacilityStats = [
            'total_surveys' => RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),

            'damaged_roads' => RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('road_damage_level')
                ->where('road_damage_level', '!=', '')
                ->count(),

            'total_items' => (int) RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->withCount('items')
                ->get()
                ->sum('items_count'),

            'municipalities' => RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('municipalitie')
                ->where('municipalitie', '!=', '')
                ->distinct()
                ->count('municipalitie'),

            'neighborhoods' => RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '')
                ->distinct()
                ->count('neighborhood'),

            'potholes_locations' => RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('potholes_exist', 'yes')
                ->count(),

            'obstacle_locations' => RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('obstacle_exist', 'yes')
                ->count(),

            'buried_bodies_locations' => RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('buried_bodies', 'yes')
                ->count(),

            'uxo_locations' => RoadFacilitySurvey::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('uxo_present', 'yes')
                ->count(),
        ];
        $publicBuildingLayerUrl = $this->normalizeFeatureLayerUrl((string) config('services.arcgis.public_building_survey_layer_url'));
        $roadFacilityLayerUrl = $this->normalizeFeatureLayerUrl((string) config('services.arcgis.road_facility_survey_layer_url'));

        return View::make(
            'DamageAssessment.damageAssessment',
            compact(
                'token',
                'unitStats',
                'buildingStats',
                'publicBuildingStats',
                'roadFacilityStats',
                'publicBuildingLayerUrl',
                'roadFacilityLayerUrl',
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

    public function publicBuildingsMap(Request $request)
    {
        $query = PublicBuildingSurvey::query()->select([
            'id',
            'objectid',
            'building_name',
            'municipalitie',
            'neighborhood',
            'building_damage_status',
        ]);

        return DataTables::of($query)
            ->editColumn('building_damage_status', function ($row) {
                return match ($row->building_damage_status) {
                    'fully_damaged' => '<span class="badge badge-light-danger fw-bold">Fully</span>',
                    'partially_damaged' => '<span class="badge badge-light-success fw-bold">Partially</span>',
                    'committee_review' => '<span class="badge badge-light-warning fw-bold">Committee</span>',
                    default => '<span class="badge badge-light">-</span>',
                };
            })
            ->rawColumns(['building_damage_status'])
            ->make(true);
    }

    public function roadFacilitiesMap(Request $request)
    {
        $query = RoadFacilitySurvey::query()->select([
            'id',
            'objectid',
            'str_name',
            'municipalitie',
            'neighborhood',
            'road_damage_level',
        ]);

        return DataTables::of($query)
            ->editColumn('road_damage_level', function ($row) {
                return match ($row->road_damage_level) {
                    'destroyed', 'severe' => '<span class="badge badge-light-danger fw-bold">Severe</span>',
                    'moderate' => '<span class="badge badge-light-warning fw-bold">Moderate</span>',
                    'minor', 'No_Damage' => '<span class="badge badge-light-success fw-bold">Minor</span>',
                    default => '<span class="badge badge-light">-</span>',
                };
            })
            ->rawColumns(['road_damage_level'])
            ->make(true);
    }

    private function normalizeFeatureLayerUrl(string $url): string
    {
        $normalized = rtrim($url, '/');

        if ($normalized === '') {
            return $normalized;
        }

        if (preg_match('/\/\d+$/', $normalized) === 1) {
            return $normalized;
        }

        return $normalized . '/0';
    }

    private function renderAssessmentTable(string $modelClass, ?string $globalid, string $type)
    {
        $arcgis = app(ArcgisService::class);
        $token = $arcgis->getToken();

        $model = $modelClass::where('globalid', $globalid)->first();
        $record = $model?->toArray() ?? [];

        $fillable = (new $modelClass)->getFillable();
        $assessments = Assessment::query()->whereIn('name', $fillable);

        $allEdits = collect();

        if (!empty(request()->search['value'])) {
            $search = request()->search['value'];

            $assessments->where(function ($query) use ($search) {
                $query->where('label', 'like', "%{$search}%")
                    ->orWhere('hint', 'like', "%{$search}%");
            });
        }

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
            ->setRowClass(function ($row) use ($record, $allEdits) {
                $original = $record[$row->name] ?? null;
                $lastEdit = $allEdits->get($row->name)?->first();
                $edited = $lastEdit?->field_value;

                $value = ($edited !== null && $edited !== '') ? $edited : $original;
                $value = trim(strip_tags((string) $value));

                $sizeOfUnit = (float) ($record['damaged_area_m2'] ?? 0);
                $floorNumber = (float) ($record['floor_number'] ?? 0);
                $criteria = (float) ($row->criteria ?? 0);
                $newCriteria = ($sizeOfUnit * $criteria) / 100;


                if ($original == null || $original == '') {

                    return '';
                }

                if (in_array($row->name, ['dm6', 'dm7', 'dm12'], true) && $floorNumber > 0 && is_numeric($value) && (float) $value > 0) {
                    return 'table-danger';
                }


                if (($row->type == 2) && is_numeric($value) && $value > $criteria) {
                    return 'table-danger';
                }
                if (
                    (($row->type > 2 && $row->type < 15) || in_array($row->type, [19, 20, 21, 23]))


                ) {


                    $columnNames = Assessment::where('type', $row->type)
                        ->pluck('name')
                        ->toArray();

                    $total = collect($columnNames)->sum(function ($column) use ($record, $allEdits) {

                        $lastEdit = $allEdits->get($column)?->first();

                        if ($lastEdit && $lastEdit->field_value !== null && $lastEdit->field_value !== '') {
                            return (float) $lastEdit->field_value;
                        }

                        return (float) ($record[$column] ?? 0);
                    });



                    if ($total > $newCriteria) {

                        return 'table-danger';
                    }
                }


                if (($row->type == 1) && is_numeric($value) && $newCriteria > 0 && $value > $newCriteria) {
                    if (in_array($row->name, ['mt8', 'mt9'], true) && is_numeric($value) && (float) $value == 1) {
                        return '';
                    }
                    return 'table-danger';
                }
                /*    if ($row->type > 2 && $row->type < 24 && is_numeric($value)) {

                   $columnNames = Assessment::where('type', $row->type)
                           ->pluck('name')
                           ->toArray();

                       $total = collect($columnNames)->sum(function ($column) use ($record, $allEdits) {

                           $lastEdit = $allEdits->get($column)?->first();

                           if ($lastEdit && $lastEdit->field_value !== null && $lastEdit->field_value !== '') {
                               return (float) $lastEdit->field_value;
                           }

                           return (float) ($record[$column] ?? 0);
                       });

                       if ($total > $criteria) {
                           return 'table-danger';
                       }
                   } */

                return '';
            })
            ->addColumn('question', function ($row) {
                return $row->label . '<br>' . $row->hint;
            })
            ->addColumn('answer', function ($row) use ($record, $allEdits, $model, $attachments, $token, $arcgis, $layerId, $type, $globalid, $filtersMap) {
                if ($row->name === 'attachments') {
                    if (!$model || !$model->objectid || !$token || $attachments->isEmpty()) {
                        return '<span class="text-muted">' . e(__('ui.damage_common.no_attachments')) . '</span>';
                    }

                    $html = '<div class="d-flex flex-wrap gap-2">';

                    foreach ($attachments as $a) {
                        $attachmentId = $a['id'] ?? null;

                        if (!$attachmentId) {
                            continue;
                        }

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
                    </a>
                ';
                    }

                    return $html . '</div>';
                }

                $fieldEdits = $allEdits->get($row->name, collect());
                $lastEdit = $fieldEdits->first();

                $originalRawValue = $record[$row->name] ?? null;
                $editedRawValue = $lastEdit?->field_value;

                $originalValue = $filtersMap[$originalRawValue] ?? $originalRawValue;
                $editedValue = $filtersMap[$editedRawValue] ?? $editedRawValue;
                $originalValue = $this->updateValue($originalValue);
                $editedValue = $this->updateValue($editedValue);
                $editedBy = $lastEdit?->user?->name;
                $editedAt = $lastEdit?->updated_at?->format('Y-m-d h:i A');

                $canViewHistory = auth()->user()->hasAnyRole([
                    'Database Officer',
                    'Project Officer',
                    'QC/QA Engineer',
                    'Legal Auditor',
                    'Auditing Supervisor',
                ]);

                if ((is_null($originalValue) || $originalValue === '') && $fieldEdits->isEmpty()) {
                    return '<span class="text-muted">-</span>';
                }

                if ($fieldEdits->isEmpty() || !$canViewHistory) {
                    return e($originalValue ?? '-');
                }

                $historyHtml = '';
                $collapseId = 'history_' . md5($type . '_' . $globalid . '_' . $row->name);

                foreach ($fieldEdits as $edit) {
                    $historyValue = $filtersMap[$edit->field_value] ?? $edit->field_value;

                    $historyHtml .= '
                <div class="border rounded p-2 mb-2 bg-light-info">
                    <div>
                        <small class="text-muted">' . e(__('ui.damage_common.value')) . ':</small>
                        <span class="fw-semibold">' . e($historyValue ?? '-') . '</span>
                    </div>
                    <div>
                        <small class="text-muted">' . e(__('ui.damage_common.by')) . ':</small>
                        ' . e($edit->user?->name ?? '-') . '
                    </div>
                    <div>
                        <small class="text-muted">' . e(__('ui.damage_common.time')) . ':</small>
                        ' . e(optional($edit->updated_at)->format('Y-m-d h:i A') ?? '-') . '
                    </div>
                </div>
            ';
                }

                $historyHtml = '
            <div class="mt-3">
                <button class="btn btn-sm btn-light-primary" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#' . $collapseId . '"
                        aria-expanded="false">
                    ' . e(__('ui.damage_common.view_edit_history', ['count' => $fieldEdits->count()])) . '
                </button>

                <div class="collapse mt-2" id="' . $collapseId . '">
                    ' . $historyHtml . '
                </div>
            </div>
        ';

                return '
            <div class="border rounded p-3 bg-light-warning">
                <div class="mb-2">
                    <small class="text-muted d-block">' . e(__('ui.damage_common.original')) . '</small>
                    <span class="text-gray-700">' . e($originalValue ?? '-') . '</span>
                </div>

                <div class="mb-2">
                    <small class="text-warning d-block fw-bold">' . e(__('ui.damage_common.latest_edit')) . '</small>
                    <span class="text-gray-900 fw-bold">' . e($editedValue ?? '-') . '</span>
                </div>

                <div class="mb-1">
                    <small class="text-info d-block fw-bold">' . e(__('ui.damage_common.editor_name')) . '</small>
                    <span class="text-gray-800">' . e($editedBy ?? '-') . '</span>
                </div>

                <div>
                    <small class="text-primary d-block fw-bold">' . e(__('ui.damage_common.edit_time')) . '</small>
                    <span class="text-gray-600">' . e($editedAt ?? '-') . '</span>
                </div>

                ' . $historyHtml . '
            </div>
        ';
            })
            ->addColumn('editAnswer', function ($row) use ($record, $edits, $globalid, $type) {
                if ($row->name === 'attachments') {
                    return;
                }
                $lastEdit = $edits->get($row->name);
                $editedValue = $lastEdit?->field_value;
                $originalValue = $record[$row->name] ?? '';
                $value = ($editedValue !== null && $editedValue !== '') ? $editedValue : $originalValue;

                $filters = Filter::where('list_name', $row->name)->get();

                if ($filters->count() > 0) {
                    $selectedValues = array_filter(array_map('trim', explode(',', (string) $value)));

                    $html = '<select
                    class="form-select form-select-sm form-select-solid inline-edit-select"
                    data-field="' . e($row->name) . '"
                    data-globalid="' . e($globalid) . '"
                    data-type="' . e($type) . '"
                    data-control="select2"
                    data-close-on-select="true"
                    data-placeholder="' . e(__('ui.damage_common.select_option')) . '">';

                    $html .= '<option value=""></option>';

                    foreach ($filters as $option) {
                        $selected = in_array($option->name, $selectedValues) ? 'selected' : '';
                        $html .= '<option value="' . e($option->name) . '" ' . $selected . '>' . e($option->label) . '</option>';
                    }

                    $html .= '</select>';

                    return $html;
                }

                return '
                <div class="d-flex gap-2 align-items-center justify-content-center">
                    <input
                        type="text"
                        class="form-control form-control-sm form-control-solid inline-edit-input"
                        value="' . e($value) . '"
                        data-field="' . e($row->name) . '"
                        data-globalid="' . e($globalid) . '"
                        data-type="' . e($type) . '"
                    >
                    <button type="button"
                        class="btn btn-sm btn-light-primary inline-save-btn"
                        data-field="' . e($row->name) . '"
                        data-globalid="' . e($globalid) . '"
                        data-type="' . e($type) . '">
                        ' . e(__('ui.buttons.save')) . '
                    </button>
                </div>
            ';
            })
            ->rawColumns(['answer', 'question', 'editAnswer'])
            ->make(true);
    }

    private function updateValue($value)
    {
        return match ($value) {
            'yes', 'yes1', 'yes2', 'yes3', 'yes4', 'yes5', 'Yes' => __('ui.options.yes'),
            'no', 'no1', 'no2', 'no3', 'no4', 'no5', 'No' => __('ui.options.no'),
            default => $value,
        };
    }

    public function globalSearch(Request $request)
    {
        $search = trim((string) $request->string('search'));

        if (mb_strlen($search) < 2) {
            return response()->json([
                'results' => [],
            ]);
        }

        $buildingResults = Building::query()
            ->select(['globalid', 'objectid', 'building_name', 'owner_name', 'neighborhood'])
            ->where(function ($query) use ($search) {
                $query->where('building_name', 'like', "%{$search}%")
                    ->orWhere('objectid', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%");
            })
            ->limit(5)
            ->get()
            ->map(function (Building $building): array {
                $subtitleParts = array_values(array_filter([
                    __('ui.search.object_id') . ': ' . $building->objectid,
                    $building->owner_name,
                    $building->neighborhood,
                ]));

                return [
                    'group' => __('ui.search.buildings'),
                    'title' => $building->building_name ?: __('ui.search.unnamed_building'),
                    'subtitle' => implode(' • ', $subtitleParts),
                    'url' => route('assessment.show', $building->globalid),
                    'icon' => 'ki-home',
                ];
            });

        $publicBuildingResults = PublicBuildingSurvey::query()
            ->select(['objectid', 'building_name', 'municipalitie', 'neighborhood'])
            ->where(function ($query) use ($search) {
                $query->where('building_name', 'like', "%{$search}%")
                    ->orWhere('objectid', 'like', "%{$search}%")
                    ->orWhere('municipalitie', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%");
            })
            ->limit(5)
            ->get()
            ->map(function (PublicBuildingSurvey $survey): array {
                $subtitleParts = array_values(array_filter([
                    __('ui.search.object_id') . ': ' . $survey->objectid,
                    $survey->municipalitie,
                    $survey->neighborhood,
                ]));

                return [
                    'group' => __('ui.search.public_buildings'),
                    'title' => $survey->building_name ?: __('ui.search.unnamed_public_building'),
                    'subtitle' => implode(' • ', $subtitleParts),
                    'url' => route('public-buildings.show', $survey),
                    'icon' => 'ki-office-bag',
                ];
            });

        $roadFacilityResults = RoadFacilitySurvey::query()
            ->select(['objectid', 'str_name', 'municipalitie', 'neighborhood'])
            ->where(function ($query) use ($search) {
                $query->where('str_name', 'like', "%{$search}%")
                    ->orWhere('objectid', 'like', "%{$search}%")
                    ->orWhere('municipalitie', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%");
            })
            ->limit(5)
            ->get()
            ->map(function (RoadFacilitySurvey $survey): array {
                $subtitleParts = array_values(array_filter([
                    __('ui.search.object_id') . ': ' . $survey->objectid,
                    $survey->municipalitie,
                    $survey->neighborhood,
                ]));

                return [
                    'group' => __('ui.search.road_facilities'),
                    'title' => $survey->str_name ?: __('ui.search.unnamed_road_facility'),
                    'subtitle' => implode(' • ', $subtitleParts),
                    'url' => route('road-facilities.show', $survey),
                    'icon' => 'ki-map',
                ];
            });

        return response()->json([
            'results' => $buildingResults
                ->concat($publicBuildingResults)
                ->concat($roadFacilityResults)
                ->values(),
        ]);
    }

    public function housingUnitsMap(Request $request)
    {
        $fullNameExpression = $this->housingUnitFullNameExpression();

        $query = Building::query()
            ->leftJoin('housing_units', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->select([
                'buildings.id',
                'buildings.globalid as building_globalid',
                'buildings.objectid',
                'buildings.building_name as building_name',
                'buildings.neighborhood as neighborhood',
                'housing_units.unit_damage_status',
                DB::raw($fullNameExpression . ' as full_name1'),
            ]);

        return DataTables::of($query)
            ->filterColumn('full_name1', function ($query, $keyword) use ($fullNameExpression) {
                $query->whereRaw($fullNameExpression . ' LIKE ?', ["%{$keyword}%"]);
            })
            ->editColumn('full_name1', function ($row) {
                return $row->full_name1 !== '' ? $row->full_name1 : '-';
            })
            ->editColumn('unit_damage_status', function ($row) {
                return match ($row->unit_damage_status) {
                    'fully_damaged2' => '<span class="badge badge-light-danger fw-bold">Fully</span>',
                    'partially_damaged2' => '<span class="badge badge-light-success fw-bold">Partially</span>',
                    'committee_review2' => '<span class="badge badge-light-warning fw-bold">Commitee</span>',
                    null, '' => '<span class="badge badge-light">No Units</span>',
                    default => '-',
                };
            })
            ->rawColumns(['unit_damage_status'])
            ->filterColumn('building_name', function ($query, $keyword) {
                $query->where('buildings.building_name', 'like', "%{$keyword}%");
            })
            ->filterColumn('neighborhood', function ($query, $keyword) {
                $query->where('buildings.neighborhood', 'like', "%{$keyword}%");
            })
            ->filterColumn('unit_damage_status', function ($query, $keyword) {
                $query->where('housing_units.unit_damage_status', 'like', "%{$keyword}%");
            })
            ->orderColumn('building_name', function ($query, $order) {
                $query->orderBy('buildings.building_name', $order);
            })
            ->orderColumn('neighborhood', function ($query, $order) {
                $query->orderBy('buildings.neighborhood', $order);
            })
            ->orderColumn('unit_damage_status', function ($query, $order) {
                $query->orderBy('housing_units.unit_damage_status', $order);
            })
            ->orderColumn('full_name1', function ($query, $order) use ($fullNameExpression) {
                $query->orderByRaw($fullNameExpression . ' ' . $order);
            })
            ->make(true);
    }

    private function housingUnitFullNameExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "TRIM(COALESCE(housing_units.q_9_3_1_first_name, '') || ' ' || COALESCE(housing_units.q_9_3_4_last_name, ''))";
        }

        return "TRIM(CONCAT_WS(' ', housing_units.q_9_3_1_first_name, housing_units.q_9_3_4_last_name))";
    }
}
