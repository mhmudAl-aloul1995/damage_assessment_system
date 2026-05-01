<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicBuilding;

use App\Exports\PublicBuildingSurveysExport;
use App\Http\Controllers\Controller;
use App\Models\PublicBuildingFilter;
use App\Models\PublicBuildingSurvey;
use App\Support\Forms\PublicBuildingSurveyLayout;
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

class PublicBuildingController extends Controller
{
    // ================= INDEX =================

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

        $databaseFilterGroups = PublicBuildingFilter::query()
            ->orderBy('list_name')
            ->orderBy('label')
            ->get()
            ->groupBy('list_name');

        $filterGroups = $databaseFilterGroups->isNotEmpty()
            ? $databaseFilterGroups
            : $this->fallbackFilterGroups();

        $assignedCol = $this->researcherColumn();

        $filterOptions = [
            'municipalities' => PublicBuildingSurvey::query()
                ->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),

            'neighborhoods' => PublicBuildingSurvey::query()
                ->distinct()->orderBy('neighborhood')->pluck('neighborhood')->filter()->values(),

            'researchers' => $assignedCol
                ? PublicBuildingSurvey::query()->distinct()->orderBy($assignedCol)->pluck($assignedCol)->filter()->values()
                : collect(),

            'min_damage_date' => optional(
                PublicBuildingSurvey::query()->whereNotNull('date_of_damage')->min('date_of_damage')
            )?->format('Y-m-d'),

            'max_damage_date' => optional(
                PublicBuildingSurvey::query()->whereNotNull('date_of_damage')->max('date_of_damage')
            )?->format('Y-m-d'),
        ];

        return view('PublicBuilding.index', compact('summary', 'filterOptions', 'filterGroups'));
    }

    // ================= DATATABLE =================

    public function data(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->filteredQuery($request))
            ->addColumn(
                'actions',
                fn ($survey) => '<a href="'.route('public-buildings.show', $survey).'" class="btn btn-light btn-sm">View</a>'
            )
            ->addColumn(
                'assigned_to',
                fn ($survey) => $survey->{$this->researcherColumn() ?? 'assigned_to'} ?? '-'
            )
            ->editColumn(
                'date_of_damage',
                fn (PublicBuildingSurvey $survey): string => $survey->date_of_damage?->format('Y-m-d') ?? '-'
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function export(Request $request, string $format): BinaryFileResponse|Response
    {
        $format = strtolower($format);

        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $surveys = $this->filteredQuery($request)->get();
        $fileBaseName = 'public_buildings_'.now()->format('Ymd_His');

        if ($format === 'pdf') {
            return Pdf::loadView('PublicBuilding.export_pdf', [
                'surveys' => $surveys,
                'filters' => $request->all(),
            ])->setPaper('a4', 'landscape')->download($fileBaseName.'.pdf');
        }

        return Excel::download(
            new PublicBuildingSurveysExport($surveys),
            $fileBaseName.'.'.$format,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    // ================= SHOW =================

    public function show(PublicBuildingSurvey $publicBuilding): View
    {
        $publicBuilding->load(['units' => fn ($q) => $q->orderBy('objectid')]);

        return view('PublicBuilding.show', [
            'survey' => $publicBuilding,
            'sections' => $this->buildSurveySections($publicBuilding),
            'unitSections' => $this->buildUnitSections($publicBuilding),
        ]);
    }

    // ================= STATIC SECTIONS =================

    private function buildSurveySections(PublicBuildingSurvey $survey): array
    {
        $repeatSectionNames = PublicBuildingSurveyLayout::repeatSectionNames('Unit_Information');

        return collect(PublicBuildingSurveyLayout::sections())
            ->reject(fn (array $section): bool => ($section['type'] ?? 'group') === 'repeat')
            ->reject(fn (array $section): bool => in_array($section['name'] ?? '', $repeatSectionNames, true))
            ->map(fn (array $section): array => [
                'title' => $this->sectionTitle($section),
                'name' => $section['name'],
                'rows' => $this->rowsFromLayoutFields($survey, $section['fields']),
            ])
            ->values()
            ->all();
    }

    private function buildUnitSections(PublicBuildingSurvey $survey): array
    {
        $unitSections = PublicBuildingSurveyLayout::repeatSections('Unit_Information');

        return $survey->units
            ->values()
            ->flatMap(function ($unit, int $i) use ($unitSections): array {
                return collect($unitSections)
                    ->map(fn (array $section): array => [
                        'title' => 'Unit / Floor '.($i + 1).' - '.$this->sectionTitle($section),
                        'name' => $section['name'],
                        'rows' => $this->rowsFromLayoutFields($unit, $section['fields']),
                    ])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    // ================= HELPERS =================

    /**
     * @param  array<int, array{name: string, type: string, label: string, hint: ?string, list_name: ?string}>  $fields
     * @return array<int, array{question: string, answer: string, empty: bool}>
     */
    private function rowsFromLayoutFields(object $record, array $fields): array
    {
        return collect($fields)
            ->reject(fn (array $field): bool => ($field['type'] ?? null) === 'calculate')
            ->map(function (array $field) use ($record): array {
                $value = PublicBuildingSurveyLayout::value($record, $field['name']);
                $answer = PublicBuildingSurveyLayout::displayValue($value, $field);
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

    private function researcherColumn(): ?string
    {
        if (Schema::hasColumn('public_building_surveys', 'assigned_to')) {
            return 'assigned_to';
        }

        if (Schema::hasColumn('public_building_surveys', 'assignedto')) {
            return 'assignedto';
        }

        return null;
    }

    protected function filteredQuery(Request $request): Builder
    {
        $query = PublicBuildingSurvey::query()->withCount('units');

        if ($request->filled('municipalitie')) {
            $query->where('municipalitie', $request->string('municipalitie')->toString());
        }

        if ($request->filled('neighborhood')) {
            $query->where('neighborhood', $request->string('neighborhood')->toString());
        }

        $researcherColumn = $this->researcherColumn();

        if ($request->filled('assigned_to') && $researcherColumn) {
            $query->where($researcherColumn, $request->string('assigned_to')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date_of_damage', '>=', $request->date('from_date')->toDateString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('date_of_damage', '<=', $request->date('to_date')->toDateString());
        }

        $this->applyDynamicFilters($query, collect($request->input('filters', [])));

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

            if ($mode === 'json_like') {
                $query->where($column, 'like', '%"'.$filterValue.'"%');

                continue;
            }

            if ($mode === 'raw_payload_like') {
                $query->where('raw_payload', 'like', '%'.$filterValue.'%');

                continue;
            }

            $query->where($column, $filterValue);
        }
    }

    /**
     * @return array{column: string, mode: string}|null
     */
    private function resolveDynamicFilter(string $filterName): ?array
    {
        $filterMappings = [
            'security' => ['column' => 'security_situation', 'mode' => 'exact'],
            'roof_type' => ['column' => 'building_roof_type', 'mode' => 'json_like'],
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
            'building_damage_status' => collect([
                (object) ['name' => 'fully_damaged', 'label' => 'Fully Damaged'],
                (object) ['name' => 'partially_damaged', 'label' => 'Partially Damaged'],
            ]),
        ]);
    }
}
