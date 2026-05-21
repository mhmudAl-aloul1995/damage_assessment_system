<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Exports\BuildingProductivityExport;
use App\Http\Controllers\Controller;
use App\Models\Building;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BuildingProductivityReportController extends Controller
{
    /**
     * Change this constant if the report date should use another buildings column.
     */
    private const DATE_FIELD = 'editdate';

    /**
     * Values treated as completed in buildings.field_status.
     *
     * @var list<string>
     */
    private const COMPLETED_STATUSES = [
        'completed',
        'complete',
        'done',
    ];

    public function __construct()
    {
        $this->middleware('role:Database Officer|Project Officer|undp-Project Manager|Auditing Supervisor|Area Manager|Team Leader|Team Leader -INF');
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $report = $this->buildReport($filters);

        return view('damage-assessment::reports.building_productivity', [
            ...$report,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'dateField' => self::DATE_FIELD,
            'completedStatuses' => self::COMPLETED_STATUSES,
            'exportRoute' => route('reports.building-productivity.export', $request->query()),
            'reportRoute' => route('reports.building-productivity.index'),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $report = $this->buildReport($filters);

        return Excel::download(
            new BuildingProductivityExport(
                rows: $report['rows'],
                grandTotal: $report['grandTotal'],
                filters: $filters,
                dateField: self::DATE_FIELD,
                completedStatuses: self::COMPLETED_STATUSES,
            ),
            'building_productivity_report.xlsx',
        );
    }

    /**
     * @return array{from_date: string|null, to_date: string|null, gov: string|null, neighborhood: string|null}
     */
    private function filters(Request $request): array
    {
        $fromDate = $request->filled('from_date')
            ? $request->date('from_date')?->toDateString()
            : null;

        $toDate = $request->filled('to_date')
            ? $request->date('to_date')?->toDateString()
            : null;

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'gov' => $request->filled('gov') ? trim((string) $request->input('gov')) : null,
            'neighborhood' => $request->filled('neighborhood') ? trim((string) $request->input('neighborhood')) : null,
        ];
    }

    /**
     * @param  array{from_date: string|null, to_date: string|null, gov: string|null, neighborhood: string|null}  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, grandTotal: array<string, mixed>, summary: array<string, mixed>, charts: array<string, mixed>}
     */
    private function buildReport(array $filters): array
    {
        $baseRows = $this->baseReportQuery($filters)
            ->orderBy('gov')
            ->orderBy('name')
            ->get()
            ->map(fn ($row): array => $this->formatRow(
                gov: (string) ($row->gov ?: 'Not Available'),
                name: (string) ($row->name ?: 'Not Available'),
                completed: (int) $row->completed,
                notCompleted: (int) $row->not_completed,
                rowType: 'detail',
            ));

        $rows = collect();

        foreach ($baseRows->groupBy('gov') as $gov => $groupRows) {
            foreach ($groupRows as $row) {
                $rows->push($row);
            }

            $rows->push($this->formatRow(
                gov: (string) $gov,
                name: 'Total',
                completed: (int) $groupRows->sum('completed'),
                notCompleted: (int) $groupRows->sum('not_completed'),
                rowType: 'gov_total',
            ));
        }

        $grandTotal = $this->formatRow(
            gov: 'Grand Total',
            name: '',
            completed: (int) $baseRows->sum('completed'),
            notCompleted: (int) $baseRows->sum('not_completed'),
            rowType: 'grand_total',
        );

        return [
            'rows' => $rows,
            'grandTotal' => $grandTotal,
            'summary' => [
                'completed' => $grandTotal['completed'],
                'not_completed' => $grandTotal['not_completed'],
                'buildings_count' => $grandTotal['buildings_count'],
                'completed_percent' => $grandTotal['completed_percent'],
                'not_completed_percent' => $grandTotal['not_completed_percent'],
                'areas_count' => $baseRows->pluck('gov')->unique()->count(),
                'neighborhoods_count' => $baseRows->count(),
            ],
            'charts' => $this->chartData($baseRows, $grandTotal, $filters),
        ];
    }

    /**
     * @param  array{from_date: string|null, to_date: string|null, gov: string|null, neighborhood: string|null}  $filters
     */
    private function baseReportQuery(array $filters): Builder
    {
        $completedStatuses = collect(self::COMPLETED_STATUSES)
            ->map(fn (string $status): string => strtolower(trim($status)))
            ->values()
            ->all();

        $query = Building::query()
            ->selectRaw("COALESCE(NULLIF(TRIM(governorate), ''), NULLIF(TRIM(municipalitie), ''), 'Not Available') as gov")
            ->selectRaw("COALESCE(NULLIF(TRIM(neighborhood), ''), 'Not Available') as name")
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(field_status, ''))) IN (".
                implode(',', array_fill(0, count($completedStatuses), '?')).
                ') THEN 1 ELSE 0 END) as completed',
                $completedStatuses,
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(field_status, ''))) IN (".
                implode(',', array_fill(0, count($completedStatuses), '?')).
                ') THEN 0 ELSE 1 END) as not_completed',
                $completedStatuses,
            )
            ->groupBy('gov', 'name');

        if ($filters['from_date']) {
            $query->whereDate(self::DATE_FIELD, '>=', $filters['from_date']);
        }

        if ($filters['to_date']) {
            $query->whereDate(self::DATE_FIELD, '<=', $filters['to_date']);
        }

        if ($filters['gov']) {
            $query->where(function (Builder $nested) use ($filters): void {
                $nested
                    ->where('governorate', $filters['gov'])
                    ->orWhere('municipalitie', $filters['gov']);
            });
        }

        if ($filters['neighborhood']) {
            $query->where('neighborhood', $filters['neighborhood']);
        }

        return $query;
    }

    /**
     * @param  array{from_date: string|null, to_date: string|null, gov: string|null, neighborhood: string|null}  $filters
     */
    private function locationReportQuery(array $filters): Builder
    {
        $completedStatuses = collect(self::COMPLETED_STATUSES)
            ->map(fn (string $status): string => strtolower(trim($status)))
            ->values()
            ->all();

        $query = Building::query()
            ->selectRaw("COALESCE(NULLIF(TRIM(municipalitie), ''), 'Not Available') as municipality_name")
            ->selectRaw("COALESCE(NULLIF(TRIM(neighborhood), ''), 'Not Available') as neighborhood_name")
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(field_status, ''))) IN (".
                implode(',', array_fill(0, count($completedStatuses), '?')).
                ') THEN 1 ELSE 0 END) as completed',
                $completedStatuses,
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(field_status, ''))) IN (".
                implode(',', array_fill(0, count($completedStatuses), '?')).
                ') THEN 0 ELSE 1 END) as not_completed',
                $completedStatuses,
            )
            ->groupBy('municipality_name', 'neighborhood_name');

        if ($filters['from_date']) {
            $query->whereDate(self::DATE_FIELD, '>=', $filters['from_date']);
        }

        if ($filters['to_date']) {
            $query->whereDate(self::DATE_FIELD, '<=', $filters['to_date']);
        }

        if ($filters['gov']) {
            $query->where(function (Builder $nested) use ($filters): void {
                $nested
                    ->where('governorate', $filters['gov'])
                    ->orWhere('municipalitie', $filters['gov']);
            });
        }

        if ($filters['neighborhood']) {
            $query->where('neighborhood', $filters['neighborhood']);
        }

        return $query;
    }

    private function formatRow(string $gov, string $name, int $completed, int $notCompleted, string $rowType): array
    {
        $buildingsCount = $completed + $notCompleted;

        return [
            'row_type' => $rowType,
            'gov' => $gov,
            'name' => $name,
            'completed' => $completed,
            'not_completed' => $notCompleted,
            'buildings_count' => $buildingsCount,
            'completed_percent' => $buildingsCount > 0 ? $completed / $buildingsCount : 0,
            'not_completed_percent' => $buildingsCount > 0 ? $notCompleted / $buildingsCount : 0,
        ];
    }

    /**
     * @return array{governorates: Collection<int, string>, neighborhoods: Collection<int, string>}
     */
    private function filterOptions(): array
    {
        return [
            'governorates' => Building::query()
                ->selectRaw("DISTINCT COALESCE(NULLIF(TRIM(governorate), ''), NULLIF(TRIM(municipalitie), '')) as gov")
                ->whereRaw("COALESCE(NULLIF(TRIM(governorate), ''), NULLIF(TRIM(municipalitie), '')) IS NOT NULL")
                ->orderBy('gov')
                ->pluck('gov')
                ->filter()
                ->values(),
            'neighborhoods' => Building::query()
                ->distinct()
                ->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '')
                ->orderBy('neighborhood')
                ->pluck('neighborhood')
                ->values(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baseRows
     * @param  array<string, mixed>  $grandTotal
     * @return array<string, mixed>
     */
    private function chartData(Collection $baseRows, array $grandTotal, array $filters): array
    {
        $govRows = $baseRows
            ->groupBy('gov')
            ->map(fn (Collection $rows, string $gov): array => $this->formatRow(
                gov: $gov,
                name: 'Total',
                completed: (int) $rows->sum('completed'),
                notCompleted: (int) $rows->sum('not_completed'),
                rowType: 'gov_total',
            ))
            ->sortByDesc('buildings_count')
            ->values();

        $topNeighborhoods = $baseRows
            ->sortByDesc('buildings_count')
            ->take(12)
            ->values();

        $allNeighborhoods = $baseRows
            ->sortBy([
                ['gov', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        $locationRows = $this->locationReportQuery($filters)
            ->orderBy('municipality_name')
            ->orderBy('neighborhood_name')
            ->get()
            ->map(fn ($row): array => [
                'municipality' => (string) ($row->municipality_name ?: 'Not Available'),
                'neighborhood' => (string) ($row->neighborhood_name ?: 'Not Available'),
                'completed' => (int) $row->completed,
                'not_completed' => (int) $row->not_completed,
            ]);

        return [
            'completion_donut' => [
                'labels' => ['Completed', 'Not Completed'],
                'series' => [
                    (int) $grandTotal['completed'],
                    (int) $grandTotal['not_completed'],
                ],
                'colors' => ['#50CD89', '#F1416C'],
            ],
            'gov_bar' => [
                'labels' => $govRows->pluck('gov')->all(),
                'completed' => $govRows->pluck('completed')->map(fn ($value): int => (int) $value)->all(),
                'not_completed' => $govRows->pluck('not_completed')->map(fn ($value): int => (int) $value)->all(),
            ],
            'neighborhood_percent' => [
                'labels' => $topNeighborhoods
                    ->map(fn (array $row): string => $row['gov'].' / '.$row['name'])
                    ->all(),
                'series' => $topNeighborhoods
                    ->pluck('completed_percent')
                    ->map(fn ($value): float => round(((float) $value) * 100, 2))
                    ->all(),
            ],
            'all_neighborhoods' => [
                'labels' => $allNeighborhoods
                    ->map(fn (array $row): string => $row['gov'].' / '.$row['name'])
                    ->all(),
                'completed' => $allNeighborhoods->pluck('completed')->map(fn ($value): int => (int) $value)->all(),
                'not_completed' => $allNeighborhoods->pluck('not_completed')->map(fn ($value): int => (int) $value)->all(),
                'height' => max(360, min(1200, $allNeighborhoods->count() * 42)),
            ],
            'location_pies' => $this->locationPieTree($locationRows),
        ];
    }

    /**
     * @param  Collection<int, array{municipality: string, neighborhood: string, completed: int, not_completed: int}>  $locationRows
     * @return array<int, array<string, mixed>>
     */
    private function locationPieTree(Collection $locationRows): array
    {
        return $locationRows
            ->groupBy('municipality')
            ->map(function (Collection $municipalityRows, string $municipality): array {
                return [
                    'pie' => $this->locationPie(
                        level: 'municipality',
                        title: $municipality,
                        subtitle: 'Municipality',
                        completed: (int) $municipalityRows->sum('completed'),
                        notCompleted: (int) $municipalityRows->sum('not_completed'),
                        idParts: [$municipality],
                    ),
                    'neighborhoods' => $municipalityRows
                        ->groupBy('neighborhood')
                        ->map(function (Collection $neighborhoodRows, string $neighborhood) use ($municipality): array {
                            return $this->locationPie(
                                level: 'neighborhood',
                                title: $neighborhood,
                                subtitle: $municipality,
                                completed: (int) $neighborhoodRows->sum('completed'),
                                notCompleted: (int) $neighborhoodRows->sum('not_completed'),
                                idParts: [$municipality, $neighborhood],
                            );
                        })
                        ->sortBy('title')
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $idParts
     * @return array<string, mixed>
     */
    private function locationPie(string $level, string $title, string $subtitle, int $completed, int $notCompleted, array $idParts): array
    {
        $buildingsCount = $completed + $notCompleted;

        return [
            'id' => 'location_pie_'.md5($level.'|'.implode('|', $idParts)),
            'level' => $level,
            'title' => $title,
            'subtitle' => $subtitle,
            'labels' => ['Completed', 'Not Completed'],
            'series' => [$completed, $notCompleted],
            'buildings_count' => $buildingsCount,
            'completed_percent' => $buildingsCount > 0 ? round(($completed / $buildingsCount) * 100) : 0,
            'not_completed_percent' => $buildingsCount > 0 ? round(($notCompleted / $buildingsCount) * 100) : 0,
        ];
    }
}
