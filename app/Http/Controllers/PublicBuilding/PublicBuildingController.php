<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicBuilding;

use App\Exports\PublicBuildingSurveysExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicBuilding\PublicBuildingFilterRequest;
use App\Models\PublicBuildingFilter;
use App\Models\PublicBuildingSurvey;
use App\Support\XlsFormLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class PublicBuildingController extends Controller
{
    public function index(): View
    {
        $summary = [
            'total_surveys' => PublicBuildingSurvey::query()->count(),
            'total_units' => PublicBuildingSurvey::query()->withCount('units')->get()->sum('units_count'),
            'damaged_buildings' => PublicBuildingSurvey::query()
                ->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '')
                ->count(),
        ];

        $filterGroups = PublicBuildingFilter::query()
            ->orderBy('list_name')
            ->orderBy('label')
            ->get()
            ->groupBy('list_name');

        if ($filterGroups->isEmpty()) {
            $filterGroups = $this->fallbackFilterGroups();
        }

        $filterOptions = [
            'municipalities' => PublicBuildingSurvey::query()->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),
            'neighborhoods' => PublicBuildingSurvey::query()->distinct()->orderBy('neighborhood')->pluck('neighborhood')->filter()->values(),
            'researchers' => PublicBuildingSurvey::query()->distinct()->orderBy('assignedto')->pluck('assignedto')->filter()->values(),
            'min_damage_date' => PublicBuildingSurvey::query()->whereNotNull('date_of_damage')->min('date_of_damage'),
            'max_damage_date' => PublicBuildingSurvey::query()->whereNotNull('date_of_damage')->max('date_of_damage'),
        ];

        return view('PublicBuilding.index', [
            'summary' => $summary,
            'filterOptions' => $filterOptions,
            'filterGroups' => $filterGroups,
        ]);
    }

    public function data(PublicBuildingFilterRequest $request): JsonResponse
    {
        return DataTables::eloquent($this->filteredQuery($request))
            ->addColumn('actions', function (PublicBuildingSurvey $survey): string {
                return '<a href="'.route('public-buildings.show', $survey).'" class="btn btn-light btn-sm">View</a>';
            })
            ->editColumn('date_of_damage', function (PublicBuildingSurvey $survey): string {
                return $survey->date_of_damage?->format('Y-m-d') ?? '-';
            })
            ->editColumn('building_damage_status', function (PublicBuildingSurvey $survey): string {
                return '<span class="badge badge-light-primary">'.e($survey->building_damage_status ?? '-').'</span>';
            })
            ->rawColumns(['actions', 'building_damage_status'])
            ->toJson();
    }

    public function export(PublicBuildingFilterRequest $request, string $format): BinaryFileResponse|Response
    {
        $format = strtolower($format);

        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $surveys = $this->filteredQuery($request)->get();
        $fileBaseName = 'public_buildings_'.now()->format('Ymd_His');

        if ($format === 'pdf') {
            return Pdf::loadView('PublicBuilding.export_pdf', [
                'surveys' => $surveys,
                'filters' => $request->validated(),
            ])->setPaper('a4', 'landscape')->download($fileBaseName.'.pdf');
        }

        return Excel::download(
            new PublicBuildingSurveysExport($surveys),
            $fileBaseName.'.'.$format,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    public function show(PublicBuildingSurvey $publicBuilding): View
    {
        $publicBuilding->load('units');
        $unitLayoutSections = XlsFormLayout::sections($this->xlsFormPath(), 'Unit_Information');

        return view('PublicBuilding.show', [
            'survey' => $publicBuilding,
            'sections' => $this->buildSurveySections($publicBuilding),
            'unitSections' => $publicBuilding->units
                ->sortBy('repeat_index')
                ->values()
                ->flatMap(function ($unit) use ($unitLayoutSections): array {
                    $unitNumber = ($unit->repeat_index ?? 0) + 1;

                    return collect($unitLayoutSections)
                        ->map(fn (array $section): array => [
                            'title' => 'Unit / Floor '.$unitNumber.' - '.$section['title'],
                            'rows' => $this->rowsFromXlsFields($unit, $section['fields']),
                        ])
                        ->filter(fn (array $section): bool => $section['rows'] !== [])
                        ->values()
                        ->all();
                })
                ->all(),
        ]);
    }

    private function buildSurveySections(PublicBuildingSurvey $survey): array
    {
        $sections = collect(XlsFormLayout::sections($this->xlsFormPath()))
            ->map(fn (array $section): array => [
                'title' => $section['title'],
                'rows' => $this->rowsFromXlsFields($survey, $section['fields']),
            ])
            ->filter(fn (array $section): bool => $section['rows'] !== [])
            ->values()
            ->all();

        if ($sections !== []) {
            return $sections;
        }

        return [
            [
                'title' => 'Survey',
                'rows' => $this->rowsFromMap($survey, [
                    'objectid' => 'Object ID',
                    'building_name' => 'Building Name',
                    'assignedto' => 'Researcher',
                    'building_damage_status' => 'Building Damage Status',
                    'comments_recommendations' => 'Comments & Recommendations',
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $fieldMap
     * @return array<int, array{question: string, answer: string}>
     */
    private function rowsFromMap(object $record, array $fieldMap): array
    {
        return collect($fieldMap)
            ->map(function (string $label, string $field) use ($record): ?array {
                $value = $this->formatSurveyValue(data_get($record, $field));

                if ($value === null) {
                    return null;
                }

                return [
                    'question' => $label,
                    'answer' => $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, label: string}>  $fields
     * @return array<int, array{question: string, answer: string}>
     */
    private function rowsFromXlsFields(object $record, array $fields): array
    {
        return collect($fields)
            ->map(function (array $field) use ($record): ?array {
                $formattedValue = $this->formatSurveyValue(XlsFormLayout::value($record, $field['name']));

                if ($formattedValue !== null) {
                    return [
                        'question' => $field['label'],
                        'answer' => $formattedValue,
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function xlsFormPath(): string
    {
        return config(
            'services.survey_forms.public_buildings_xlsx',
            'C:\\Users\\hp\\Downloads\\PUBP01-Damage Assessment of Public Buildings (1).xlsx',
        );
    }

    private function formatSurveyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $items = collect($value)
                    ->flatten()
                    ->map(fn ($item) => is_scalar($item) ? trim((string) $item) : null)
                    ->filter()
                    ->map(fn (string $item) => Str::of($item)->replace('_', ' ')->headline()->toString())
                    ->values();

                return $items->isEmpty() ? null : $items->implode(', ');
            }

            $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $jsonValue === false || $jsonValue === '[]' ? null : $jsonValue;
        }

        if ($value instanceof \JsonSerializable) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? null : $encoded;
        }

        if (is_iterable($value)) {
            $items = collect($value)
                ->flatten()
                ->map(fn ($item) => is_scalar($item) ? trim((string) $item) : null)
                ->filter()
                ->map(fn (string $item) => Str::of($item)->replace('_', ' ')->headline()->toString())
                ->values();

            return $items->isEmpty() ? null : $items->implode(', ');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    protected function filteredQuery(PublicBuildingFilterRequest $request): Builder
    {
        $query = PublicBuildingSurvey::query()->withCount('units');

        if ($request->filled('municipalitie')) {
            $query->where('municipalitie', $request->string('municipalitie')->toString());
        }

        if ($request->filled('neighborhood')) {
            $query->where('neighborhood', $request->string('neighborhood')->toString());
        }

        if ($request->filled('assignedto')) {
            $query->where('assignedto', $request->string('assignedto')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date_of_damage', '>=', $request->date('from_date')->toDateString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('date_of_damage', '<=', $request->date('to_date')->toDateString());
        }

        if ($request->boolean('damaged_only')) {
            $query->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '');
        }

        if ($request->boolean('with_units')) {
            $query->has('units');
        }

        if ($request->boolean('has_municipality')) {
            $query->whereNotNull('municipalitie')
                ->where('municipalitie', '!=', '');
        }

        if ($request->boolean('has_neighborhood')) {
            $query->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '');
        }

        if ($request->boolean('has_assignedto')) {
            $query->whereNotNull('assignedto')
                ->where('assignedto', '!=', '');
        }

        if ($request->boolean('occupied_only')) {
            $query->where('is_building_occupied', 'yes');
        }

        if ($request->boolean('bodies_only')) {
            $query->where('is_bodies', 'yes');
        }

        if ($request->boolean('uxo_only')) {
            $query->where('is_uxo', 'yes');
        }

        $search = trim((string) $request->input('search.value', $request->input('search', '')));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested
                    ->where('building_name', 'like', '%'.$search.'%')
                    ->orWhere('municipalitie', 'like', '%'.$search.'%')
                    ->orWhere('neighborhood', 'like', '%'.$search.'%')
                    ->orWhere('assignedto', 'like', '%'.$search.'%')
                    ->orWhere('objectid', 'like', '%'.$search.'%')
                    ->orWhere('building_damage_status', 'like', '%'.$search.'%');
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
            $filterValue = (string) $value;

            if ($mode === 'exact') {
                $query->where($column, $filterValue);

                continue;
            }

            if ($mode === 'json_like') {
                $query->where($column, 'like', '%"'.$filterValue.'"%');

                continue;
            }

            if ($mode === 'raw_payload_like') {
                $query->where('raw_payload', 'like', '%'.$filterValue.'%');
            }
        }
    }

    /**
     * @return array{column: string, mode: string}|null
     */
    private function resolveDynamicFilter(string $filterName): ?array
    {
        $filterMappings = [
            'security' => ['column' => 'security_situation', 'mode' => 'exact'],
            'locality' => ['column' => 'municipalitie', 'mode' => 'exact'],
            'roof_type' => ['column' => 'building_roof_type', 'mode' => 'json_like'],
            'building_status' => ['column' => 'building_status', 'mode' => 'exact'],
            'beneficiaries_type' => ['column' => 'benef_type', 'mode' => 'json_like'],
            'owning_entity' => ['column' => 'and_ownership', 'mode' => 'exact'],
        ];

        if (array_key_exists($filterName, $filterMappings)) {
            return $filterMappings[$filterName];
        }

        if (! Schema::hasColumn('public_building_surveys', $filterName)) {
            return ['column' => 'raw_payload', 'mode' => 'raw_payload_like'];
        }

        $jsonColumns = [
            'benef_type',
            'building_roof_type',
            'ground_floor_use',
        ];

        if (in_array($filterName, $jsonColumns, true)) {
            return ['column' => $filterName, 'mode' => 'json_like'];
        }

        return ['column' => $filterName, 'mode' => 'exact'];
    }

    private function fallbackFilterGroups(): Collection
    {
        return collect([
            'security' => collect([
                (object) ['name' => 'Safe', 'label' => 'Safe'],
                (object) ['name' => 'Unsafe', 'label' => 'Unsafe'],
            ]),
            'weather' => collect([
                (object) ['name' => 'fine', 'label' => 'Fine'],
                (object) ['name' => 'windy', 'label' => 'Windy'],
                (object) ['name' => 'rainy', 'label' => 'Rainy'],
            ]),
            'building_damage_status' => collect([
                (object) ['name' => 'fully_damaged', 'label' => 'Totally Damaged'],
                (object) ['name' => 'partially_damaged', 'label' => 'Partially Damaged'],
                (object) ['name' => 'committee_review', 'label' => 'Committee Review'],
            ]),
            'building_type' => collect([
                (object) ['name' => 'stan_alone_building', 'label' => 'Stand alone building'],
                (object) ['name' => 'apartment', 'label' => 'Apartment'],
                (object) ['name' => 'other', 'label' => 'Other'],
            ]),
            'building_age' => collect([
                (object) ['name' => 'years0_5', 'label' => '0-5 years'],
                (object) ['name' => 'years21_50', 'label' => '21-50 years'],
                (object) ['name' => 'not_sure', 'label' => 'Not sure'],
            ]),
            'sector' => collect([
                (object) ['name' => 'health', 'label' => 'Health'],
                (object) ['name' => 'education', 'label' => 'Education'],
                (object) ['name' => 'governmental_municipal', 'label' => 'Governmental/Municipal'],
            ]),
            'facility_type' => collect([
                (object) ['name' => 'hospital', 'label' => 'Hospital'],
                (object) ['name' => 'university', 'label' => 'University'],
                (object) ['name' => 'municipality', 'label' => 'Municipality'],
            ]),
            'building_use' => collect([
                (object) ['name' => 'residential', 'label' => 'Residential'],
                (object) ['name' => 'work', 'label' => 'Work'],
                (object) ['name' => 'combined', 'label' => 'Combined'],
            ]),
            'building_status' => collect([
                (object) ['name' => 'dangerous', 'label' => 'Dangerous'],
                (object) ['name' => 'rubble', 'label' => 'Rubble'],
                (object) ['name' => 'removed', 'label' => 'Removed'],
            ]),
            'roof_type' => collect([
                (object) ['name' => 'clay_tile', 'label' => 'Clay Tile'],
                (object) ['name' => 'concrete', 'label' => 'Concrete'],
                (object) ['name' => 'asbestos', 'label' => 'Asbestos'],
            ]),
        ]);
    }
}
