<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
            'start_date' => $dateRange['from']->toDateString(),
            'end_date' => $dateRange['to']->toDateString(),
            'date_range_label' => $dateRange['from']->format('m/d/Y').' - '.$dateRange['to']->format('m/d/Y'),
            'rows' => $rows,
            'filters' => [
                'governorate' => (string) ($filters['governorate'] ?? ''),
                'municipalitie' => (string) ($filters['municipalitie'] ?? ''),
                'neighborhood' => (string) ($filters['neighborhood'] ?? ''),
                'zone_code' => (string) ($filters['zone_code'] ?? ''),
                'assignedto' => (string) ($filters['assignedto'] ?? ''),
            ],
            'filter_options' => $this->filterOptions($type),
            'summary' => [
                'grouped_areas' => $rows->count(),
                'engineers' => (int) $rows->sum('no_eng'),
                'tda' => (int) $rows->sum('tda_range'),
                'pda' => (int) $rows->sum('pda_range'),
                'cra' => (int) $rows->sum('cra_range'),
                'total_records' => (int) $rows->sum('total_count'),
            ],
        ];
    }

    public function filterOptions(string $type): array
    {
        return match ($type) {
            self::TYPE_HOUSING_UNITS, self::TYPE_BUILDINGS => $this->buildingBackedFilterOptions(),
            self::TYPE_PUBLIC_BUILDINGS => $this->surveyFilterOptions(PublicBuildingSurvey::query(), 'assigned_to', false),
            self::TYPE_ROAD_FACILITIES => $this->surveyFilterOptions(RoadFacilitySurvey::query(), 'assigned_to', true),
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

    private function groupedQuery(string $type, array $filters, Carbon $fromDate, Carbon $toDate): Builder
    {
        return match ($type) {
            self::TYPE_HOUSING_UNITS => $this->housingUnitsQuery($filters, $fromDate, $toDate),
            self::TYPE_BUILDINGS => $this->buildingsQuery($filters, $fromDate, $toDate),
            self::TYPE_PUBLIC_BUILDINGS => $this->publicBuildingsQuery($filters, $fromDate, $toDate),
            self::TYPE_ROAD_FACILITIES => $this->roadFacilitiesQuery($filters, $fromDate, $toDate),
            default => throw new InvalidArgumentException("Unsupported area productivity report type [{$type}]."),
        };
    }

    private function housingUnitsQuery(array $filters, Carbon $fromDate, Carbon $toDate): Builder
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
                SUM(CASE WHEN housing_units.unit_damage_status IN ('committee_review2', 'committee_review', 'commite_review') THEN 1 ELSE 0 END) as cra_range,
                COUNT(housing_units.id) as total_count
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

    private function buildingsQuery(array $filters, Carbon $fromDate, Carbon $toDate): Builder
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
                SUM(CASE WHEN buildings.building_damage_status IN ('committee_review', 'commite_review') THEN 1 ELSE 0 END) as cra_range,
                COUNT(buildings.id) as total_count
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

    private function publicBuildingsQuery(array $filters, Carbon $fromDate, Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('public_building_surveys.neighborhood');

        $query = PublicBuildingSurvey::query()
            ->selectRaw("
                {$this->preferredValueExpression('public_building_surveys.governorate')} as governorate,
                {$this->preferredValueExpression('public_building_surveys.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('public_building_surveys.neighborhood')} as neighborhood,
                COUNT(DISTINCT public_building_surveys.assigned_to) as no_eng,
                SUM(CASE WHEN public_building_surveys.building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END) as tda_range,
                SUM(CASE WHEN public_building_surveys.building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END) as pda_range,
                SUM(CASE WHEN public_building_surveys.building_damage_status IN ('committee_review', 'commite_review') THEN 1 ELSE 0 END) as cra_range,
                COUNT(public_building_surveys.id) as total_count
            ")
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        $this->applyFilters($query, $filters, [
            'governorate' => 'public_building_surveys.governorate',
            'municipalitie' => 'public_building_surveys.municipalitie',
            'neighborhood' => 'public_building_surveys.neighborhood',
            'assignedto' => 'public_building_surveys.assigned_to',
        ], 'public_building_surveys.created_at', $fromDate, $toDate);

        return $query;
    }

    private function roadFacilitiesQuery(array $filters, Carbon $fromDate, Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('road_facility_surveys.neighborhood');

        $query = RoadFacilitySurvey::query()
            ->selectRaw("
                {$this->preferredValueExpression('road_facility_surveys.governorate')} as governorate,
                {$this->preferredValueExpression('road_facility_surveys.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('road_facility_surveys.neighborhood')} as neighborhood,
                COUNT(DISTINCT road_facility_surveys.assigned_to) as no_eng,
                SUM(CASE WHEN road_facility_surveys.road_damage_level IN ('destroyed', 'severe') THEN 1 ELSE 0 END) as tda_range,
                SUM(CASE WHEN road_facility_surveys.road_damage_level IN ('moderate', 'minor') THEN 1 ELSE 0 END) as pda_range,
                SUM(CASE WHEN road_facility_surveys.road_damage_level IN ('No_Damage', 'no_damage') THEN 1 ELSE 0 END) as cra_range,
                COUNT(road_facility_surveys.id) as total_count
            ")
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        $this->applyFilters($query, $filters, [
            'governorate' => 'road_facility_surveys.governorate',
            'municipalitie' => 'road_facility_surveys.municipalitie',
            'neighborhood' => 'road_facility_surveys.neighborhood',
            'zone_code' => 'road_facility_surveys.zone_code',
            'assignedto' => 'road_facility_surveys.assigned_to',
        ], 'road_facility_surveys.created_at', $fromDate, $toDate);

        return $query;
    }

    private function applyFilters(
        Builder $query,
        array $filters,
        array $columnMap,
        string $dateColumn,
        Carbon $fromDate,
        Carbon $toDate,
    ): void {
        foreach ($columnMap as $filterKey => $column) {
            if (filled($filters[$filterKey] ?? null)) {
                $query->where($column, (string) $filters[$filterKey]);
            }
        }

        $query->whereBetween($dateColumn, [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()]);
    }

    private function resolveDateRange(array $filters): array
    {
        $rawStartDate = $filters['start_date'] ?? $filters['from_date'] ?? null;
        $rawEndDate = $filters['end_date'] ?? $filters['to_date'] ?? null;

        $fromDate = filled($rawStartDate)
            ? Carbon::parse((string) $rawStartDate)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $toDate = filled($rawEndDate)
            ? Carbon::parse((string) $rawEndDate)->endOfDay()
            : now()->endOfDay();

        if ($toDate->lt($fromDate)) {
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

    private function surveyFilterOptions(Builder $query, string $assignedColumn, bool $withZoneCode): array
    {
        $governorates = (clone $query)->orderBy('governorate')->pluck('governorate')->filter()->unique()->values();
        $municipalities = (clone $query)->orderBy('municipalitie')->pluck('municipalitie')->filter()->unique()->values();
        $neighborhoods = (clone $query)->orderBy('neighborhood')->pluck('neighborhood')->filter()->unique()->values();
        $assignedto = (clone $query)->orderBy($assignedColumn)->pluck($assignedColumn)->filter()->unique()->values();
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
}
