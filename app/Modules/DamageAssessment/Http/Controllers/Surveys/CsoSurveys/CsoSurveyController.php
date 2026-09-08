<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Surveys\CsoSurveys;

use App\Exports\CsoSurveyOrganizationsExport;
use App\Exports\CsoSurveysExport;
use App\Exports\CsoSurveysFlatExport;
use App\Exports\CsoSurveysWorkbookExport;
use App\Exports\CsoSurveyUnitsExport;
use App\Http\Controllers\Controller;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Support\CsoDamageStatusMapper;
use App\Support\Forms\CsoSurveyLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class CsoSurveyController extends Controller
{
    public function index(): View
    {
        return view('damage-assessment::surveys.cso.index', $this->indexData());
    }

    public function exportData(): View
    {
        return view('damage-assessment::surveys.cso.export-data', $this->indexData());
    }

    /**
     * @return array{
     *     summary: array{total_surveys: int, total_organizations: int, total_units: int, damaged_buildings: int},
     *     filterOptions: array{municipalities: Collection, neighborhoods: Collection, researchers: Collection, surveyStatuses: Collection, damageStatuses: Collection, operationalStatuses: Collection, min_creationdate: ?string, max_creationdate: ?string},
     *     exportColumns: array{surveys: array<string, string>, organizations: array<string, string>, units: array<string, string>},
     *     exportColumnGroups: array{surveys: array<string, array<string, string>>, organizations: array<string, array<string, string>>, units: array<string, array<string, string>>}
     * }
     */
    private function indexData(): array
    {
        $summary = [
            'total_surveys' => CsoSurvey::query()->count(),
            'total_organizations' => CsoSurveyOrganization::query()->count(),
            'total_units' => CsoSurveyUnit::query()->count(),
            'damaged_buildings' => CsoSurvey::query()
                ->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '')
                ->count(),
        ];

        $filterOptions = [
            'municipalities' => CsoSurvey::query()->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),
            'neighborhoods' => CsoSurvey::query()->distinct()->orderBy('neighborhood')->pluck('neighborhood')->filter()->values(),
            'researchers' => CsoSurvey::query()->distinct()->orderBy('assignedto')->pluck('assignedto')->filter()->values(),
            'surveyStatuses' => CsoSurvey::query()->distinct()->orderBy('field_status')->pluck('field_status')->filter()->values(),
            'damageStatuses' => $this->damageStatusOptions(),
            'operationalStatuses' => CsoSurvey::query()->distinct()->orderBy('operational_status')->pluck('operational_status')->filter()->values(),
            'min_creationdate' => optional(CsoSurvey::query()->whereNotNull('creationdate')->min('creationdate'))?->format('Y-m-d'),
            'max_creationdate' => optional(CsoSurvey::query()->whereNotNull('creationdate')->max('creationdate'))?->format('Y-m-d'),
        ];

        $exportColumns = [
            'surveys' => CsoSurveysExport::availableColumns(),
            'organizations' => CsoSurveyOrganizationsExport::availableColumns(),
            'units' => CsoSurveyUnitsExport::availableColumns(),
        ];

        $exportColumnGroups = [
            'surveys' => CsoSurveysExport::availableColumnGroups(),
            'organizations' => CsoSurveyOrganizationsExport::availableColumnGroups(),
            'units' => CsoSurveyUnitsExport::availableColumnGroups(),
        ];

        return compact('summary', 'filterOptions', 'exportColumns', 'exportColumnGroups');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->filteredQuery($request)->withCount(['organizations', 'units']))
            ->addColumn('actions', fn (CsoSurvey $survey): string => '<a href="'.route('cso-surveys.show', $survey).'" class="btn btn-light btn-sm">View</a>')
            ->editColumn('creationdate', fn (CsoSurvey $survey): string => $survey->creationdate?->format('Y-m-d H:i') ?? '-')
            ->editColumn('field_status', fn (CsoSurvey $survey): string => $this->statusBadge($survey->field_status, 'primary'))
            ->editColumn('building_damage_status', fn (CsoSurvey $survey): string => '<span class="badge badge-light-danger">'.e($this->damageStatusLabel($survey->building_damage_status)).'</span>')
            ->addColumn('assignedto', fn (CsoSurvey $survey): string => $survey->assignedto ?? '-')
            ->rawColumns(['actions', 'field_status', 'building_damage_status'])
            ->toJson();
    }

    public function export(Request $request, string $format): BinaryFileResponse|Response
    {
        $format = strtolower($format);

        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $surveys = $this->filteredQuery($request)
            ->withCount(['organizations', 'units'])
            ->get();

        $surveys->load([
            'organizations' => fn ($query) => $query->orderBy('objectid'),
            'units' => fn ($query) => $query->orderBy('objectid'),
        ]);

        $fileBaseName = 'cso_surveys_'.now()->format('Ymd_His');
        $pdfDefaultSurveyColumns = $format === 'pdf' ? array_keys(CsoSurveysExport::availableColumnGroups()['Summary']) : null;
        $pdfDefaultOrganizationColumns = $format === 'pdf' ? array_keys(CsoSurveyOrganizationsExport::availableColumnGroups()['Summary']) : null;
        $pdfDefaultUnitColumns = $format === 'pdf' ? array_keys(CsoSurveyUnitsExport::availableColumnGroups()['Summary']) : null;
        $surveyColumns = $this->selectedExportColumns($request, 'cso_survey_columns', CsoSurveysExport::availableColumns(), $pdfDefaultSurveyColumns);
        $organizationColumns = $this->selectedExportColumns($request, 'cso_organization_columns', CsoSurveyOrganizationsExport::availableColumns(), $pdfDefaultOrganizationColumns);
        $unitColumns = $this->selectedExportColumns($request, 'cso_unit_columns', CsoSurveyUnitsExport::availableColumns(), $pdfDefaultUnitColumns);

        if ($format === 'pdf') {
            $flatExport = new CsoSurveysFlatExport($surveys, $surveyColumns, $organizationColumns, $unitColumns);

            return Pdf::loadView('damage-assessment::surveys.cso.export_pdf', [
                'surveys' => $surveys,
                'filters' => $request->all(),
                'rows' => $flatExport->rows(),
                'columns' => [
                    'surveys' => array_intersect_key(CsoSurveysExport::availableColumns(), array_flip($surveyColumns)),
                    'organizations' => array_intersect_key(CsoSurveyOrganizationsExport::availableColumns(), array_flip($organizationColumns)),
                    'units' => array_intersect_key(CsoSurveyUnitsExport::availableColumns(), array_flip($unitColumns)),
                ],
            ])->setPaper('a4', 'landscape')->download($fileBaseName.'.pdf');
        }

        return Excel::download(
            $format === 'xlsx'
                ? new CsoSurveysWorkbookExport($surveys, $surveyColumns, $organizationColumns, $unitColumns)
                : new CsoSurveysFlatExport($surveys, $surveyColumns, $organizationColumns, $unitColumns),
            $fileBaseName.'.'.$format,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    public function show(CsoSurvey $csoSurvey): View
    {
        $csoSurvey->load([
            'organizations' => fn ($query) => $query->orderBy('objectid'),
            'units' => fn ($query) => $query->orderBy('objectid'),
        ]);

        $organizationGroups = $this->organizationGroups($csoSurvey);

        return view('damage-assessment::surveys.cso.show', [
            'survey' => $csoSurvey,
            'sections' => $this->surveySections($csoSurvey),
            'organizationGroups' => $organizationGroups,
            'unitCount' => $organizationGroups->sum(fn (array $group): int => $group['units']->count()),
            'buildingDamage' => CsoDamageStatusMapper::bucket($csoSurvey->building_damage_status),
            'damageBuckets' => [
                CsoDamageStatusMapper::FULLY_DAMAGED,
                CsoDamageStatusMapper::PARTIALLY_DAMAGED,
                CsoDamageStatusMapper::NO_DAMAGE,
                CsoDamageStatusMapper::COMMITTEE_REVIEW,
                CsoDamageStatusMapper::UNCLASSIFIED,
            ],
        ]);
    }

    private function organizationGroups(CsoSurvey $survey): Collection
    {
        $organizations = $survey->organizations;
        $organizationSections = CsoSurveyLayout::repeatSections('CSO_Organizations');
        $unitSections = CsoSurveyLayout::repeatSections('Unit_Information');
        $organizationIds = $organizations->pluck('globalid')->filter()->values();
        $units = $survey->units->concat(
            CsoSurveyUnit::query()->whereIn('parentglobalid', $organizationIds)->orderBy('objectid')->get()
        )->unique('id')->sortBy('objectid')->values();
        $organizationLookup = $organizations->groupBy(
            fn (CsoSurveyOrganization $organization): string => $this->normalizeGlobalId($organization->globalid)
        );

        $groupedUnits = $units->groupBy(function (CsoSurveyUnit $unit) use ($organizationLookup): string {
            // Sync retains the original organization parent in raw_payload when flattening the survey relation.
            $sourceParent = CsoSurveyLayout::value((object) ['raw_payload' => $unit->raw_payload], 'parentglobalid');
            $matches = collect([$sourceParent, $unit->parentglobalid])
                ->map(fn (mixed $parent): string => $this->normalizeGlobalId($parent))
                ->filter(fn (string $parent): bool => $parent !== '' && $organizationLookup->has($parent))
                ->unique()->values();

            if ($matches->count() !== 1 || $organizationLookup[$matches->first()]->count() !== 1) {
                return 'unassigned';
            }

            return 'organization-'.$organizationLookup[$matches->first()]->first()->id;
        });

        $groups = $organizations->map(function (CsoSurveyOrganization $organization) use ($groupedUnits, $organizationSections): array {
            $key = 'organization-'.$organization->id;
            $preferredName = app()->getLocale() === 'ar' ? 'organization_name_ar' : 'organization_name_en';
            $name = CsoSurveyLayout::value($organization, $preferredName)
                ?: $organization->organization_name_en ?: $organization->organization_name_ar
                ?: __('cso_details.organization_number', ['number' => $organization->objectid ?? $organization->id]);

            return [
                'key' => $key,
                'name' => $name,
                'organization' => $organization,
                'registration' => CsoSurveyLayout::value($organization, 'registration_number') ?? '-',
                'active' => CsoSurveyLayout::displayValue(CsoSurveyLayout::value($organization, 'is_organization_active'), [
                    'type' => 'select_one', 'list_name' => 'yes_no',
                ]) ?? '-',
                'sections' => $this->detailSections($organization, $organizationSections),
                'units' => $groupedUnits->get($key, collect()),
            ];
        });

        if ($groupedUnits->has('unassigned')) {
            $groups->push([
                'key' => 'unassigned',
                'name' => __('cso_details.unassigned'),
                'organization' => null,
                'sections' => [],
                'units' => $groupedUnits['unassigned'],
            ]);
        }

        return $groups->map(function (array $group) use ($unitSections): array {
            $group['units'] = $group['units']->map(fn (CsoSurveyUnit $unit): array => [
                'id' => $unit->id,
                'name' => CsoSurveyLayout::value($unit, 'unit_name')
                    ?: __('cso_details.unit_number', ['number' => $unit->unit_number ?? $unit->objectid ?? $unit->id]),
                'number' => CsoSurveyLayout::value($unit, 'unit_number')
                    ?? CsoSurveyLayout::value($unit, 'building_unit_number') ?? '-',
                'floor' => CsoSurveyLayout::value($unit, 'unit_floor_number')
                    ?? CsoSurveyLayout::value($unit, 'floor_number') ?? '-',
                'function' => CsoSurveyLayout::displayValue(CsoSurveyLayout::value($unit, 'unit_function'), [
                    'type' => 'select_multiple', 'list_name' => 'unit_function',
                ]) ?? '-',
                'damage' => CsoDamageStatusMapper::bucket(CsoSurveyLayout::value($unit, 'unit_damage_status')),
                'sections' => $this->detailSections($unit, $unitSections),
            ])->values();
            $group['damageCounts'] = $group['units']->countBy('damage');

            return $group;
        })->values();
    }

    private function normalizeGlobalId(mixed $value): string
    {
        return is_scalar($value) ? strtolower(trim(trim((string) $value), '{}')) : '';
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = CsoSurvey::query();

        foreach (['municipalitie', 'neighborhood', 'assignedto', 'field_status', 'building_damage_status', 'operational_status'] as $field) {
            $values = $this->requestValues($request, $field);

            if ($values !== []) {
                $this->applyNullableValueFilter($query, $field, $values);
            }
        }

        $this->applyChildFilters($query, 'organizations', CsoSurveyOrganization::class, $request->input('organization_filters', []));
        $this->applyChildFilters($query, 'units', CsoSurveyUnit::class, $request->input('unit_filters', []));

        if ($request->filled('from_date')) {
            $query->whereDate('creationdate', '>=', $request->date('from_date')->toDateString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('creationdate', '<=', $request->date('to_date')->toDateString());
        }

        if ($request->boolean('damaged_only')) {
            $query->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '');
        }

        if ($request->boolean('with_organizations')) {
            $query->has('organizations');
        }

        if ($request->boolean('with_units')) {
            $query->has('units');
        }

        $search = trim((string) $request->input('search.value', $request->input('q', $request->input('search', ''))));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested
                    ->where('organization_name', 'like', '%'.$search.'%')
                    ->orWhere('building_name', 'like', '%'.$search.'%')
                    ->orWhere('municipalitie', 'like', '%'.$search.'%')
                    ->orWhere('neighborhood', 'like', '%'.$search.'%')
                    ->orWhere('objectid', 'like', '%'.$search.'%')
                    ->orWhere('field_status', 'like', '%'.$search.'%')
                    ->orWhere('building_damage_status', 'like', '%'.$search.'%')
                    ->orWhere('operational_status', 'like', '%'.$search.'%')
                    ->orWhere('assignedto', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    private function surveySections(CsoSurvey $survey): array
    {
        $repeatSectionNames = array_merge(
            CsoSurveyLayout::repeatSectionNames('CSO_Organizations'),
            CsoSurveyLayout::repeatSectionNames('Unit_Information'),
            ['CSO_Organizations', 'Unit_Information'],
        );

        return collect(CsoSurveyLayout::sections())
            ->reject(fn (array $section): bool => in_array($section['name'] ?? '', $repeatSectionNames, true))
            ->map(fn (array $section): array => [
                'title' => $this->sectionTitle($section),
                'name' => $section['name'],
                'rows' => $this->rowsFromLayoutFields($survey, $section['fields'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function detailSections(object $record, array $sections): array
    {
        return collect($sections)
            ->map(fn (array $section): array => [
                'title' => $this->sectionTitle($section),
                'name' => $section['name'],
                'rows' => $this->rowsFromLayoutFields($record, $section['fields'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function sectionTitle(array $section): string
    {
        return (app()->getLocale() === 'ar' ? ($section['hint'] ?? null) : null)
            ?: ($section['label'] ?: $section['name']);
    }

    private function rowsFromLayoutFields(object $record, array $fields): array
    {
        return collect($fields)
            ->reject(fn (array $field): bool => ($field['type'] ?? null) === 'calculate')
            ->map(function (array $field) use ($record): array {
                $value = CsoSurveyLayout::value($record, $field['name']);
                $answer = in_array($field['name'] ?? null, ['building_damage_status', 'unit_damage_status'], true)
                    ? __('cso_details.damage.'.CsoDamageStatusMapper::bucket($value))
                    : CsoSurveyLayout::displayValue($value, $field);

                return [
                    'question' => (app()->getLocale() === 'ar' ? ($field['hint'] ?? null) : null)
                        ?: ($field['label'] ?: $field['name']),
                    'answer' => $answer ?? $this->emptyAnswerText($field),
                    'empty' => $answer === null,
                ];
            })
            ->values()
            ->all();
    }

    private function requestValues(Request $request, string $key): array
    {
        $value = $request->input($key, $request->input($key.'.*'));

        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $values
     */
    private function applyNullableValueFilter(Builder $query, string $field, array $values): void
    {
        $values = collect($values);
        $includesBlank = $values->contains('__blank__') || $values->contains('__NULL__');
        $explicitValues = $values
            ->reject(fn (string $value): bool => in_array($value, ['__blank__', '__NULL__'], true))
            ->values()
            ->all();

        $query->where(function (Builder $query) use ($field, $includesBlank, $explicitValues): void {
            if ($explicitValues !== []) {
                $query->whereIn($field, $explicitValues);
            }

            if ($includesBlank) {
                $method = $explicitValues === [] ? 'where' : 'orWhere';
                $query->{$method}(fn (Builder $query): Builder => $query->whereNull($field)->orWhere($field, ''));
            }
        });
    }

    private function applyChildFilters(Builder $query, string $relation, string $modelClass, mixed $filters): void
    {
        if (! is_array($filters) || $filters === []) {
            return;
        }

        $table = (new $modelClass)->getTable();

        foreach ($filters as $field => $values) {
            $field = (string) $field;

            if (! Schema::hasColumn($table, $field)) {
                continue;
            }

            $values = $this->normalizeValues($values);

            if ($values === []) {
                continue;
            }

            $query->whereHas($relation, function (Builder $query) use ($field, $values): void {
                $this->applyNullableValueFilter($query, $field, $values);
            });
        }
    }

    private function statusBadge(?string $status, string $color): string
    {
        return '<span class="badge badge-light-'.$color.'">'.e($status ?: '-').'</span>';
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    private function damageStatusOptions(): Collection
    {
        $knownStatuses = collect($this->damageStatusLabels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values();

        $storedStatuses = CsoSurvey::query()
            ->distinct()
            ->orderBy('building_damage_status')
            ->pluck('building_damage_status')
            ->filter()
            ->map(fn (mixed $status): string => trim((string) $status))
            ->filter(fn (string $status): bool => $status !== '')
            ->reject(fn (string $status): bool => array_key_exists($status, $this->damageStatusLabels()))
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $this->damageStatusLabel($status),
            ])
            ->values();

        return $knownStatuses->merge($storedStatuses);
    }

    private function damageStatusLabel(?string $status): string
    {
        $status = trim((string) $status);

        if ($status === '') {
            return '-';
        }

        return $this->damageStatusLabels()[$status] ?? match (strtolower($status)) {
            'total', 'totally', 'total_damage', 'totally_damaged', 'totally damaged', 'fully_damaged', 'fully damaged' => 'ضرر كلي',
            'partial', 'partial_damage', 'partially_damaged', 'partially damaged' => 'ضرر جزئي',
            'committee_review', 'technical_committee', 'technical committee' => 'لجنة فنية',
            default => $status,
        };
    }

    /**
     * @return array<string, string>
     */
    private function damageStatusLabels(): array
    {
        return [
            '1' => 'ضرر كلي',
            '2' => 'ضرر جزئي',
            '3' => 'لجنة فنية',
        ];
    }

    /**
     * @param  array<string, string>  $availableColumns
     * @return array<int, string>
     */
    private function selectedExportColumns(Request $request, string $key, array $availableColumns, ?array $defaultColumns = null): array
    {
        $availableColumnKeys = array_keys($availableColumns);

        if (! $request->has($key) && ! $request->has($key.'_mode') && $defaultColumns !== null) {
            return array_values(array_intersect($defaultColumns, $availableColumnKeys));
        }

        if ($request->input($key.'_mode') === 'except') {
            $excludedColumns = array_values(array_intersect(
                $this->normalizeValues($request->input($key.'_excluded')),
                $availableColumnKeys,
            ));

            return array_values(array_diff($availableColumnKeys, $excludedColumns));
        }

        $selectedColumns = array_values(array_intersect(
            $this->normalizeValues($request->input($key)),
            $availableColumnKeys,
        ));

        return $selectedColumns !== [] ? $selectedColumns : $availableColumnKeys;
    }

    private function normalizeValues(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function emptyAnswerText(array $field): string
    {
        return ($field['type'] ?? null) === 'image' ? 'لا يوجد مرفق' : 'لا يوجد جواب';
    }
}
