<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Surveys\Buildings;

use App\Exports\TableExport;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Building;
use App\Models\Filter;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Yajra\Datatables\Datatables;

class BuildingController extends Controller
{
    public function index(): ViewContract
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

        return View::make('damage-assessment::surveys.buildings.buildings', [
            'buildingFilterSections' => $this->buildingFilterSections($filters->groupBy('list_name')),
            'buildingSummary' => $this->buildingSummary(),
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

    public function show(Request $request): JsonResponse
    {
        $isHomepage = $request->input('hompage_building') == 1;
        $select = $isHomepage
            ? ['assignedto', 'globalid', 'objectid', 'building_name', 'owner_name', 'zone_code', 'neighborhood']
            : ['assignedto', 'globalid', 'objectid', 'building_name', 'owner_name', 'owner_id', 'zone_code', 'units_count', 'editdate', 'field_status', 'building_damage_status', 'units_nos', 'damaged_units_nos', 'floor_nos', 'building_debris_exist', 'uxo_present', 'bodies_present', 'neighborhood', 'municipalitie'];

        $query = Building::query()->select($select)->where('assignedto', '!=', '');

        $filters = $request->input('filters', []);

        if (! is_array($filters)) {
            $filters = [];
        }

        $this->applyBuildingFilters($query, $filters);

        return Datatables::of($query)
            ->editColumn('id', function ($ctr) {
                return '<div class="form-check form-check-sm form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" value="'.$ctr->id.'" />
                    </div>';
            })
            ->editColumn('field_status', function ($ctr) {
                return $this->statusBadge($ctr->field_status, [
                    'completed' => 'success',
                    'not_completed' => 'warning',
                    'not completed' => 'warning',
                ]);
            })
            ->editColumn('building_damage_status', function ($ctr) {
                return $this->statusBadge($ctr->building_damage_status, [
                    'fully_damaged' => 'danger',
                    'partially_damaged' => 'warning',
                    'committee_review' => 'primary',
                ], [
                    'fully_damaged' => 'Totally Damaged',
                    'partially_damaged' => 'Partially Damaged',
                    'committee_review' => 'Committee Review',
                ]);
            })
            ->addColumn('risk_summary', function ($ctr) {
                $risks = collect([
                    $ctr->building_debris_exist === 'yes' ? __('ui.buildings_page.debris') : null,
                    in_array($ctr->uxo_present, ['yes', 'yes3'], true) ? __('ui.buildings_page.uxo') : null,
                    in_array($ctr->bodies_present, ['yes', 'yes3'], true) ? __('ui.buildings_page.bodies') : null,
                ])->filter();

                if ($risks->isEmpty()) {
                    return '<span class="text-muted">-</span>';
                }

                return $risks
                    ->map(fn (string $risk): string => '<span class="badge badge-light-danger me-1 mb-1">'.e($risk).'</span>')
                    ->implode('');
            })
            ->editColumn('action', function ($ctr) {
                $housingUrl = url("/damage-assessment/showHousing/{$ctr->globalid}");
                $assessmentUrl = url("/damage-assessment/assessment/{$ctr->globalid}");

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
            ->rawColumns(['action', 'id', 'field_status', 'building_damage_status', 'risk_summary'])
            ->make(true);
    }

    /**
     * @return array<int, array{title: string, filters: array<int, array{field: string, label: string, options: mixed}>}>
     */
    private function buildingFilterSections($groupedFilters): array
    {
        $sections = [
            [
                'title' => __('ui.buildings_page.filter_section_damage'),
                'filters' => [
                    ['field' => 'building_damage_status', 'label' => __('ui.buildings_page.damage_status')],
                    ['field' => 'building_status_visit', 'label' => __('ui.buildings_page.visit_status')],
                    ['field' => 'building_debris_exist', 'label' => __('ui.buildings_page.debris_exists')],
                    ['field' => 'building_debris_qty', 'label' => __('ui.buildings_page.debris_quantity')],
                    ['field' => 'building_debris_blocking', 'label' => __('ui.buildings_page.debris_blocking')],
                    ['field' => 'uxo_present', 'label' => __('ui.buildings_page.uxo_present')],
                    ['field' => 'bodies_present', 'label' => __('ui.buildings_page.bodies_present')],
                ],
            ],
            [
                'title' => __('ui.buildings_page.filter_section_building'),
                'filters' => [
                    ['field' => 'building_type', 'label' => __('ui.buildings_page.building_type')],
                    ['field' => 'building_use', 'label' => __('ui.buildings_page.building_use')],
                    ['field' => 'building_material', 'label' => __('ui.buildings_page.building_material')],
                    ['field' => 'building_age', 'label' => __('ui.buildings_page.building_age')],
                    ['field' => 'building_roof_type', 'label' => __('ui.buildings_page.roof_type')],
                ],
            ],
            [
                'title' => __('ui.buildings_page.filter_section_ownership'),
                'filters' => [
                    ['field' => 'building_ownership', 'label' => __('ui.buildings_page.building_ownership')],
                    ['field' => 'owner_status', 'label' => __('ui.buildings_page.owner_status')],
                    ['field' => 'building_responsible', 'label' => __('ui.buildings_page.building_responsible')],
                    ['field' => 'building_authorization', 'label' => __('ui.buildings_page.building_authorization')],
                ],
            ],
            [
                'title' => __('ui.buildings_page.filter_section_services'),
                'filters' => [
                    ['field' => 'has_elevator', 'label' => __('ui.buildings_page.has_elevator')],
                    ['field' => 'elevator_status', 'label' => __('ui.buildings_page.elevator_status')],
                    ['field' => 'has_solar', 'label' => __('ui.buildings_page.has_solar')],
                    ['field' => 'solar_damage_status', 'label' => __('ui.buildings_page.solar_damage_status')],
                    ['field' => 'has_well', 'label' => __('ui.buildings_page.has_well')],
                    ['field' => 'well_damage_status', 'label' => __('ui.buildings_page.well_damage_status')],
                    ['field' => 'has_fence', 'label' => __('ui.buildings_page.has_fence')],
                    ['field' => 'fence_damage_status', 'label' => __('ui.buildings_page.fence_damage_status')],
                    ['field' => 'has_parking', 'label' => __('ui.buildings_page.has_parking')],
                    ['field' => 'parking_status', 'label' => __('ui.buildings_page.parking_status')],
                ],
            ],
        ];

        return collect($sections)
            ->map(function (array $section) use ($groupedFilters): array {
                $section['filters'] = collect($section['filters'])
                    ->filter(fn (array $filter): bool => Schema::hasColumn('buildings', $filter['field']))
                    ->map(function (array $filter) use ($groupedFilters): array {
                        $filter['options'] = $groupedFilters[$filter['field']] ?? collect();

                        return $filter;
                    })
                    ->filter(fn (array $filter): bool => $filter['options']->isNotEmpty())
                    ->values()
                    ->all();

                return $section;
            })
            ->filter(fn (array $section): bool => ! empty($section['filters']))
            ->values()
            ->all();
    }

    private function applyBuildingFilters(Builder $query, array $filters): void
    {
        $selectFilters = [
            'assignedto',
            'municipalitie',
            'neighborhood',
            'field_status',
            'building_damage_status',
            'building_status_visit',
            'building_debris_exist',
            'building_debris_qty',
            'building_debris_blocking',
            'uxo_present',
            'bodies_present',
            'building_type',
            'building_use',
            'building_material',
            'building_age',
            'building_roof_type',
            'building_ownership',
            'owner_status',
            'building_responsible',
            'building_authorization',
            'has_elevator',
            'elevator_status',
            'has_solar',
            'solar_damage_status',
            'has_well',
            'well_damage_status',
            'has_fence',
            'fence_damage_status',
            'has_parking',
            'parking_status',
        ];

        foreach ($selectFilters as $field) {
            if (! Schema::hasColumn('buildings', $field)) {
                continue;
            }

            $value = $filters[$field] ?? null;

            if (is_array($value)) {
                $value = array_values(array_filter($value, fn ($item): bool => $item !== null && $item !== ''));
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            is_array($value)
                ? $query->whereIn($field, $value)
                : $query->where($field, $value);
        }

        foreach (['building_name', 'owner_name', 'owner_id', 'objectid'] as $field) {
            if (! Schema::hasColumn('buildings', $field)) {
                continue;
            }

            $value = $filters[$field] ?? null;

            if ($value !== null && $value !== '') {
                $query->where($field, 'like', '%'.$value.'%');
            }
        }

        foreach (['floor_nos', 'units_nos', 'damaged_units_nos'] as $field) {
            if (! Schema::hasColumn('buildings', $field)) {
                continue;
            }

            $from = $filters[$field.'_from'] ?? null;
            $to = $filters[$field.'_to'] ?? null;

            if ($from !== null && $from !== '') {
                $query->where($field, '>=', $from);
            }

            if ($to !== null && $to !== '') {
                $query->where($field, '<=', $to);
            }
        }
    }

    /**
     * @param  array<string, string>  $colors
     */
    private function statusBadge(?string $value, array $colors, array $labels = []): string
    {
        if ($value === null || trim($value) === '') {
            return '<span class="text-muted">-</span>';
        }

        $normalized = strtolower(trim($value));
        $color = $colors[$normalized] ?? 'secondary';

        $label = $labels[$normalized] ?? str($value)->replace('_', ' ')->title();

        return '<span class="badge badge-light-'.$color.'">'.e($label).'</span>';
    }

    /**
     * @return array{total: int, fully_damaged: int, partially_damaged: int, committee_review: int}
     */
    private function buildingSummary(): array
    {
        $baseQuery = Building::query()->where('assignedto', '!=', '');

        return [
            'total' => (clone $baseQuery)->count(),
            'fully_damaged' => (clone $baseQuery)->where('building_damage_status', 'fully_damaged')->count(),
            'partially_damaged' => (clone $baseQuery)->where('building_damage_status', 'partially_damaged')->count(),
            'committee_review' => (clone $baseQuery)->where('building_damage_status', 'committee_review')->count(),
        ];
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
        $format = $request->input('format');
        $buildingColumns = $request->input('building_columns');
        $filters = $request->input('filters', []);

        if (! is_array($filters)) {
            $filters = [];
        }

        $buildingColumns = array_filter($buildingColumns ?? [], fn ($value) => ! is_null($value) && $value !== '');

        if ($buildingColumns === []) {
            $buildingColumns = ['objectid', 'building_name', 'owner_name', 'building_damage_status', 'municipalitie', 'neighborhood', 'units_nos', 'damaged_units_nos'];
        }

        $buildingQuery = Building::query()->select($buildingColumns);
        $this->applyBuildingFilters($buildingQuery, $filters);
        $building = $buildingQuery->get();

        $assessmentHints = Assessment::whereIn('name', $buildingColumns)
            ->get(['name', 'hint', 'label'])
            ->keyBy('name');

        if ($format == 'pdf') {
            return Pdf::view('damage-assessment::pdf.building', compact('building', 'buildingColumns', 'assessmentHints'))
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
