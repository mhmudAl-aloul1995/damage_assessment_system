<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Surveys\HousingUnits;

use App\Exports\HousingUnitBoqExport;
use App\Exports\TableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\HousingUnitExportRequest;
use App\Models\Assessment;
use App\Models\Building;
use App\Models\Filter;
use App\Models\HousingUnit;
use App\Support\BrowsershotConfiguration;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\Datatables\Datatables;

class HousingUnitController extends Controller
{
    private const HOUSING_EXPORT_SOURCE_TABLE = 'v_housing_units_audited';

    public function index(?string $globalid = null): ViewContract
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

        return View::make('damage-assessment::surveys.housing-units.housing', [
            'housingFilterSections' => $this->housingFilterSections($filters->groupBy('list_name')),
            'housingSummary' => $this->housingSummary($globalid),
            'filters' => $filters,
            'filterName' => $filterName,
            'globalid' => $globalid,
            'engineers' => Building::query()->distinct()->orderBy('assignedto')->pluck('assignedto')->filter()->values(),
            'owners' => Building::query()->distinct()->orderBy('owner_name')->pluck('owner_name')->filter()->values(),
            'municip' => Building::query()->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),
            'neighborhoods' => HousingUnit::query()->distinct()->orderBy('neighborhood')->pluck('neighborhood')->filter()->values(),
            'assessments' => Assessment::query()->get(),
            'groupedFilters' => $filters->groupBy('list_name'),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $filters = $request->input('filters', []);

        if (! is_array($filters)) {
            $filters = [];
        }

        $housingUnits = HousingUnit::query()
            ->with('building:globalid,objectid,assignedto')
            ->select([
                'id',
                'objectid',
                'globalid',
                'parentglobalid',
                'housing_unit_type',
                'unit_damage_status',
                'floor_number',
                'housing_unit_number',
                'unit_direction',
                'damaged_area_m2',
                'infra_type2',
                'house_unit_ownership',
                'unit_owner',
                'q_9_3_1_first_name',
                'q_9_3_2_second_name__father',
                'q_9_3_3_third_name__grandfather',
                'q_9_3_4_last_name',
                'id_number1',
                'mobile_number',
                'occupied',
                'the_unit_resident',
                'current_residence',
                'unit_support_needed',
                'is_the_housing_unit_or_living_habitable',
                'has_fire',
                'rubble_removal_is_needed',
                'activation_of_uxo_ha_d_material_clearance',
                'municipalitie',
                'neighborhood',
                'number_of_rooms',
                'age',
                'editdate',
            ]);

        if ($request->filled('parentglobalid')) {
            $housingUnits->where('parentglobalid', $request->string('parentglobalid')->toString());
        }

        $this->applyHousingFilters($housingUnits, $filters);

        return Datatables::of($housingUnits->orderBy('objectid'))
            ->addColumn('assignedto', function (HousingUnit $housingUnit): string {
                return $housingUnit->building?->assignedto ?? '-';
            })
            ->addColumn('building_objectid', function (HousingUnit $housingUnit): string {
                return (string) ($housingUnit->building?->objectid ?? '-');
            })
            ->addColumn('full_name', function (HousingUnit $housingUnit): string {
                $name = collect([
                    $housingUnit->q_9_3_1_first_name,
                    $housingUnit->q_9_3_2_second_name__father,
                    $housingUnit->q_9_3_3_third_name__grandfather,
                    $housingUnit->q_9_3_4_last_name,
                ])->filter()->implode(' ');

                return $name !== '' ? $name : ($housingUnit->unit_owner ?? '-');
            })
            ->editColumn('unit_damage_status', function (HousingUnit $housingUnit): string {
                return $this->statusBadge($housingUnit->unit_damage_status, [
                    'fully_damaged2' => 'danger',
                    'partially_damaged2' => 'warning',
                    'committee_review2' => 'primary',
                    'no_damaged' => 'success',
                ], [
                    'fully_damaged2' => 'Totally Damaged',
                    'partially_damaged2' => 'Partially Damaged',
                    'committee_review2' => 'Committee Review',
                    'no_damaged' => 'No Damage',
                ]);
            })
            ->addColumn('support_summary', function (HousingUnit $housingUnit): string {
                $items = collect([
                    $housingUnit->unit_support_needed === 'yes' ? __('ui.housing_page.support_needed') : null,
                    $housingUnit->rubble_removal_is_needed === 'yes' ? __('ui.housing_page.rubble_removal') : null,
                    $housingUnit->activation_of_uxo_ha_d_material_clearance === 'yes' ? __('ui.housing_page.uxo_clearance') : null,
                    $housingUnit->has_fire === 'yes' ? __('ui.housing_page.fire') : null,
                ])->filter();

                if ($items->isEmpty()) {
                    return '<span class="text-muted">-</span>';
                }

                return $items
                    ->map(fn (string $item): string => '<span class="badge badge-light-warning me-1 mb-1">'.e($item).'</span>')
                    ->implode('');
            })
            ->editColumn('id', function ($ctr) {
                return '<div class="form-check form-check-sm form-check-custom form-check-solid">
																<input class="form-check-input" type="checkbox" value="'.$ctr->id.'" />
															</div>';
            })
            ->editColumn('action', function ($ctr) {
                $excelUrl = route('housing.export', ['format' => 'xlsx', 'globalid' => $ctr->globalid]);
                $pdfUrl = route('housing.export', ['format' => 'pdf', 'globalid' => $ctr->globalid]);

                return '<a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">'.e(__('ui.damage_common.actions')).'
															<i class="ki-duotone ki-down fs-5 ms-1"></i></a>
															<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4" data-kt-menu="true">
																<div class="menu-item px-3">
																	<a class="menu-link px-3" target="_blank" href="'.url('damage-assessment/assessment/'.$ctr->parentglobalid).'" data-kt-users-table-filter="delete_row">'.e(__('ui.damage_common.assessment')).'</a>
																</div>
																<div class="menu-item px-3">
																	<a class="menu-link px-3" href="'.e($excelUrl).'">تصدير BOQ Excel</a>
																</div>
																<div class="menu-item px-3">
																	<a class="menu-link px-3" href="'.e($pdfUrl).'">تصدير BOQ PDF</a>
																</div>
															</div>';
            })
            ->setRowId('globalid')
            ->rawColumns(['action', 'id', 'unit_damage_status', 'support_summary'])
            ->make(true);
    }

