<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Services\Reports;

use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HlpAuditReportService
{
    private const LEGAL_AUDITOR_TYPE = 'Legal Auditor';

    private const ACCEPTED_LEGAL_STATUS_NAMES = [
        'legal_notes',
        'accepted_by_lawyer',
        'legal notes',
        'accepted by lawyer',
        'accepted by layaer',
    ];

    public function build(array $filters): array
    {
        $dateRange = $this->resolveDateRange($filters);
        $rows = $this->rows($filters, $dateRange['from'], $dateRange['to']);

        return [
            'start_date' => $dateRange['from']->toDateString(),
            'end_date' => $dateRange['to']->toDateString(),
            'date_range_label' => $dateRange['from']->format('m/d/Y').' - '.$dateRange['to']->format('m/d/Y'),
            'filters' => [
                'governorate' => (string) ($filters['governorate'] ?? ''),
                'neighborhood' => (string) ($filters['neighborhood'] ?? ''),
            ],
            'filter_options' => $this->filterOptions(),
            'rows' => $rows,
            'summary' => [
                'hlp_buildings' => (int) $rows->sum('hlp_buildings'),
                'hlp_housings' => (int) $rows->sum('hlp_housings'),
            ],
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function exportRows(array $filters): Collection
    {
        $dateRange = $this->resolveDateRange($filters);

        return $this->rows($filters, $dateRange['from'], $dateRange['to']);
    }

    public function filterOptions(): array
    {
        return [
            'governorates' => Building::query()->orderBy('governorate')->pluck('governorate')->filter()->unique()->values(),
            'neighborhoods' => Building::query()->orderBy('neighborhood')->pluck('neighborhood')->filter()->unique()->values(),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function rows(array $filters, Carbon $fromDate, Carbon $toDate): Collection
    {
        $buildingRows = $this->buildingRows($filters, $fromDate, $toDate)->get()->keyBy($this->rowGroupKey(...));
        $housingRows = $this->housingRows($filters, $fromDate, $toDate)->get()->keyBy($this->rowGroupKey(...));

        return $buildingRows
            ->keys()
            ->merge($housingRows->keys())
            ->unique()
            ->map(function (string $groupKey) use ($buildingRows, $housingRows): object {
                $buildingRow = $buildingRows->get($groupKey);
                $housingRow = $housingRows->get($groupKey);

                return (object) [
                    'governorate' => $buildingRow->governorate ?? $housingRow->governorate ?? '',
                    'neighborhood' => $buildingRow->neighborhood ?? $housingRow->neighborhood ?? '',
                    'hlp_buildings' => (int) ($buildingRow->hlp_buildings ?? 0),
                    'hlp_housings' => (int) ($housingRow->hlp_housings ?? 0),
                ];
            })
            ->sortBy([
                ['governorate', 'asc'],
                ['neighborhood', 'asc'],
            ])
            ->values();
    }

    private function buildingRows(array $filters, Carbon $fromDate, Carbon $toDate): Builder
    {
        $query = BuildingStatus::query()
            ->join('assessment_statuses', 'building_statuses.status_id', '=', 'assessment_statuses.id')
            ->join('buildings', 'building_statuses.building_id', '=', 'buildings.objectid')
            ->where('building_statuses.type', self::LEGAL_AUDITOR_TYPE)
            ->whereIn('assessment_statuses.name', self::ACCEPTED_LEGAL_STATUS_NAMES)
            ->whereBetween('building_statuses.updated_at', [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()])
            ->selectRaw("
                {$this->preferredValueExpression('buildings.governorate')} as governorate,
                {$this->preferredValueExpression('buildings.neighborhood')} as neighborhood,
                COUNT(DISTINCT building_statuses.building_id) as hlp_buildings
            ")
            ->groupByRaw($this->normalizedValueExpression('buildings.governorate').', '.$this->normalizedValueExpression('buildings.neighborhood'));

        $this->applyLocationFilters($query, $filters, 'buildings');

        return $query;
    }

    private function housingRows(array $filters, Carbon $fromDate, Carbon $toDate): Builder
    {
        $governorateExpression = 'COALESCE(buildings.governorate, housing_units.governorate)';
        $neighborhoodExpression = 'COALESCE(buildings.neighborhood, housing_units.neighborhood)';
        $query = HousingStatus::query()
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->join('housing_units', 'housing_statuses.housing_id', '=', 'housing_units.objectid')
            ->leftJoin('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->where('housing_statuses.type', self::LEGAL_AUDITOR_TYPE)
            ->whereIn('assessment_statuses.name', self::ACCEPTED_LEGAL_STATUS_NAMES)
            ->whereBetween('housing_statuses.updated_at', [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()])
            ->selectRaw("
                {$this->preferredValueExpression($governorateExpression)} as governorate,
                {$this->preferredValueExpression($neighborhoodExpression)} as neighborhood,
                COUNT(DISTINCT housing_statuses.housing_id) as hlp_housings
            ")
            ->groupByRaw($this->normalizedValueExpression($governorateExpression).', '.$this->normalizedValueExpression($neighborhoodExpression));

        $this->applyLocationFilters($query, $filters, 'buildings');

        return $query;
    }

    private function applyLocationFilters(Builder $query, array $filters, string $table): void
    {
        if (filled($filters['governorate'] ?? null)) {
            $query->where("{$table}.governorate", (string) $filters['governorate']);
        }

        if (filled($filters['neighborhood'] ?? null)) {
            $query->where("{$table}.neighborhood", (string) $filters['neighborhood']);
        }
    }

    private function resolveDateRange(array $filters): array
    {
        $fromDate = filled($filters['start_date'] ?? null)
            ? Carbon::parse((string) $filters['start_date'])->startOfDay()
            : now()->subDays(30)->startOfDay();

        $toDate = filled($filters['end_date'] ?? null)
            ? Carbon::parse((string) $filters['end_date'])->endOfDay()
            : now()->endOfDay();

        if ($toDate->lt($fromDate)) {
            $toDate = $fromDate->copy()->endOfDay();
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    private function rowGroupKey(object $row): string
    {
        return ($row->governorate ?? 'N/A').'||'.($row->neighborhood ?? 'N/A');
    }

    private function normalizedValueExpression(string $column): string
    {
        return "COALESCE(NULLIF(TRIM({$column}), ''), 'N/A')";
    }

    private function preferredValueExpression(string $column): string
    {
        return 'MIN('.$this->normalizedValueExpression($column).')';
    }
}
