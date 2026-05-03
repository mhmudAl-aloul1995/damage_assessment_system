<?php

declare(strict_types=1);

namespace App\Http\Controllers\RoadFacility;

use App\Exports\RoadFacilitySurveysExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoadFacility\RoadFacilityFilterRequest;
use App\Models\RoadFacilityFilter;
use App\Models\RoadFacilitySurvey;
use App\Support\Forms\RoadFacilitySurveyLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class RoadFacilityController extends Controller
{
    public function index(): View
    {
        $summary = [
            'total_surveys' => RoadFacilitySurvey::query()->count(),
            'total_items' => RoadFacilitySurvey::query()->withCount('items')->get()->sum('items_count'),
            'damaged_roads' => RoadFacilitySurvey::query()
                ->whereNotNull('road_damage_level')
                ->where('road_damage_level', '!=', '')
                ->count(),
        ];

        $databaseFilterGroups = RoadFacilityFilter::query()
            ->orderBy('list_name')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy('list_name');

        $filterGroups = $databaseFilterGroups->isNotEmpty() ? $databaseFilterGroups : $this->fallbackFilterGroups();

        $researcherColumn = $this->researcherColumn();

        $filterOptions = [
            'municipalities' => RoadFacilitySurvey::query()->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),
            'neighborhoods' => RoadFacilitySurvey::query()->distinct()->orderBy('neighborhood')->pluck('neighborhood')->filter()->values(),
            'researchers' => $researcherColumn
                ? RoadFacilitySurvey::query()->distinct()->orderBy($researcherColumn)->pluck($researcherColumn)->filter()->values()
                : collect(),
            'min_submissiondate' => optional(RoadFacilitySurvey::query()->whereNotNull('submissiondate')->min('submissiondate'))?->format('Y-m-d'),
            'max_submissiondate' => optional(RoadFacilitySurvey::query()->whereNotNull('submissiondate')->max('submissiondate'))?->format('Y-m-d'),
        ];

        return view('RoadFacility.index', [
            'summary' => $summary,
            'filterOptions' => $filterOptions,
            'filterGroups' => $filterGroups,
        ]);
    }

    public function data(RoadFacilityFilterRequest $request): JsonResponse
    {
        return DataTables::eloquent($this->filteredQuery($request))
            ->addColumn('actions', function (RoadFacilitySurvey $survey): string {
                return '<a href="'.route('road-facilities.show', $survey).'" class="btn btn-light btn-sm">View</a>';
            })
            ->editColumn('submissiondate', function (RoadFacilitySurvey $survey): string {
                return $survey->submissiondate?->format('Y-m-d H:i') ?? '-';
            })
            ->editColumn('road_damage_level', function (RoadFacilitySurvey $survey): string {
                return '<span class="badge badge-light-danger">'.e($survey->road_damage_level ?? '-').'</span>';
            })
            ->addColumn('assignedto', fn (RoadFacilitySurvey $survey): string => $survey->assignedto ?? '-')
            ->rawColumns(['actions', 'road_damage_level'])
            ->toJson();
    }

    public function export(RoadFacilityFilterRequest $request, string $format): BinaryFileResponse|Response
    {
        $format = strtolower($format);

        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $surveys = $this->filteredQuery($request)->get();
        $fileBaseName = 'road_facilities_'.now()->format('Ymd_His');

        if ($format === 'pdf') {
            return Pdf::loadView('RoadFacility.export_pdf', [
                'surveys' => $surveys,
                'filters' => $request->validated(),
            ])->setPaper('a4', 'landscape')->download($fileBaseName.'.pdf');
        }

        return Excel::download(
            new RoadFacilitySurveysExport($surveys),
            $fileBaseName.'.'.$format,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    public function show(RoadFacilitySurvey $roadFacility): View
    {
        $roadFacility->load(['items' => fn ($q) => $q->orderBy('objectid')]);
        $itemSections = RoadFacilitySurveyLayout::repeatSections('R2');

        return view('RoadFacility.show', [
            'survey' => $roadFacility,
            'sections' => $this->buildSurveySections($roadFacility),
            'itemSections' => $roadFacility->items
                ->values()
                ->flatMap(fn ($item, int $index): array => collect($itemSections)
                    ->map(fn (array $section): array => [
                        'title' => 'Required Item '.($index + 1).' - '.$this->sectionTitle($section),
                        'name' => $section['name'],
                        'rows' => $this->rowsFromLayoutFields($item, $section['fields']),
                    ])
                    ->values()
                    ->all())
                ->values()
                ->all(),
        ]);
    }

    private function buildSurveySections(RoadFacilitySurvey $survey): array
    {
        return collect(RoadFacilitySurveyLayout::sections())
            ->reject(fn (array $section): bool => ($section['type'] ?? 'group') === 'repeat')
            ->map(fn (array $section): array => [
                'title' => $this->sectionTitle($section),
                'name' => $section['name'],
                'rows' => $this->rowsFromLayoutFields($survey, $section['fields']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, type: string, label: string, hint: ?string, list_name: ?string}>  $fields
     * @return array<int, array{question: string, answer: string, empty: bool}>
     */
    private function rowsFromLayoutFields(object $record, array $fields): array
    {
        return collect($fields)
            ->reject(fn (array $field): bool => ($field['type'] ?? null) === 'calculate')
            ->map(function (array $field) use ($record): array {
                $value = RoadFacilitySurveyLayout::value($record, $field['name']);
                $answer = RoadFacilitySurveyLayout::displayValue($value, $field);
                $isEmpty = $answer === null;

                return [
                    'question' => $field['label'] ?: $field['name'],
                    'answer' => $answer ?? $this->emptyAnswerText($field),
                    'empty' => $isEmpty,
                ];
            })
            ->values()
            ->all();
    }

    private function sectionTitle(array $section): string
    {
        return (string) ($section['label'] ?? $section['name'] ?? 'Section');
    }

    private function emptyAnswerText(array $field): string
    {
        return ($field['type'] ?? null) === 'image' ? 'لا يوجد مرفق' : 'لا يوجد جواب';
    }

    protected function filteredQuery(RoadFacilityFilterRequest $request): Builder
    {
        $query = RoadFacilitySurvey::query()->withCount('items');

        $municipalities = $this->requestValues($request, 'municipalitie');
        if ($municipalities !== []) {
            $query->whereIn('municipalitie', $municipalities);
        }

        $neighborhoods = $this->requestValues($request, 'neighborhood');
        if ($neighborhoods !== []) {
            $query->whereIn('neighborhood', $neighborhoods);
        }

        $researcherColumn = $this->researcherColumn();

        $assignedTo = $this->requestValues($request, 'assignedto', 'assignedto');
        if ($assignedTo !== [] && $researcherColumn) {
            $query->whereIn($researcherColumn, $assignedTo);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('submissiondate', '>=', $request->date('from_date')->toDateString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('submissiondate', '<=', $request->date('to_date')->toDateString());
        }

        if ($request->boolean('damaged_only')) {
            $query->whereNotNull('road_damage_level')
                ->where('road_damage_level', '!=', '');
        }

        if ($request->boolean('with_items')) {
            $query->has('items');
        }

        if ($request->boolean('has_municipality')) {
            $query->whereNotNull('municipalitie')
                ->where('municipalitie', '!=', '');
        }

        if ($request->boolean('has_neighborhood')) {
            $query->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '');
        }

        if ($request->boolean('potholes_only')) {
            $query->where('potholes_exist', 'yes');
        }

        if ($request->boolean('obstacles_only')) {
            $query->where('obstacle_exist', 'yes');
        }

        if ($request->boolean('buried_bodies_only')) {
            $query->where('buried_bodies', 'yes');
        }

        if ($request->boolean('uxo_only')) {
            $query->where('uxo_present', 'yes');
        }

        $search = trim((string) $request->input('search.value', $request->input('search', '')));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested
                    ->where('str_name', 'like', '%'.$search.'%')
                    ->orWhere('municipalitie', 'like', '%'.$search.'%')
                    ->orWhere('neighborhood', 'like', '%'.$search.'%')
                    ->orWhere('objectid', 'like', '%'.$search.'%')
                    ->orWhere('road_damage_level', 'like', '%'.$search.'%')
                    ->orWhere('road_access', 'like', '%'.$search.'%')
                    ->orWhere('lane_count', 'like', '%'.$search.'%')
                    ->orWhere('blockage_reason', 'like', '%'.$search.'%');

                $researcherColumn = $this->researcherColumn();

                if ($researcherColumn) {
                    $nested->orWhere($researcherColumn, 'like', '%'.$search.'%');
                }
            });
        }

        $this->applyDynamicFilters($query, collect($request->validated('filters', [])));

        return $query;
    }

    private function applyDynamicFilters(Builder $query, Collection $filters): void
    {
        foreach ($filters->filter() as $filterName => $value) {
            $resolvedFilter = $this->resolveDynamicFilter((string) $filterName);

            if ($resolvedFilter === null) {
                continue;
            }

            $column = $resolvedFilter['column'];
            $mode = $resolvedFilter['mode'];
            $filterValues = $this->normalizeValues($value);

            if ($filterValues === []) {
                continue;
            }

            if ($mode === 'exact') {
                $query->whereIn($column, $filterValues);

                continue;
            }

            if ($mode === 'json_like') {
                $query->where(function (Builder $nested) use ($column, $filterValues): void {
                    foreach ($filterValues as $filterValue) {
                        $nested->orWhere($column, 'like', '%"'.$filterValue.'"%');
                    }
                });

                continue;
            }

            if ($mode === 'voltage_level') {
                $query->where(function (Builder $nested) use ($filterValues): void {
                    $nested
                        ->whereIn('pole_voltage_level', $filterValues)
                        ->orWhereIn('cable_voltage_level', $filterValues);
                });

                continue;
            }

            if ($mode === 'raw_payload_like') {
                $query->where(function (Builder $nested) use ($filterValues): void {
                    foreach ($filterValues as $filterValue) {
                        $nested->orWhere('raw_payload', 'like', '%'.$filterValue.'%');
                    }
                });
            }
        }
    }

    private function requestValues(RoadFacilityFilterRequest $request, string ...$keys): array
    {
        foreach ($keys as $key) {
            $values = $this->normalizeValues($request->input($key));

            if ($values !== []) {
                return $values;
            }
        }

        return [];
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
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function researcherColumn(): ?string
    {
        if (Schema::hasColumn('road_facility_surveys', 'assignedto')) {
            return 'assignedto';
        }

        if (Schema::hasColumn('road_facility_surveys', 'assignedto')) {
            return 'assignedto';
        }

        return null;
    }

    /**
     * @return array{column: string, mode: string}|null
     */
    private function resolveDynamicFilter(string $filterName): ?array
    {
        $filterMappings = [
            'security' => ['column' => 'security_situation', 'mode' => 'exact'],
            'demolition_type' => ['column' => 'demolition_scope', 'mode' => 'exact'],
            'voltage_level' => ['column' => 'pole_voltage_level', 'mode' => 'voltage_level'],
            'locality' => ['column' => 'municipalitie', 'mode' => 'exact'],
        ];

        if (array_key_exists($filterName, $filterMappings)) {
            return $filterMappings[$filterName];
        }

        if (! Schema::hasColumn('road_facility_surveys', $filterName)) {
            return ['column' => 'raw_payload', 'mode' => 'raw_payload_like'];
        }

        $jsonColumns = [
            'blockage_reason',
            'road_type',
            'sidewalk_damage_type',
            'pole_type',
            'traffic_signs_type',
        ];

        if (in_array($filterName, $jsonColumns, true)) {
            return ['column' => $filterName, 'mode' => 'json_like'];
        }

        return ['column' => $filterName, 'mode' => 'exact'];
    }

    private function fallbackFilterGroups(): Collection
    {
        return collect([
            'governorate' => collect([
                (object) ['name' => 'North', 'label' => 'North'],
                (object) ['name' => 'Gaza', 'label' => 'Gaza'],
                (object) ['name' => 'Middle_Area', 'label' => 'Middle Area'],
                (object) ['name' => 'Khan_Younis', 'label' => 'Khan Younis'],
                (object) ['name' => 'Rafah', 'label' => 'Rafah'],
            ]),
            'road_damage_level' => collect([
                (object) ['name' => 'minor', 'label' => 'Minor damage (road usable)'],
                (object) ['name' => 'moderate', 'label' => 'Moderate damage (partial use / needs maintenance)'],
                (object) ['name' => 'severe', 'label' => 'Severe damage (not usable)'],
                (object) ['name' => 'destroyed', 'label' => 'Totally destroyed'],
                (object) ['name' => 'No_Damage', 'label' => 'No Damage'],
            ]),
            'road_access' => collect([
                (object) ['name' => 'open', 'label' => 'Fully open'],
                (object) ['name' => 'partial', 'label' => 'Partially open'],
                (object) ['name' => 'closed', 'label' => 'Fully closed'],
            ]),
            'lane_count' => collect([
                (object) ['name' => 'one', 'label' => 'One lane'],
                (object) ['name' => 'two', 'label' => 'Two lanes'],
                (object) ['name' => 'more', 'label' => 'More than two'],
            ]),
            'traffic_signs_type' => collect([
                (object) ['name' => 'guide', 'label' => 'Guide signs'],
                (object) ['name' => 'warning', 'label' => 'Warning signs'],
                (object) ['name' => 'traffic_light', 'label' => 'Traffic lights'],
            ]),
            'demolition_type' => collect([
                (object) ['name' => 'whole', 'label' => 'Whole'],
                (object) ['name' => 'partial', 'label' => 'Partially'],
                (object) ['name' => 'no_need', 'label' => 'No need'],
            ]),
            'pole_material' => collect([
                (object) ['name' => 'galvanized', 'label' => 'Galvanized steel pole'],
                (object) ['name' => 'wooden', 'label' => 'Wooden pole'],
            ]),
            'voltage_level' => collect([
                (object) ['name' => 'low', 'label' => 'Low voltage'],
                (object) ['name' => 'high', 'label' => 'High voltage'],
            ]),
            'blockage_reason' => collect([
                (object) ['name' => 'landfill', 'label' => 'Backfill / excavation'],
                (object) ['name' => 'debris', 'label' => 'Debris / rubble'],
                (object) ['name' => 'poles', 'label' => 'Fallen poles'],
                (object) ['name' => 'uxo', 'label' => 'Security risk (UXO)'],
            ]),
            'sidewalk_damage_type' => collect([
                (object) ['name' => 'broken_tiles', 'label' => 'Broken tiles'],
                (object) ['name' => 'missing_tiles', 'label' => 'Missing tiles'],
                (object) ['name' => 'collapsed_sidewalk', 'label' => 'Collapsed sidewalk'],
            ]),
        ]);
    }
}