    /**
     * @return array<int, array{title: string, filters: array<int, array{field: string, label: string, options: mixed}>}>
     */
    private function housingFilterSections($groupedFilters): array
    {
        $sections = [
            [
                'title' => __('ui.housing_page.filter_section_unit'),
                'filters' => [
                    ['field' => 'housing_unit_type', 'label' => __('ui.housing_page.unit_type')],
                    ['field' => 'unit_damage_status', 'label' => __('ui.housing_page.damage_status')],
                    ['field' => 'infra_type2', 'label' => __('ui.housing_page.unit_use')],
                    ['field' => 'house_unit_ownership', 'label' => __('ui.housing_page.unit_ownership')],
                    ['field' => 'occupied', 'label' => __('ui.housing_page.occupied')],
                    ['field' => 'unit_roof_type', 'label' => __('ui.housing_page.roof_type')],
                ],
            ],
            [
                'title' => __('ui.housing_page.filter_section_resident'),
                'filters' => [
                    ['field' => 'sex', 'label' => __('ui.housing_page.gender')],
                    ['field' => 'marital_status', 'label' => __('ui.housing_page.marital_status')],
                    ['field' => 'are_there_people_with_disability', 'label' => __('ui.housing_page.disability')],
                    ['field' => 'is_refugee', 'label' => __('ui.housing_page.refugee')],
                    ['field' => 'the_unit_resident', 'label' => __('ui.housing_page.unit_resident')],
                    ['field' => 'current_residence', 'label' => __('ui.housing_page.current_residence')],
                ],
            ],
            [
                'title' => __('ui.housing_page.filter_section_needs'),
                'filters' => [
                    ['field' => 'has_fire', 'label' => __('ui.housing_page.has_fire')],
                    ['field' => 'rubble_removal_is_needed', 'label' => __('ui.housing_page.rubble_removal_needed')],
                    ['field' => 'activation_of_uxo_ha_d_material_clearance', 'label' => __('ui.housing_page.uxo_clearance_needed')],
                    ['field' => 'unit_support_needed', 'label' => __('ui.housing_page.support_needed')],
                    ['field' => 'is_the_housing_unit_or_living_habitable', 'label' => __('ui.housing_page.habitable')],
                ],
            ],
        ];

        return collect($sections)
            ->map(function (array $section) use ($groupedFilters): array {
                $section['filters'] = collect($section['filters'])
                    ->filter(fn (array $filter): bool => Schema::hasColumn('housing_units', $filter['field']))
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

    private function applyHousingFilters(Builder $query, array $filters, string $table = 'housing_units'): void
    {
        $assignedTo = $filters['assignedto'] ?? null;

        if (is_array($assignedTo)) {
            $assignedTo = array_values(array_filter($assignedTo, fn ($item): bool => $item !== null && $item !== ''));
        }

        if ($assignedTo !== null && $assignedTo !== '' && $assignedTo !== []) {
            $query->whereHas('building', function (Builder $buildingQuery) use ($assignedTo): void {
                is_array($assignedTo)
                    ? $buildingQuery->whereIn('assignedto', $assignedTo)
                    : $buildingQuery->where('assignedto', $assignedTo);
            });
        }

        $endFrom = $filters['end_from'] ?? null;
        $endTo = $filters['end_to'] ?? null;

        if (($endFrom !== null && $endFrom !== '') || ($endTo !== null && $endTo !== '')) {
            $query->whereHas('building', function (Builder $buildingQuery) use ($endFrom, $endTo): void {
                if ($endFrom !== null && $endFrom !== '') {
                    $buildingQuery->whereDate('end', '>=', $endFrom);
                }

                if ($endTo !== null && $endTo !== '') {
                    $buildingQuery->whereDate('end', '<=', $endTo);
                }
            });
        }

        $selectFilters = [
            'housing_unit_type',
            'unit_damage_status',
            'infra_type2',
            'house_unit_ownership',
            'occupied',
            'unit_roof_type',
            'sex',
            'marital_status',
            'are_there_people_with_disability',
            'is_refugee',
            'the_unit_resident',
            'current_residence',
            'has_fire',
            'rubble_removal_is_needed',
            'activation_of_uxo_ha_d_material_clearance',
            'unit_support_needed',
            'is_the_housing_unit_or_living_habitable',
            'municipalitie',
            'neighborhood',
        ];

        foreach ($selectFilters as $field) {
            if (! Schema::hasColumn($table, $field)) {
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

        foreach ([
            'unit_owner',
            'id_number1',
            'q_9_3_1_first_name',
            'q_9_3_2_second_name__father',
            'q_9_3_3_third_name__grandfather',
            'q_9_3_4_last_name',
            'housing_unit_number',
            'objectid',
        ] as $field) {
            if (! Schema::hasColumn($table, $field)) {
                continue;
            }

            $value = $filters[$field] ?? null;

            if ($value !== null && $value !== '') {
                $query->where($field, 'like', '%'.$value.'%');
            }
        }

        foreach (['floor_number', 'damaged_area_m2', 'number_of_rooms', 'age'] as $field) {
            if (! Schema::hasColumn($table, $field)) {
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

        $submissionDateColumn = $this->housingSubmissionDateColumn($table);

        if ($submissionDateColumn !== null) {
            $from = $filters['submission_date_from'] ?? null;
            $to = $filters['submission_date_to'] ?? null;

            if ($from !== null && $from !== '') {
                $query->whereDate($submissionDateColumn, '>=', $from);
            }

            if ($to !== null && $to !== '') {
                $query->whereDate($submissionDateColumn, '<=', $to);
            }
        }
    }

    private function housingSubmissionDateColumn(string $table = 'housing_units'): ?string
    {
        foreach (['building_submit_date'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $colors
     * @param  array<string, string>  $labels
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

    private function housingSummary(?string $parentGlobalId): array
    {
        $query = HousingUnit::query();

        if ($parentGlobalId !== null) {
            $query->where('parentglobalid', $parentGlobalId);
        }

        $fullyDamaged = (clone $query)->where('unit_damage_status', 'fully_damaged2')->count();
        $partiallyDamaged = (clone $query)->where('unit_damage_status', 'partially_damaged2')->count();

        return [
            'total' => $fullyDamaged + $partiallyDamaged,
            'fully_damaged' => $fullyDamaged,
            'partially_damaged' => $partiallyDamaged,
            'committee_review' => (clone $query)->where('unit_damage_status', 'committee_review2')->count(),
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
            return Pdf::view('damage-assessment::pdf.building', compact('building', 'housingColumns'))
                ->format('a4')
                ->name('building-'.time().'.pdf');
        }

        $assessmentHints = Assessment::query()
            ->whereIn('name', $housingColumns)
            ->get(['name', 'hint', 'label'])
            ->keyBy('name');

        return Excel::download(new TableExport($housing, $housingColumns, $assessmentHints), time().'housing.'.$format);
    }

    public function export(HousingUnitExportRequest $request, ?string $format = null): BinaryFileResponse|PdfBuilder
    {
        $format = strtolower($format ?? (string) $request->input('format', 'xlsx'));

        abort_unless(in_array($format, ['xlsx', 'pdf', 'csv'], true), 422, 'صيغة التصدير غير صحيحة.');

        $housingColumns = $this->housingBoqSelectColumns();
        $assessmentHints = Assessment::query()
            ->whereIn('name', $this->housingBoqColumns())
            ->get(['name', 'hint', 'label'])
            ->keyBy('name');

        $housing = $this->exportQuery($request, $housingColumns)
            ->orderBy('objectid')
            ->get();
        $housing = $this->applyAuditedHousingValues($housing, $housingColumns);
        $boqRows = $this->housingBoqRows($housing, $assessmentHints);
        $summary = $this->housingBoqSummary($housing, $boqRows);

        $fileBaseName = $this->housingExportFileBaseName($housing);

        if ($format === 'pdf') {
            return Pdf::view('damage-assessment::surveys.housing-units.export_pdf', [
                'housing' => $housing,
                'boqRows' => $boqRows,
                'summary' => $summary,
                'sourceTable' => self::HOUSING_EXPORT_SOURCE_TABLE,
                'generatedAt' => now()->format('Y-m-d H:i'),
            ])
                ->format('a4')
                ->landscape()
                ->name($fileBaseName.'.pdf')
                ->withBrowsershot(function (Browsershot $browsershot): void {
                    app(BrowsershotConfiguration::class)->apply($browsershot);
                });
        }

        return Excel::download(
            new HousingUnitBoqExport($boqRows, $summary),
            $fileBaseName.'.'.$format
        );
    }

    /**
     * @param  array<int, string|null>|mixed  $requestedColumns
     * @return array<int, string>
     */
    private function exportColumns(mixed $requestedColumns): array
    {
        $availableColumns = Schema::getColumnListing('housing_units');
        $requestedColumns = is_array($requestedColumns) ? $requestedColumns : [];
        $columns = array_values(array_intersect(
            array_values(array_filter($requestedColumns, fn ($column): bool => is_string($column) && $column !== '')),
            $availableColumns
        ));

        if ($columns !== []) {
            return $columns;
        }

        return array_values(array_intersect([
            'objectid',
            'globalid',
            'parentglobalid',
            'housing_unit_type',
            'unit_damage_status',
            'floor_number',
            'housing_unit_number',
            'unit_owner',
            'id_number1',
            'mobile_number',
            'damaged_area_m2',
            'municipalitie',
            'neighborhood',
            'unit_support_needed',
            'is_the_housing_unit_or_living_habitable',
            'building_submit_date',
            'editdate',
        ], $availableColumns));
    }

    /**
     * @param  array<int, string>  $housingColumns
     */
    private function exportQuery(HousingUnitExportRequest $request, array $housingColumns): Builder
    {
        $query = HousingUnit::query()->select($housingColumns);
        $filters = $request->input('filters', []);

        if (! is_array($filters)) {
            $filters = [];
        }

        if ($request->filled('globalid')) {
            $query->where('globalid', $request->string('globalid')->toString());
        }

        if ($request->filled('parentglobalid')) {
            $query->where('parentglobalid', $request->string('parentglobalid')->toString());
        }

        $this->applyHousingFilters($query, $filters);

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private function housingBoqSelectColumns(): array
    {
        $availableColumns = Schema::getColumnListing('housing_units');

        return array_values(array_intersect(array_unique(array_merge([
            'objectid',
            'globalid',
            'parentglobalid',
            'housing_unit_number',
            'unit_owner',
            'q_9_3_1_first_name',
            'q_9_3_2_second_name__father',
            'q_9_3_3_third_name__grandfather',
            'q_9_3_4_last_name',
            'municipalitie',
            'neighborhood',
            'unit_damage_status',
        ], $this->housingBoqColumns())), $availableColumns));
    }

    /**
     * @return array<int, string>
     */
    private function housingBoqColumns(): array
    {
        return collect(Schema::getColumnListing('housing_units'))
            ->filter(fn (string $column): bool => $this->isHousingBoqColumn($column))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, HousingUnit>  $housing
     * @param  array<int, string>  $columns
     * @return Collection<int, HousingUnit>
     */
    private function applyAuditedHousingValues(Collection $housing, array $columns): Collection
    {
        if ($housing->isEmpty()) {
            return $housing;
        }

        $globalIds = $housing
            ->pluck('globalid')
            ->filter()
            ->values()
            ->all();

        if ($globalIds === []) {
            return $housing;
        }

        $auditedValues = DB::table('edit_assessments')
            ->where('type', 'housing_table')
            ->whereIn('global_id', $globalIds)
            ->whereIn('field_name', $columns)
            ->orderByDesc('id')
            ->get(['global_id', 'field_name', 'field_value'])
            ->groupBy('global_id')
            ->map(fn (Collection $edits): Collection => $edits->unique('field_name')->pluck('field_value', 'field_name'));

        return $housing->each(function (HousingUnit $housingUnit) use ($auditedValues): void {
            $unitAuditedValues = $auditedValues->get($housingUnit->globalid);

            if (! $unitAuditedValues instanceof Collection) {
                return;
            }

            $unitAuditedValues->each(function (mixed $value, string $field) use ($housingUnit): void {
                $housingUnit->setAttribute($field, $value);
            });
        });
    }

    /**
     * @param  Collection<int, HousingUnit>  $housing
     */
    private function housingExportFileBaseName(Collection $housing): string
    {
        $timestamp = now()->format('Ymd-His');

        if ($housing->count() === 1) {
            $ownerName = $this->safeExportFileSegment($this->housingUnitOwnerName($housing->first()));

            if ($ownerName !== null) {
                return 'جدول-الكميات-'.$ownerName.'-'.$timestamp;
            }
        }

        return 'housing-units-'.$timestamp;
    }

    private function safeExportFileSegment(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        $value = (string) preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $value);
        $value = (string) preg_replace('/\s+/u', '-', trim($value));
        $value = trim($value, '-_. ');

        return $value !== '' ? (string) str($value)->limit(80, '') : null;
    }

    private function isHousingBoqColumn(string $column): bool
    {
        return (bool) preg_match('/^(dm|bl|co|fn|al|wd|mt|cm|pm|el|pv)\d+$/', $column)
            || (bool) preg_match('/^(item|quant)\d+$/', $column)
            || $column === 'pv_note';
    }

    /**
     * @param  Collection<int, HousingUnit>  $housing
     * @param  Collection<string, Assessment>  $assessmentHints
     * @return Collection<int, array<string, mixed>>
     */
    private function housingBoqRows(Collection $housing, Collection $assessmentHints): Collection
    {
        $rows = collect();
        $boqColumns = $this->housingBoqColumns();

        foreach ($housing as $housingUnit) {
            $miscDescriptions = [];

            foreach ($boqColumns as $column) {
                $value = $this->cleanBoqValue($housingUnit->{$column} ?? null);

                if ($value === null) {
                    continue;
                }

                if (preg_match('/^item(\d+)$/', $column, $matches)) {
                    $miscDescriptions[$matches[1]] = $value;

                    continue;
                }

                if (preg_match('/^quant(\d+)$/', $column, $matches)) {
                    $description = $miscDescriptions[$matches[1]] ?? $this->boqDescription($column, $assessmentHints);
                    $rows->push($this->housingBoqRow($housingUnit, $column, $description, $value, 'ITEM'.$matches[1], 'Miscellaneous Works'));

                    continue;
                }

                $rows->push($this->housingBoqRow(
                    $housingUnit,
                    $column,
                    $this->boqDescription($column, $assessmentHints),
                    $value,
                    $this->boqItemCode($column, $assessmentHints),
                    $this->boqSection($column)
                ));
            }
        }

        return $rows->values();
    }

    private function housingBoqRow(HousingUnit $housingUnit, string $column, string $description, string $quantity, string $itemCode, string $section): array
    {
        return [
            'objectid' => $housingUnit->objectid,
            'globalid' => $housingUnit->globalid,
            'housing_unit_number' => $housingUnit->housing_unit_number ?: '-',
            'unit_owner' => $this->housingUnitOwnerName($housingUnit),
            'municipalitie' => $housingUnit->municipalitie ?: '-',
            'neighborhood' => $housingUnit->neighborhood ?: '-',
            'unit_damage_status' => $housingUnit->unit_damage_status ?: '-',
            'field' => $column,
            'section' => $section,
            'item_code' => $itemCode,
            'description' => $description,
            'unit' => $this->boqUnit($description),
            'quantity' => $quantity,
        ];
    }

    private function cleanBoqValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '0' || strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }

    /**
     * @param  Collection<string, Assessment>  $assessmentHints
     */
    private function boqDescription(string $column, Collection $assessmentHints): string
    {
        $assessment = $assessmentHints->get($column);
        $description = trim((string) ($assessment?->hint ?: $assessment?->label ?: $column));

        return $description !== '' ? $description : $column;
    }

    /**
     * @param  Collection<string, Assessment>  $assessmentHints
     */
    private function boqItemCode(string $column, Collection $assessmentHints): string
    {
        $label = trim((string) ($assessmentHints->get($column)?->label ?? ''));

        if (preg_match('/^([A-Za-z]{1,4}\d+[A-Za-z]?)\s*[-–]/', $label, $matches)) {
            return strtoupper($matches[1]);
        }

        return strtoupper($column);
    }

    private function boqUnit(string $description): string
    {
        if (preg_match('/\(([^()]+)\)\s*$/u', $description, $matches)) {
            return trim($matches[1]);
        }

        return '-';
    }

    private function boqSection(string $column): string
    {
        if (preg_match('/^fn(\d+)$/', $column, $matches)) {
            $number = (int) $matches[1];

            if ($number >= 1 && $number <= 3) {
                return 'Painting Works';
            }

            if (in_array($number, [5, 6, 7, 8, 10], true)) {
                return 'Tiling Works';
            }

            if ($number === 4 || ($number >= 11 && $number <= 15)) {
                return 'Marble Works';
            }

            if ($number >= 16 && $number <= 26) {
                return 'Plastering Works (Gypsum / Plaster)';
            }

            return 'External Finishings Works';
        }

        foreach ([
            'dm' => 'Demolishing Works',
            'bl' => 'Blocks Works',
            'co' => 'Concrete Works',
            'al' => 'Aluminum Works',
            'wd' => 'Wood Works',
            'mt' => 'Metal Works',
            'cm' => 'Combined',
            'pm' => 'Plumping Works',
            'el' => 'Electrical Works',
            'pv' => 'PV System Works',
        ] as $prefix => $section) {
            if (str_starts_with($column, $prefix)) {
                return $section;
            }
        }

        return 'Miscellaneous Works';
    }

    private function housingUnitOwnerName(HousingUnit $housingUnit): string
    {
        $name = collect([
            $housingUnit->q_9_3_1_first_name,
            $housingUnit->q_9_3_2_second_name__father,
            $housingUnit->q_9_3_3_third_name__grandfather,
            $housingUnit->q_9_3_4_last_name,
        ])->filter()->implode(' ');

        return $name !== '' ? $name : ($housingUnit->unit_owner ?: '-');
    }

    /**
     * @param  Collection<int, HousingUnit>  $housing
     * @param  Collection<int, array<string, mixed>>  $boqRows
     * @return array<string, mixed>
     */
    private function housingBoqSummary(Collection $housing, Collection $boqRows): array
    {
        return [
            'units_count' => $housing->count(),
            'rows_count' => $boqRows->count(),
            'sections_count' => $boqRows->pluck('section')->unique()->count(),
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
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
