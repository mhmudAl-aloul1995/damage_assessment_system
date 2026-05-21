<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Services\Reports;

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AreaProductivityReportService
{
    public const TYPE_HOUSING_UNITS = 'housing_units';

    public const TYPE_BUILDINGS = 'buildings';

    public const TYPE_PUBLIC_BUILDINGS = 'public_buildings';

    public const TYPE_ROAD_FACILITIES = 'road_facilities';

    public function build(string $type, array $filters): array
    {
        $definition = $this->definition($type);
        $dateRange = $this->resolveDateRange($filters);
        $rows = $this->groupedQuery($type, $filters, $dateRange['from'], $dateRange['to'])->get();

        return [
            'type' => $type,
            'title_key' => $definition['title_key'],
            'subtitle_key' => $definition['subtitle_key'],
            'route_name' => $definition['route_name'],
            'export_route_name' => $definition['export_route_name'],
            'sector_key' => $definition['sector_key'],
            'start_date' => $dateRange['from']?->toDateString() ?? '',
            'end_date' => $dateRange['to']?->toDateString() ?? '',
            'date_range_label' => $dateRange['from'] && $dateRange['to']
                ? $dateRange['from']->format('m/d/Y').' - '.$dateRange['to']->format('m/d/Y')
                : 'All',
            'rows' => $rows,
            'filters' => [
                'governorate' => (string) ($filters['governorate'] ?? ''),
                'municipalitie' => (string) ($filters['municipalitie'] ?? ''),
                'neighborhood' => (string) ($filters['neighborhood'] ?? ''),
                'zone_code' => (string) ($filters['zone_code'] ?? ''),
                'assignedto' => (string) ($filters['assignedto'] ?? ''),
            ],
            'filter_options' => $this->filterOptions($type),
            'charts' => [
                'location_pies' => $this->supportsLocationPieCharts($type)
                    ? $this->buildLocationPieCharts($rows, $type)
                    : [],
            ],
            'summary' => [
                'grouped_areas' => $rows->count(),
                'engineers' => (int) $rows->sum('no_eng'),
                'tda' => (int) $rows->sum('tda_range'),
                'pda' => (int) $rows->sum('pda_range'),
                'cra' => (int) $rows->sum('cra_range'),
                'destroyed' => (int) $rows->sum('destroyed_count'),
                'severe' => (int) $rows->sum('severe_count'),
                'moderate' => (int) $rows->sum('moderate_count'),
                'minor' => (int) $rows->sum('minor_count'),
                'no_damage' => (int) $rows->sum('no_damage_count'),
                'total_records' => (int) $rows->sum('total_count'),
                'housing_units_count' => (int) $rows->sum('housing_units_count'),
            ],
        ];
    }

    public function filterOptions(string $type): array
    {
        return match ($type) {
            self::TYPE_HOUSING_UNITS, self::TYPE_BUILDINGS => $this->buildingBackedFilterOptions(),
            self::TYPE_PUBLIC_BUILDINGS => $this->surveyFilterOptions(PublicBuildingSurvey::query(), false),
            self::TYPE_ROAD_FACILITIES => $this->surveyFilterOptions(RoadFacilitySurvey::query(), true),
            default => throw new InvalidArgumentException("Unsupported area productivity report type [{$type}]."),
        };
    }

    /**
     * @return Collection<int, object>
     */
    public function exportRows(string $type, array $filters): Collection
    {
        $definition = $this->definition($type);
        $dateRange = $this->resolveDateRange($filters);

        return $this->groupedQuery($type, $filters, $dateRange['from'], $dateRange['to'])
            ->get()
            ->map(function (object $row) use ($definition) {
                $row->Sector = __($definition['sector_key']);

                return $row;
            });
    }

    private function groupedQuery(string $type, array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        return match ($type) {
            self::TYPE_HOUSING_UNITS => $this->housingUnitsQuery($filters, $fromDate, $toDate),
            self::TYPE_BUILDINGS => $this->buildingsQuery($filters, $fromDate, $toDate),
            self::TYPE_PUBLIC_BUILDINGS => $this->publicBuildingsQuery($filters, $fromDate, $toDate),
            self::TYPE_ROAD_FACILITIES => $this->roadFacilitiesQuery($filters, $fromDate, $toDate),
            default => throw new InvalidArgumentException("Unsupported area productivity report type [{$type}]."),
        };
    }

    private function housingUnitsQuery(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('buildings.neighborhood');

        $query = HousingUnit::query()
            ->join('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->selectRaw("
                {$this->preferredValueExpression('buildings.governorate')} as governorate,
                {$this->preferredValueExpression('buildings.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('buildings.neighborhood')} as neighborhood,
                COUNT(DISTINCT buildings.assignedto) as no_eng,
                SUM(CASE WHEN housing_units.unit_damage_status = 'fully_damaged2' THEN 1 ELSE 0 END) as tda_range,
                SUM(CASE WHEN housing_units.unit_damage_status = 'partially_damaged2' THEN 1 ELSE 0 END) as pda_range,
                SUM(CASE WHEN housing_units.unit_damage_status IN ('committee_review2', 'committee_review', 'commite_review', 'commitee_review2', 'commitee_review') THEN 1 ELSE 0 END) as cra_range,
                SUM(CASE
                    WHEN housing_units.unit_damage_status IN (
                        'fully_damaged2',
                        'partially_damaged2',
                        'committee_review2',
                        'committee_review',
                        'commite_review',
                        'commitee_review2',
                        'commitee_review'
                    ) THEN 1
                    ELSE 0
                END) as total_count
            ")
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        $this->applyFilters($query, $filters, [
            'governorate' => 'buildings.governorate',
            'municipalitie' => 'buildings.municipalitie',
            'neighborhood' => 'buildings.neighborhood',
            'zone_code' => 'buildings.zone_code',
            'assignedto' => 'buildings.assignedto',
        ], 'housing_units.creationdate', $fromDate, $toDate);

        return $query;
    }

    private function buildingsQuery(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('buildings.neighborhood');

        $query = Building::query()
            ->selectRaw("
                {$this->preferredValueExpression('buildings.governorate')} as governorate,
                {$this->preferredValueExpression('buildings.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('buildings.neighborhood')} as neighborhood,
                COUNT(DISTINCT buildings.assignedto) as no_eng,
                SUM(CASE WHEN buildings.building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END) as tda_range,
                SUM(CASE WHEN buildings.building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END) as pda_range,
                SUM(CASE WHEN buildings.building_damage_status IN ('committee_review', 'commite_review', 'commitee_review', 'committee_review2', 'commitee_review2') THEN 1 ELSE 0 END) as cra_range,
                SUM(CASE
                    WHEN buildings.building_damage_status IN (
                        'fully_damaged',
                        'partially_damaged',
                        'committee_review',
                        'commite_review',
                        'commitee_review',
                        'committee_review2',
                        'commitee_review2'
                    ) THEN 1
                    ELSE 0
                END) as total_count,
                SUM(CASE
                    WHEN buildings.building_damage_status IN (
                        'fully_damaged',
                        'partially_damaged',
                        'committee_review',
                        'commite_review',
                        'commitee_review',
                        'committee_review2',
                        'commitee_review2'
                    ) THEN (
                        SELECT COUNT(*)
                        FROM housing_units
                        WHERE housing_units.parentglobalid = buildings.globalid
                    )
                    ELSE 0
                END) as housing_units_count
            ")
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        $this->applyFilters($query, $filters, [
            'governorate' => 'buildings.governorate',
            'municipalitie' => 'buildings.municipalitie',
            'neighborhood' => 'buildings.neighborhood',
            'zone_code' => 'buildings.zone_code',
            'assignedto' => 'buildings.assignedto',
        ], 'buildings.creationdate', $fromDate, $toDate);

        return $query;
    }

    private function publicBuildingsQuery(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('public_building_surveys.neighborhood');
        $assignedExpression = $this->assignedValueExpression('public_building_surveys');

        $query = PublicBuildingSurvey::query()
            ->selectRaw("
                {$this->preferredValueExpression('public_building_surveys.governorate')} as governorate,
                {$this->preferredValueExpression('public_building_surveys.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('public_building_surveys.neighborhood')} as neighborhood,
                COUNT(DISTINCT NULLIF({$assignedExpression}, '')) as no_eng,
                SUM(CASE WHEN public_building_surveys.building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END) as tda_range,
                SUM(CASE WHEN public_building_surveys.building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END) as pda_range,
                SUM(CASE WHEN public_building_surveys.building_damage_status IN ('committee_review', 'commite_review', 'commitee_review', 'committee_review2', 'commitee_review2') THEN 1 ELSE 0 END) as cra_range,
                COUNT(public_building_surveys.id) as total_count
            ")
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        $this->applyFilters($query, $filters, [
            'governorate' => 'public_building_surveys.governorate',
            'municipalitie' => 'public_building_surveys.municipalitie',
            'neighborhood' => 'public_building_surveys.neighborhood',
            'assignedto' => $assignedExpression,
        ], $this->dateColumn('public_building_surveys'), $fromDate, $toDate);

        return $query;
    }

    private function roadFacilitiesQuery(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('road_facility_surveys.neighborhood');
        $assignedExpression = $this->assignedValueExpression('road_facility_surveys');

        $query = RoadFacilitySurvey::query()
            ->selectRaw("
                {$this->preferredValueExpression('road_facility_surveys.governorate')} as governorate,
                {$this->preferredValueExpression('road_facility_surveys.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('road_facility_surveys.neighborhood')} as neighborhood,
                COUNT(DISTINCT NULLIF({$assignedExpression}, '')) as no_eng,
                SUM(CASE WHEN road_facility_surveys.road_damage_level = 'destroyed' THEN 1 ELSE 0 END) as destroyed_count,
                SUM(CASE WHEN road_facility_surveys.road_damage_level = 'severe' THEN 1 ELSE 0 END) as severe_count,
                SUM(CASE WHEN road_facility_surveys.road_damage_level = 'moderate' THEN 1 ELSE 0 END) as moderate_count,
                SUM(CASE WHEN road_facility_surveys.road_damage_level = 'minor' THEN 1 ELSE 0 END) as minor_count,
                SUM(CASE WHEN road_facility_surveys.road_damage_level IN ('No_Damage', 'no_damage') THEN 1 ELSE 0 END) as no_damage_count,
                SUM(CASE
                    WHEN road_facility_surveys.road_damage_level IN ('destroyed', 'severe', 'moderate', 'minor', 'No_Damage', 'no_damage')
                    THEN 1 ELSE 0
                END) as total_count
            ")
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        $this->applyFilters($query, $filters, [
            'governorate' => 'road_facility_surveys.governorate',
            'municipalitie' => 'road_facility_surveys.municipalitie',
            'neighborhood' => 'road_facility_surveys.neighborhood',
            'zone_code' => 'road_facility_surveys.zone_code',
            'assignedto' => $assignedExpression,
        ], $this->dateColumn('road_facility_surveys'), $fromDate, $toDate);

        return $query;
    }

    private function applyFilters(
        Builder $query,
        array $filters,
        array $columnMap,
        string $dateColumn,
        ?Carbon $fromDate,
        ?Carbon $toDate,
    ): void {
        foreach ($columnMap as $filterKey => $column) {
            if (filled($filters[$filterKey] ?? null)) {
                if (str_contains($column, '(')) {
                    $query->whereRaw("{$column} = ?", [(string) $filters[$filterKey]]);
                } else {
                    $query->where($column, (string) $filters[$filterKey]);
                }
            }
        }

        if ($fromDate && $toDate) {
            $query->whereBetween($dateColumn, [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()]);
        }
    }

    private function resolveDateRange(array $filters): array
    {
        $rawStartDate = $filters['start_date'] ?? $filters['from_date'] ?? null;
        $rawEndDate = $filters['end_date'] ?? $filters['to_date'] ?? null;

        $fromDate = filled($rawStartDate)
            ? Carbon::parse((string) $rawStartDate)->startOfDay()
            : null;

        $toDate = filled($rawEndDate)
            ? Carbon::parse((string) $rawEndDate)->endOfDay()
            : null;

        if ($fromDate && $toDate && $toDate->lt($fromDate)) {
            $toDate = $fromDate->copy()->endOfDay();
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    private function buildingBackedFilterOptions(): array
    {
        return [
            'governorates' => Building::query()->orderBy('governorate')->pluck('governorate')->filter()->unique()->values(),
            'municipalities' => Building::query()->orderBy('municipalitie')->pluck('municipalitie')->filter()->unique()->values(),
            'neighborhoods' => Building::query()->orderBy('neighborhood')->pluck('neighborhood')->filter()->unique()->values(),
            'zone_codes' => Building::query()->orderBy('zone_code')->pluck('zone_code')->filter()->unique()->values(),
            'assignedto' => Building::query()->orderBy('assignedto')->pluck('assignedto')->filter()->unique()->values(),
        ];
    }

    private function surveyFilterOptions(Builder $query, bool $withZoneCode): array
    {
        $table = $query->getModel()->getTable();
        $assignedExpression = $this->assignedValueExpression($table);

        $governorates = (clone $query)->orderBy('governorate')->pluck('governorate')->filter()->unique()->values();
        $municipalities = (clone $query)->orderBy('municipalitie')->pluck('municipalitie')->filter()->unique()->values();
        $neighborhoods = (clone $query)->orderBy('neighborhood')->pluck('neighborhood')->filter()->unique()->values();
        $assignedto = (clone $query)
            ->selectRaw("DISTINCT {$assignedExpression} as assignedto_value")
            ->orderBy('assignedto_value')
            ->pluck('assignedto_value')
            ->filter()
            ->unique()
            ->values();
        $zoneCodes = $withZoneCode
            ? (clone $query)->orderBy('zone_code')->pluck('zone_code')->filter()->unique()->values()
            : collect();

        return [
            'governorates' => $governorates,
            'municipalities' => $municipalities,
            'neighborhoods' => $neighborhoods,
            'zone_codes' => $zoneCodes,
            'assignedto' => $assignedto,
        ];
    }

    private function definition(string $type): array
    {
        return match ($type) {
            self::TYPE_HOUSING_UNITS => [
                'title_key' => 'multilingual.area_productivity_reports.titles.housing_units',
                'subtitle_key' => 'multilingual.area_productivity_reports.subtitles.housing_units',
                'route_name' => 'reports.area-productivity.housing-units',
                'export_route_name' => 'reports.area-productivity.export.housing-units',
                'sector_key' => 'multilingual.area_productivity_reports.sectors.housing_units',
            ],
            self::TYPE_BUILDINGS => [
                'title_key' => 'multilingual.area_productivity_reports.titles.buildings',
                'subtitle_key' => 'multilingual.area_productivity_reports.subtitles.buildings',
                'route_name' => 'reports.area-productivity.buildings',
                'export_route_name' => 'reports.area-productivity.export.buildings',
                'sector_key' => 'multilingual.area_productivity_reports.sectors.buildings',
            ],
            self::TYPE_PUBLIC_BUILDINGS => [
                'title_key' => 'multilingual.area_productivity_reports.titles.public_buildings',
                'subtitle_key' => 'multilingual.area_productivity_reports.subtitles.public_buildings',
                'route_name' => 'reports.area-productivity.public-buildings',
                'export_route_name' => 'reports.area-productivity.export.public-buildings',
                'sector_key' => 'multilingual.area_productivity_reports.sectors.public_buildings',
            ],
            self::TYPE_ROAD_FACILITIES => [
                'title_key' => 'multilingual.area_productivity_reports.titles.road_facilities',
                'subtitle_key' => 'multilingual.area_productivity_reports.subtitles.road_facilities',
                'route_name' => 'reports.area-productivity.road-facilities',
                'export_route_name' => 'reports.area-productivity.export.road-facilities',
                'sector_key' => 'multilingual.area_productivity_reports.sectors.road_facilities',
            ],
            default => throw new InvalidArgumentException("Unsupported area productivity report type [{$type}]."),
        };
    }

    private function normalizedGroupExpression(string $column): string
    {
        return "LOWER(TRIM(COALESCE({$column}, '')))";
    }

    private function preferredValueExpression(string $column): string
    {
        return "COALESCE(MAX(NULLIF(TRIM({$column}), '')), MAX(TRIM({$column})), '')";
    }

    private function assignedValueExpression(string $table): string
    {
        $columns = [];

        if (Schema::hasColumn($table, 'assigned_to')) {
            $columns[] = "NULLIF(TRIM({$table}.assigned_to), '')";
        }

        if (Schema::hasColumn($table, 'assignedto')) {
            $columns[] = "NULLIF(TRIM({$table}.assignedto), '')";
        }

        if ($columns === []) {
            return "''";
        }

        return 'COALESCE('.implode(', ', $columns).", '')";
    }

    private function dateColumn(string $table): string
    {
        if (Schema::hasColumn($table, 'creationdate')) {
            return "{$table}.creationdate";
        }

        return "{$table}.created_at";
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{pie: array<string, mixed>, neighborhoods: array<int, array<string, mixed>>}>
     */
    private function buildLocationPieCharts(Collection $rows, string $type): array
    {
        $metrics = $this->locationPieMetrics($type);
        $idPrefix = match ($type) {
            self::TYPE_HOUSING_UNITS => 'housing_units',
            self::TYPE_PUBLIC_BUILDINGS => 'public_buildings',
            self::TYPE_ROAD_FACILITIES => 'road_facilities',
            default => 'area_productivity',
        };

        return $rows
            ->filter(fn (object $row): bool => $this->metricTotal($row, $metrics) > 0)
            ->groupBy(fn (object $row): string => $this->locationValue($row->municipalitie ?? null))
            ->map(function (Collection $municipalityRows, string $municipality) use ($idPrefix, $type): array {
                $metrics = $this->locationPieMetrics($type);
                $municipalityPie = $this->makeLocationPie(
                    idPrefix: "{$idPrefix}_municipality",
                    title: $municipality,
                    subtitle: 'Municipality',
                    series: $this->metricSeries($municipalityRows, $metrics),
                    labels: array_column($metrics, 'label'),
                    colors: array_column($metrics, 'color'),
                    level: 'municipality',
                );

                $neighborhoods = $municipalityRows
                    ->when($type === self::TYPE_PUBLIC_BUILDINGS, fn (Collection $rows): Collection => $rows->take(0))
                    ->sortByDesc(fn (object $row): int => $this->metricTotal($row, $metrics))
                    ->values()
                    ->map(fn (object $row): array => $this->makeLocationPie(
                        idPrefix: "{$idPrefix}_neighborhood",
                        title: $this->locationValue($row->neighborhood ?? null),
                        subtitle: 'Neighborhood',
                        series: $this->metricSeries(collect([$row]), $metrics),
                        labels: array_column($metrics, 'label'),
                        colors: array_column($metrics, 'color'),
                        level: 'neighborhood',
                    ))
                    ->all();

                return [
                    'pie' => $municipalityPie,
                    'neighborhoods' => $neighborhoods,
                ];
            })
            ->sortByDesc(fn (array $municipalityNode): int => (int) $municipalityNode['pie']['items_count'])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeLocationPie(
        string $idPrefix,
        string $title,
        string $subtitle,
        array $series,
        array $labels,
        array $colors,
        string $level,
    ): array {
        $itemsCount = array_sum($series);

        return [
            'id' => $idPrefix.'_'.substr(md5($level.'|'.$title), 0, 12),
            'title' => $title,
            'subtitle' => $subtitle,
            'level' => $level,
            'series' => $series,
            'labels' => $labels,
            'colors' => $colors,
            'items_count' => $itemsCount,
            'units_count' => $itemsCount,
            'buildings_count' => $itemsCount,
            'completed_percent' => $this->percentage((int) ($series[0] ?? 0), $itemsCount),
            'not_completed_percent' => $this->percentage((int) ($series[1] ?? 0), $itemsCount),
            'summary_items' => collect($labels)
                ->map(fn (string $label, int $index): array => [
                    'label' => $label,
                    'value' => (int) ($series[$index] ?? 0),
                    'percent' => $this->percentage((int) ($series[$index] ?? 0), $itemsCount),
                    'color' => $colors[$index] ?? '#181c32',
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, color: string}>
     */
    private function locationPieMetrics(string $type): array
    {
        if ($type === self::TYPE_HOUSING_UNITS) {
            return [
                ['key' => 'tda_range', 'label' => 'Totally Damaged', 'color' => '#F1416C'],
                ['key' => 'pda_range', 'label' => 'Partially Damaged', 'color' => '#FFC700'],
                ['key' => 'cra_range', 'label' => 'Committee Review', 'color' => '#E879F9'],
            ];
        }

        if ($type === self::TYPE_ROAD_FACILITIES) {
            return [
                ['key' => 'destroyed_count', 'label' => 'Destroyed', 'color' => '#F1416C'],
                ['key' => 'severe_count', 'label' => 'Severe', 'color' => '#E879F9'],
                ['key' => 'moderate_count', 'label' => 'Moderate', 'color' => '#FFC700'],
                ['key' => 'minor_count', 'label' => 'Minor', 'color' => '#009EF7'],
                ['key' => 'no_damage_count', 'label' => 'No Damage', 'color' => '#50CD89'],
            ];
        }

        return [
            ['key' => 'tda_range', 'label' => 'Totally Damaged', 'color' => '#F1416C'],
            ['key' => 'pda_range', 'label' => 'Partially Damaged', 'color' => '#FFC700'],
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string, color: string}>  $metrics
     * @return array<int, int>
     */
    private function metricSeries(Collection $rows, array $metrics): array
    {
        return collect($metrics)
            ->map(fn (array $metric): int => (int) $rows->sum($metric['key']))
            ->all();
    }

    /**
     * @param  array<int, array{key: string, label: string, color: string}>  $metrics
     */
    private function metricTotal(object $row, array $metrics): int
    {
        return collect($metrics)
            ->sum(fn (array $metric): int => (int) ($row->{$metric['key']} ?? 0));
    }

    private function supportsLocationPieCharts(string $type): bool
    {
        return in_array($type, [
            self::TYPE_HOUSING_UNITS,
            self::TYPE_PUBLIC_BUILDINGS,
            self::TYPE_ROAD_FACILITIES,
        ], true);
    }

    private function percentage(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }

    private function locationValue(?string $value): string
    {
        $normalizedValue = trim((string) $value);

        return $normalizedValue !== ''
            ? $normalizedValue
            : __('multilingual.area_productivity_reports.labels.not_available');
    }
}
