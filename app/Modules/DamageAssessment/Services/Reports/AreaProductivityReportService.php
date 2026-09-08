<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Services\Reports;

use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Support\CsoDamageStatusMapper;
use App\Support\Phase\PhaseContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AreaProductivityReportService
{
    public const TYPE_HOUSING_UNITS = 'housing_units';

    public const TYPE_BUILDINGS = 'buildings';

    public const TYPE_PUBLIC_BUILDINGS = 'public_buildings';

    public const TYPE_ROAD_FACILITIES = 'road_facilities';

    public const TYPE_CSO_SURVEYS = 'cso_surveys';

    /**
     * @var list<string>
     */
    private const HOUSING_UNIT_PRODUCTIVITY_STATUSES = [
        'fully_damaged2',
        'partially_damaged2',
        'no_damaged',
        'committee_review2',
        'committee_review',
        'commite_review',
        'commitee_review2',
        'commitee_review',
    ];

    /**
     * @var list<string>
     */
    private const BUILDING_COMMITTEE_STATUSES = [
        'committee_review',
        'commite_review',
        'commitee_review',
        'committee_review2',
        'commitee_review2',
    ];

    /**
     * @var list<string>
     */
    private const UNIT_COMMITTEE_STATUSES = [
        'committee_review2',
        'committee_review',
        'commite_review',
        'commitee_review2',
        'commitee_review',
    ];

    /**
     * @var list<string>
     */
    private const COMMITTEE_ARCHIVE_SOURCE_TYPES = [
        'committee_decision',
        'temporary_committee_excel_archive',
    ];

    public function build(string $type, array $filters): array
    {
        $definition = $this->definition($type);
        $dateRange = $this->resolveDateRange($filters);
        $rows = $this->withArchivedCommitteeProductivity(
            $type,
            $this->groupedQuery($type, $filters, $dateRange['from'], $dateRange['to'])->get(),
            $filters,
            $dateRange['from'],
            $dateRange['to'],
        );

        $csoDetails = $type === self::TYPE_CSO_SURVEYS
            ? $this->csoDetails($filters, $dateRange['from'], $dateRange['to'])
            : [
                'organizations' => collect(),
                'units' => collect(),
                'organization_summary' => [],
                'unit_summary' => [],
                'organization_charts' => ['location_pies' => []],
                'unit_charts' => ['location_pies' => []],
            ];

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
                'governorate' => $this->filterValues($filters['governorate'] ?? null),
                'municipalitie' => $this->filterValues($filters['municipalitie'] ?? null),
                'neighborhood' => $this->filterValues($filters['neighborhood'] ?? null),
                'zone_code' => $this->filterValues($filters['zone_code'] ?? null),
                'assignedto' => $this->filterValues($filters['assignedto'] ?? null),
            ],
            'filter_options' => $this->filterOptions($type),
            'charts' => [
                'location_pies' => $this->supportsLocationPieCharts($type)
                    ? $this->buildLocationPieCharts(
                        $rows,
                        $type,
                        $type === self::TYPE_CSO_SURVEYS ? 'cso_summary' : null,
                    )
                    : [],
            ],
            'summary' => [
                'grouped_areas' => $rows->count(),
                'engineers' => (int) $rows->sum('no_eng'),
                'tda' => (int) $rows->sum('tda_range'),
                'pda' => (int) $rows->sum('pda_range'),
                'cra' => (int) $rows->sum('cra_range'),
                'unclassified' => (int) $rows->sum('unclassified_count'),
                'no_damage' => (int) $rows->sum('no_damage_count'),
                'destroyed' => (int) $rows->sum('destroyed_count'),
                'severe' => (int) $rows->sum('severe_count'),
                'moderate' => (int) $rows->sum('moderate_count'),
                'minor' => (int) $rows->sum('minor_count'),
                'total_records' => (int) $rows->sum('total_count'),
                'total_road_length_km' => round((float) $rows->sum('total_road_length_km'), 3),
                'housing_units_count' => (int) $rows->sum('housing_units_count'),
                'organizations_count' => (int) $rows->sum('organizations_count'),
                'cso_units_count' => (int) $rows->sum('cso_units_count'),
            ],
            'cso' => $csoDetails,
        ];
    }

    public function filterOptions(string $type): array
    {
        return match ($type) {
            self::TYPE_HOUSING_UNITS, self::TYPE_BUILDINGS => $this->buildingBackedFilterOptions(),
            self::TYPE_PUBLIC_BUILDINGS => $this->surveyFilterOptions(PublicBuildingSurvey::query(), false),
            self::TYPE_ROAD_FACILITIES => $this->surveyFilterOptions(RoadFacilitySurvey::query(), true),
            self::TYPE_CSO_SURVEYS => $this->surveyFilterOptions(CsoSurvey::query(), false),
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

        return $this->withArchivedCommitteeProductivity(
            $type,
            $this->groupedQuery($type, $filters, $dateRange['from'], $dateRange['to'])->get(),
            $filters,
            $dateRange['from'],
            $dateRange['to'],
        )
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
            self::TYPE_CSO_SURVEYS => $this->csoSurveysQuery($filters, $fromDate, $toDate),
            default => throw new InvalidArgumentException("Unsupported area productivity report type [{$type}]."),
        };
    }

    private function housingUnitsQuery(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('buildings.neighborhood');

        $query = AuditedHousingUnit::query()
            ->from('audited_housing_units as housing_units')
            ->join('audited_buildings as buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->selectRaw("
                {$this->preferredValueExpression('buildings.governorate')} as governorate,
                {$this->preferredValueExpression('buildings.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('buildings.neighborhood')} as neighborhood,
                COUNT(DISTINCT buildings.assignedto) as no_eng,
                SUM(CASE WHEN housing_units.unit_damage_status = 'fully_damaged2' THEN 1 ELSE 0 END) as tda_range,
                SUM(CASE WHEN housing_units.unit_damage_status = 'partially_damaged2' THEN 1 ELSE 0 END) as pda_range,
                SUM(CASE WHEN housing_units.unit_damage_status = 'no_damaged' THEN 1 ELSE 0 END) as no_damage_count,
                SUM(CASE WHEN housing_units.unit_damage_status IN ('committee_review2', 'committee_review', 'commite_review', 'commitee_review2', 'commitee_review') THEN 1 ELSE 0 END) as cra_range,
                SUM(CASE WHEN housing_units.unit_damage_status IS NULL OR TRIM(housing_units.unit_damage_status) = '' THEN 1 ELSE 0 END) as unclassified_count,
                SUM(CASE
                    WHEN housing_units.unit_damage_status IN ('".implode("', '", self::HOUSING_UNIT_PRODUCTIVITY_STATUSES)."')
                        OR housing_units.unit_damage_status IS NULL
                        OR TRIM(housing_units.unit_damage_status) = ''
                    THEN 1
                    ELSE 0
                END) as total_count
            ")
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        app(PhaseContext::class)->applyToEloquent($query, 'buildings.phase_number');

        $this->applyFilters($query, $filters, [
            'governorate' => 'buildings.governorate',
            'municipalitie' => 'buildings.municipalitie',
            'neighborhood' => 'buildings.neighborhood',
            'zone_code' => 'buildings.zone_code',
            'assignedto' => 'buildings.assignedto',
        ], 'housing_units.building_submit_date', $fromDate, $toDate);

        return $query;
    }

    private function buildingsQuery(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('buildings.neighborhood');
        $housingUnitsCountSubquery = $this->housingUnitsCountSubquery($filters, $fromDate, $toDate);

        $query = AuditedBuilding::query()
            ->from('audited_buildings as buildings')
            ->leftJoinSub($housingUnitsCountSubquery, 'filtered_housing_units', function ($join) use ($groupKey): void {
                $join->on('filtered_housing_units.neighborhood_group', '=', DB::raw($groupKey));
            })
            ->where('buildings.field_status', 'COMPLETED')
            ->selectRaw("
                {$this->preferredValueExpression('buildings.governorate')} as governorate,
                {$this->preferredValueExpression('buildings.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('buildings.neighborhood')} as neighborhood,
                COUNT(DISTINCT buildings.assignedto) as no_eng,
                SUM(CASE WHEN buildings.building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END) as tda_range,
                SUM(CASE WHEN buildings.building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END) as pda_range,
                SUM(CASE WHEN buildings.building_damage_status IN ('committee_review', 'commite_review', 'commitee_review', 'committee_review2', 'commitee_review2') THEN 1 ELSE 0 END) as cra_range,
                SUM(CASE WHEN buildings.building_damage_status IS NULL OR TRIM(buildings.building_damage_status) = '' THEN 1 ELSE 0 END) as unclassified_count,
                SUM(CASE
                    WHEN buildings.building_damage_status IN ('fully_damaged', 'partially_damaged', 'committee_review', 'commite_review', 'commitee_review', 'committee_review2', 'commitee_review2')
                        OR buildings.building_damage_status IS NULL
                        OR TRIM(buildings.building_damage_status) = ''
                    THEN 1
                    ELSE 0
                END) as total_count,
                COALESCE(MAX(filtered_housing_units.housing_units_count), 0) as housing_units_count
            ")
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        app(PhaseContext::class)->applyToEloquent($query, 'buildings.phase_number');

        $this->applyFilters($query, $filters, [
            'governorate' => 'buildings.governorate',
            'municipalitie' => 'buildings.municipalitie',
            'neighborhood' => 'buildings.neighborhood',
            'zone_code' => 'buildings.zone_code',
            'assignedto' => 'buildings.assignedto',
        ], 'buildings.end', $fromDate, $toDate);

        return $query;
    }

    private function housingUnitsCountSubquery(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('unit_buildings.neighborhood');

        $query = AuditedHousingUnit::query()
            ->from('audited_housing_units as housing_units')
            ->join('audited_buildings as unit_buildings', 'housing_units.parentglobalid', '=', 'unit_buildings.globalid')
            ->selectRaw("
                {$groupKey} as neighborhood_group,
                COUNT(*) as housing_units_count
            ")
            ->groupByRaw($groupKey);

        app(PhaseContext::class)->applyToEloquent($query, 'unit_buildings.phase_number');

        $this->applyFilters($query, $filters, [
            'governorate' => 'unit_buildings.governorate',
            'municipalitie' => 'unit_buildings.municipalitie',
            'neighborhood' => 'unit_buildings.neighborhood',
            'zone_code' => 'unit_buildings.zone_code',
            'assignedto' => 'unit_buildings.assignedto',
        ], 'housing_units.building_submit_date', $fromDate, $toDate);

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
        $lengthColumn = $this->roadLengthColumn();
        $lengthExpression = $lengthColumn !== null
            ? "ROUND(SUM(COALESCE(road_facility_surveys.{$lengthColumn}, 0)), 3)"
            : '0';

        $query = RoadFacilitySurvey::query()
            ->where('road_facility_surveys.field_status', 'COMPLETED')
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
                    WHEN road_facility_surveys.road_damage_level IS NULL
                        OR TRIM(road_facility_surveys.road_damage_level) = ''
                        OR road_facility_surveys.road_damage_level NOT IN ('destroyed', 'severe', 'moderate', 'minor', 'No_Damage', 'no_damage')
                    THEN 1 ELSE 0
                END) as unclassified_count,
                COUNT(road_facility_surveys.id) as total_count,
                {$lengthExpression} as total_road_length_km
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

    private function csoSurveysQuery(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Builder
    {
        $groupKey = $this->normalizedGroupExpression('cso_surveys.neighborhood');
        $organizationCounts = CsoSurveyOrganization::query()
            ->from('cso_survey_organizations as organizations')
            ->selectRaw('organizations.parentglobalid, COUNT(*) as organizations_count')
            ->groupBy('organizations.parentglobalid');
        $unitCounts = CsoSurveyUnit::query()
            ->from('cso_survey_units as units')
            ->selectRaw('units.parentglobalid, COUNT(*) as cso_units_count')
            ->groupBy('units.parentglobalid');

        $query = CsoSurvey::query()
            ->leftJoinSub($organizationCounts, 'organization_counts', function ($join): void {
                $join->on('organization_counts.parentglobalid', '=', 'cso_surveys.globalid');
            })
            ->leftJoinSub($unitCounts, 'unit_counts', function ($join): void {
                $join->on('unit_counts.parentglobalid', '=', 'cso_surveys.globalid');
            })
            ->selectRaw("
                {$this->preferredValueExpression('cso_surveys.governorate')} as governorate,
                {$this->preferredValueExpression('cso_surveys.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('cso_surveys.neighborhood')} as neighborhood,
                COUNT(DISTINCT NULLIF(TRIM(cso_surveys.assignedto), '')) as no_eng,
                ".CsoDamageStatusMapper::sumSql('cso_surveys.building_damage_status', CsoDamageStatusMapper::FULLY_DAMAGED).' as tda_range,
                '.CsoDamageStatusMapper::sumSql('cso_surveys.building_damage_status', CsoDamageStatusMapper::PARTIALLY_DAMAGED).' as pda_range,
                '.CsoDamageStatusMapper::sumSql('cso_surveys.building_damage_status', CsoDamageStatusMapper::NO_DAMAGE).' as no_damage_count,
                '.CsoDamageStatusMapper::sumSql('cso_surveys.building_damage_status', CsoDamageStatusMapper::COMMITTEE_REVIEW).' as cra_range,
                '.CsoDamageStatusMapper::unclassifiedSumSql('cso_surveys.building_damage_status').' as unclassified_count,
                COUNT(cso_surveys.id) as total_count,
                SUM(COALESCE(organization_counts.organizations_count, 0)) as organizations_count,
                SUM(COALESCE(unit_counts.cso_units_count, 0)) as cso_units_count
            ')
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        app(PhaseContext::class)->applyToEloquent($query, 'cso_surveys.phase_number');

        $this->applyFilters($query, $filters, [
            'governorate' => 'cso_surveys.governorate',
            'municipalitie' => 'cso_surveys.municipalitie',
            'neighborhood' => 'cso_surveys.neighborhood',
            'assignedto' => 'cso_surveys.assignedto',
        ], $this->dateColumn('cso_surveys'), $fromDate, $toDate);

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
            $values = $this->filterValues($filters[$filterKey] ?? null);

            if ($values === []) {
                continue;
            }

            if (str_contains($column, '(')) {
                $placeholders = collect($values)->map(fn (): string => '?')->implode(', ');
                $query->whereRaw("{$column} in ({$placeholders})", $values);
            } else {
                $query->whereIn($column, $values);
            }
        }

        if ($fromDate && $toDate) {
            $query->whereBetween($dateColumn, [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()]);
        }
    }

    private function csoDetails(array $filters, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        $organizations = $this->csoOrganizationRows($filters, $fromDate, $toDate);
        $units = $this->csoUnitRows($filters, $fromDate, $toDate);

        return [
            'organizations' => $organizations,
            'units' => $units,
            'organization_summary' => $this->damageSummary($organizations),
            'unit_summary' => $this->damageSummary($units),
            'organization_charts' => [
                'location_pies' => $this->buildLocationPieCharts($organizations, self::TYPE_CSO_SURVEYS, 'cso_organizations'),
            ],
            'unit_charts' => [
                'location_pies' => $this->buildLocationPieCharts($units, self::TYPE_CSO_SURVEYS, 'cso_units'),
            ],
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function csoOrganizationRows(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Collection
    {
        $groupKey = $this->normalizedGroupExpression('surveys.neighborhood');

        $query = CsoSurveyOrganization::query()
            ->from('cso_survey_organizations as organizations')
            ->join('cso_surveys as surveys', 'organizations.parentglobalid', '=', 'surveys.globalid')
            ->selectRaw("
                {$this->preferredValueExpression('surveys.governorate')} as governorate,
                {$this->preferredValueExpression('surveys.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('surveys.neighborhood')} as neighborhood,
                COUNT(DISTINCT NULLIF(TRIM(surveys.assignedto), '')) as no_eng,
                ".CsoDamageStatusMapper::sumSql('surveys.building_damage_status', CsoDamageStatusMapper::FULLY_DAMAGED).' as tda_range,
                '.CsoDamageStatusMapper::sumSql('surveys.building_damage_status', CsoDamageStatusMapper::PARTIALLY_DAMAGED).' as pda_range,
                '.CsoDamageStatusMapper::sumSql('surveys.building_damage_status', CsoDamageStatusMapper::NO_DAMAGE).' as no_damage_count,
                '.CsoDamageStatusMapper::sumSql('surveys.building_damage_status', CsoDamageStatusMapper::COMMITTEE_REVIEW).' as cra_range,
                '.CsoDamageStatusMapper::unclassifiedSumSql('surveys.building_damage_status').' as unclassified_count,
                COUNT(organizations.id) as total_count
            ')
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        app(PhaseContext::class)->applyToEloquent($query, 'surveys.phase_number');

        $this->applyFilters($query, $filters, [
            'governorate' => 'surveys.governorate',
            'municipalitie' => 'surveys.municipalitie',
            'neighborhood' => 'surveys.neighborhood',
            'assignedto' => 'surveys.assignedto',
        ], $this->dateColumn('surveys'), $fromDate, $toDate);

        return $query->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function csoUnitRows(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Collection
    {
        $groupKey = $this->normalizedGroupExpression('surveys.neighborhood');

        $query = CsoSurveyUnit::query()
            ->from('cso_survey_units as units')
            ->join('cso_surveys as surveys', 'units.parentglobalid', '=', 'surveys.globalid')
            ->selectRaw("
                {$this->preferredValueExpression('surveys.governorate')} as governorate,
                {$this->preferredValueExpression('surveys.municipalitie')} as municipalitie,
                {$this->preferredValueExpression('surveys.neighborhood')} as neighborhood,
                COUNT(DISTINCT NULLIF(TRIM(surveys.assignedto), '')) as no_eng,
                ".CsoDamageStatusMapper::sumSql('units.unit_damage_status', CsoDamageStatusMapper::FULLY_DAMAGED).' as tda_range,
                '.CsoDamageStatusMapper::sumSql('units.unit_damage_status', CsoDamageStatusMapper::PARTIALLY_DAMAGED).' as pda_range,
                '.CsoDamageStatusMapper::sumSql('units.unit_damage_status', CsoDamageStatusMapper::NO_DAMAGE).' as no_damage_count,
                '.CsoDamageStatusMapper::sumSql('units.unit_damage_status', CsoDamageStatusMapper::COMMITTEE_REVIEW).' as cra_range,
                '.CsoDamageStatusMapper::unclassifiedSumSql('units.unit_damage_status').' as unclassified_count,
                COUNT(units.id) as total_count
            ')
            ->groupByRaw($groupKey)
            ->orderByDesc('total_count');

        app(PhaseContext::class)->applyToEloquent($query, 'surveys.phase_number');

        $this->applyFilters($query, $filters, [
            'governorate' => 'surveys.governorate',
            'municipalitie' => 'surveys.municipalitie',
            'neighborhood' => 'surveys.neighborhood',
            'assignedto' => 'surveys.assignedto',
        ], $this->dateColumn('surveys'), $fromDate, $toDate);

        return $query->get();
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function damageSummary(Collection $rows): array
    {
        return [
            'total_records' => (int) $rows->sum('total_count'),
            'tda' => (int) $rows->sum('tda_range'),
            'pda' => (int) $rows->sum('pda_range'),
            'no_damage' => (int) $rows->sum('no_damage_count'),
            'cra' => (int) $rows->sum('cra_range'),
            'unclassified' => (int) $rows->sum('unclassified_count'),
            'cso_units_count' => (int) $rows->sum('cso_units_count'),
            'engineers' => (int) $rows->sum('no_eng'),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function withArchivedCommitteeProductivity(string $type, Collection $rows, array $filters, ?Carbon $fromDate, ?Carbon $toDate): Collection
    {
        if (! in_array($type, [self::TYPE_BUILDINGS, self::TYPE_HOUSING_UNITS], true) || ! Schema::hasTable('building_survey_archive_objects')) {
            return $rows;
        }

        $archiveRows = $type === self::TYPE_BUILDINGS
            ? $this->archivedCommitteeBuildingRows($filters, $fromDate, $toDate)
            : $this->archivedCommitteeHousingUnitRows($filters, $fromDate, $toDate);

        foreach ($archiveRows as $archiveRow) {
            $key = $this->groupKeyFromRow($archiveRow);
            $row = $rows->first(fn (object $existingRow): bool => $this->groupKeyFromRow($existingRow) === $key);

            if ($row === null) {
                $row = $this->emptyAreaProductivityRow($archiveRow);
                $rows->push($row);
            }

            $archivedCount = (int) ($archiveRow->archived_committee_count ?? 0);
            $row->cra_range = (int) ($row->cra_range ?? 0) + $archivedCount;
            $row->total_count = (int) ($row->total_count ?? 0) + $archivedCount;
            $row->no_eng = max((int) ($row->no_eng ?? 0), (int) ($archiveRow->archived_no_eng ?? 0));
        }

        return $rows
            ->sortByDesc(fn (object $row): int => (int) ($row->total_count ?? 0))
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function archivedCommitteeBuildingRows(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Collection
    {
        $snapshotColumn = 'building_snapshot';
        $neighborhoodExpression = $this->jsonValueExpression('archive', $snapshotColumn, 'neighborhood');
        $assignedExpression = $this->jsonValueExpression('archive', $snapshotColumn, 'assignedto');
        $groupKey = $this->normalizedGroupExpression($neighborhoodExpression);

        $query = DB::table('building_survey_archive_objects as archive')
            ->whereNull('archive.housing_unit_objectid')
            ->whereIn('archive.source_type', self::COMMITTEE_ARCHIVE_SOURCE_TYPES)
            ->whereIn('archive.building_snapshot->building_damage_status', self::BUILDING_COMMITTEE_STATUSES)
            ->selectRaw("
                {$this->preferredValueExpression($this->jsonValueExpression('archive', $snapshotColumn, 'governorate'))} as governorate,
                {$this->preferredValueExpression($this->jsonValueExpression('archive', $snapshotColumn, 'municipalitie'))} as municipalitie,
                {$this->preferredValueExpression($neighborhoodExpression)} as neighborhood,
                COUNT(DISTINCT NULLIF(TRIM({$assignedExpression}), '')) as archived_no_eng,
                COUNT(DISTINCT archive.building_objectid) as archived_committee_count
            ")
            ->groupByRaw($groupKey);

        $this->applyArchiveFilters($query, $filters, $snapshotColumn, ['building_snapshot->end', 'building_snapshot->submission_date'], $fromDate, $toDate);

        return $query->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function archivedCommitteeHousingUnitRows(array $filters, ?Carbon $fromDate, ?Carbon $toDate): Collection
    {
        $buildingSnapshotColumn = 'building_snapshot';
        $unitSnapshotColumn = 'housing_unit_snapshot';
        $neighborhoodExpression = $this->jsonValueExpression('archive', $buildingSnapshotColumn, 'neighborhood');
        $assignedExpression = $this->jsonValueExpression('archive', $buildingSnapshotColumn, 'assignedto');
        $groupKey = $this->normalizedGroupExpression($neighborhoodExpression);

        $query = DB::table('building_survey_archive_objects as archive')
            ->whereNotNull('archive.housing_unit_objectid')
            ->whereIn('archive.source_type', self::COMMITTEE_ARCHIVE_SOURCE_TYPES)
            ->whereIn('archive.housing_unit_snapshot->unit_damage_status', self::UNIT_COMMITTEE_STATUSES)
            ->selectRaw("
                {$this->preferredValueExpression($this->jsonValueExpression('archive', $buildingSnapshotColumn, 'governorate'))} as governorate,
                {$this->preferredValueExpression($this->jsonValueExpression('archive', $buildingSnapshotColumn, 'municipalitie'))} as municipalitie,
                {$this->preferredValueExpression($neighborhoodExpression)} as neighborhood,
                COUNT(DISTINCT NULLIF(TRIM({$assignedExpression}), '')) as archived_no_eng,
                COUNT(DISTINCT archive.housing_unit_objectid) as archived_committee_count
            ")
            ->groupByRaw($groupKey);

        $this->applyArchiveFilters($query, $filters, $buildingSnapshotColumn, ["{$unitSnapshotColumn}->building_submit_date"], $fromDate, $toDate);

        return $query->get();
    }

    /**
     * @param  array<int, string>  $datePaths
     */
    private function applyArchiveFilters(\Illuminate\Database\Query\Builder $query, array $filters, string $snapshotColumn, array $datePaths, ?Carbon $fromDate, ?Carbon $toDate): void
    {
        foreach ([
            'governorate' => 'governorate',
            'municipalitie' => 'municipalitie',
            'neighborhood' => 'neighborhood',
            'zone_code' => 'zone_code',
            'assignedto' => 'assignedto',
        ] as $filterKey => $snapshotKey) {
            $values = $this->filterValues($filters[$filterKey] ?? null);

            if ($values === []) {
                continue;
            }

            $query->whereIn("archive.{$snapshotColumn}->{$snapshotKey}", $values);
        }

        $phase = app(PhaseContext::class)->selected();

        if ($phase !== null) {
            $query->where("archive.{$snapshotColumn}->phase_number", $phase);
        }

        if ($fromDate && $toDate) {
            $query->where(function (\Illuminate\Database\Query\Builder $dateQuery) use ($datePaths, $fromDate, $toDate): void {
                foreach ($datePaths as $index => $datePath) {
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $dateQuery->{$method}(function (\Illuminate\Database\Query\Builder $pathQuery) use ($datePath, $fromDate, $toDate): void {
                        $pathQuery->whereBetween("archive.{$datePath}", [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()]);
                    });
                }
            });
        }
    }

    private function emptyAreaProductivityRow(object $source): object
    {
        return (object) [
            'governorate' => $source->governorate ?? '',
            'municipalitie' => $source->municipalitie ?? '',
            'neighborhood' => $source->neighborhood ?? '',
            'no_eng' => 0,
            'tda_range' => 0,
            'pda_range' => 0,
            'cra_range' => 0,
            'destroyed_count' => 0,
            'severe_count' => 0,
            'moderate_count' => 0,
            'minor_count' => 0,
            'no_damage_count' => 0,
            'unclassified_count' => 0,
            'total_count' => 0,
            'total_road_length_km' => 0.0,
            'housing_units_count' => 0,
        ];
    }

    private function groupKeyFromRow(object $row): string
    {
        return mb_strtolower(trim((string) ($row->neighborhood ?? '')));
    }

    private function jsonValueExpression(string $tableAlias, string $jsonColumn, string $key): string
    {
        $path = '$."'.str_replace('"', '\"', $key).'"';

        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract({$tableAlias}.{$jsonColumn}, '{$path}')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$tableAlias}.{$jsonColumn}, '{$path}'))";
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

    /**
     * @return list<string>
     */
    private function filterValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return collect(is_array($value) ? $value : [$value])
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function buildingBackedFilterOptions(): array
    {
        $query = AuditedBuilding::query();
        app(PhaseContext::class)->applyToEloquent($query);

        return [
            'governorates' => (clone $query)->orderBy('governorate')->pluck('governorate')->filter()->unique()->values(),
            'municipalities' => (clone $query)->orderBy('municipalitie')->pluck('municipalitie')->filter()->unique()->values(),
            'neighborhoods' => (clone $query)->orderBy('neighborhood')->pluck('neighborhood')->filter()->unique()->values(),
            'zone_codes' => (clone $query)->orderBy('zone_code')->pluck('zone_code')->filter()->unique()->values(),
            'assignedto' => (clone $query)->orderBy('assignedto')->pluck('assignedto')->filter()->unique()->values(),
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
            self::TYPE_CSO_SURVEYS => [
                'title_key' => 'multilingual.area_productivity_reports.titles.cso_surveys',
                'subtitle_key' => 'multilingual.area_productivity_reports.subtitles.cso_surveys',
                'route_name' => 'reports.area-productivity.cso-surveys',
                'export_route_name' => 'reports.area-productivity.export.cso-surveys',
                'sector_key' => 'multilingual.area_productivity_reports.sectors.cso_surveys',
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

    private function roadLengthColumn(): ?string
    {
        return collect(['Lenght_Km_2', 'lenght_km_2', 'length_km_2'])
            ->first(fn (string $column): bool => Schema::hasColumn('road_facility_surveys', $column));
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{pie: array<string, mixed>, neighborhoods: array<int, array<string, mixed>>}>
     */
    private function buildLocationPieCharts(Collection $rows, string $type, ?string $idPrefixOverride = null): array
    {
        $metrics = $this->locationPieMetrics($type);
        $idPrefix = $idPrefixOverride ?? match ($type) {
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
        if ($type === self::TYPE_CSO_SURVEYS) {
            return [
                ['key' => 'tda_range', 'label' => __('multilingual.area_productivity_reports.metrics.totally_damaged'), 'color' => '#F1416C'],
                ['key' => 'pda_range', 'label' => __('multilingual.area_productivity_reports.metrics.partially_damaged'), 'color' => '#FFC700'],
                ['key' => 'no_damage_count', 'label' => __('multilingual.area_productivity_reports.metrics.no_damage'), 'color' => '#50CD89'],
                ['key' => 'cra_range', 'label' => __('multilingual.area_productivity_reports.metrics.committee_review'), 'color' => '#E879F9'],
                ['key' => 'unclassified_count', 'label' => __('multilingual.area_productivity_reports.metrics.unclassified'), 'color' => '#7E8299'],
            ];
        }

        if ($type === self::TYPE_HOUSING_UNITS) {
            return [
                ['key' => 'tda_range', 'label' => __('multilingual.area_productivity_reports.metrics.totally_damaged'), 'color' => '#F1416C'],
                ['key' => 'pda_range', 'label' => __('multilingual.area_productivity_reports.metrics.partially_damaged'), 'color' => '#FFC700'],
                ['key' => 'cra_range', 'label' => __('multilingual.area_productivity_reports.metrics.committee_review'), 'color' => '#E879F9'],
                ['key' => 'no_damage_count', 'label' => __('multilingual.area_productivity_reports.metrics.no_damage'), 'color' => '#50CD89'],
                ['key' => 'unclassified_count', 'label' => __('multilingual.area_productivity_reports.metrics.unclassified'), 'color' => '#7E8299'],
            ];
        }

        if ($type === self::TYPE_ROAD_FACILITIES) {
            return [
                ['key' => 'destroyed_count', 'label' => __('multilingual.area_productivity_reports.metrics.destroyed'), 'color' => '#F1416C'],
                ['key' => 'severe_count', 'label' => __('multilingual.area_productivity_reports.metrics.severe'), 'color' => '#E879F9'],
                ['key' => 'moderate_count', 'label' => __('multilingual.area_productivity_reports.metrics.moderate'), 'color' => '#FFC700'],
                ['key' => 'minor_count', 'label' => __('multilingual.area_productivity_reports.metrics.minor'), 'color' => '#009EF7'],
                ['key' => 'no_damage_count', 'label' => __('multilingual.area_productivity_reports.metrics.no_damage'), 'color' => '#50CD89'],
                ['key' => 'unclassified_count', 'label' => __('multilingual.area_productivity_reports.metrics.unclassified'), 'color' => '#7E8299'],
            ];
        }

        return [
            ['key' => 'tda_range', 'label' => __('multilingual.area_productivity_reports.metrics.totally_damaged'), 'color' => '#F1416C'],
            ['key' => 'pda_range', 'label' => __('multilingual.area_productivity_reports.metrics.partially_damaged'), 'color' => '#FFC700'],
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
            self::TYPE_CSO_SURVEYS,
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
