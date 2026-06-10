<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Services\Reports;

use App\Models\Building;
use App\Models\HousingUnit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EngineerAuditReportService
{
    public const REPORT_TYPE_BUILDINGS = 'buildings';

    public const REPORT_TYPE_HOUSING_UNITS = 'housing_units';

    private const ENGINEER_AUDITOR_TYPE = 'QC/QA Engineer';

    private const COMPLETED_FIELD_STATUS = 'completed';

    private const DEFAULT_START_DATE = '2026-01-01';

    private const SUBMISSION_DATE_FIELD = 'end';

    /**
     * @var list<string>
     */
    private const ACCEPTED_STATUS_NAMES = [
        'accepted_by_engineer',
        'accepted',
    ];

    /**
     * @var list<string>
     */
    private const REJECTED_STATUS_NAMES = [
        'rejected_by_engineer',
        'rejected',
    ];

    /**
     * @var list<string>
     */
    private const NEED_REVIEW_STATUS_NAMES = [
        'need_review',
    ];

    public function build(array $filters): array
    {
        $filters = $this->resolveFilters($filters);
        $activeReportType = $this->resolveReportType($filters['report_type'] ?? null);
        $reports = [
            self::REPORT_TYPE_BUILDINGS => $this->buildReport($this->buildingRows($filters)),
            self::REPORT_TYPE_HOUSING_UNITS => $this->buildReport($this->housingUnitRows($filters)),
        ];
        $activeReport = $reports[$activeReportType];

        return [
            'filters' => [
                'assignedto' => $this->firstFilterValue($filters['assignedto'] ?? null),
                'start_date' => (string) ($filters['start_date'] ?? ''),
                'end_date' => (string) ($filters['end_date'] ?? ''),
                'report_type' => $activeReportType,
            ],
            'filter_options' => $this->filterOptions(),
            'active_report_type' => $activeReportType,
            'report_tabs' => [
                self::REPORT_TYPE_BUILDINGS => [
                    'label' => 'المباني',
                    'item_label' => 'المباني',
                    'total_label' => 'عدد استمارات المباني الكلي',
                    'summary' => $reports[self::REPORT_TYPE_BUILDINGS]['summary'],
                ],
                self::REPORT_TYPE_HOUSING_UNITS => [
                    'label' => 'الوحدات',
                    'item_label' => 'الوحدات',
                    'total_label' => 'عدد الوحدات الكلي',
                    'summary' => $reports[self::REPORT_TYPE_HOUSING_UNITS]['summary'],
                ],
            ],
            'item_label' => $activeReportType === self::REPORT_TYPE_HOUSING_UNITS ? 'الوحدات' : 'المباني',
            'total_label' => $activeReportType === self::REPORT_TYPE_HOUSING_UNITS ? 'عدد الوحدات الكلي' : 'عدد استمارات المباني الكلي',
            'rows' => $activeReport['rows'],
            'summary' => $activeReport['summary'],
        ];
    }

    public function filterOptions(): array
    {
        return [
            'engineers' => Building::query()
                ->whereRaw('LOWER(TRIM(COALESCE(field_status, \'\'))) = ?', [self::COMPLETED_FIELD_STATUS])
                ->whereRaw("NULLIF(TRIM(COALESCE(assignedto, '')), '') IS NOT NULL")
                ->selectRaw('DISTINCT TRIM(assignedto) as assignedto')
                ->orderBy('assignedto')
                ->pluck('assignedto')
                ->values(),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function buildingRows(array $filters): Collection
    {
        return $this->rows($this->buildingBaseQuery($filters), $this->normalizedEngineerExpression());
    }

    private function housingUnitRows(array $filters): Collection
    {
        return $this->rows($this->housingUnitBaseQuery($filters), $this->normalizedEngineerExpression());
    }

    /**
     * @return array{rows: Collection<int, object>, summary: array{accepted_count: int, rejected_count: int, need_review_count: int, total_completed_count: int}}
     */
    private function buildReport(Collection $rows): array
    {
        return [
            'rows' => $rows,
            'summary' => [
                'accepted_count' => (int) $rows->sum('accepted_count'),
                'rejected_count' => (int) $rows->sum('rejected_count'),
                'need_review_count' => (int) $rows->sum('need_review_count'),
                'total_completed_count' => (int) $rows->sum('total_completed_count'),
            ],
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function rows(Builder $query, string $groupExpression): Collection
    {
        return $query
            ->groupByRaw($groupExpression)
            ->orderByDesc('accepted_count')
            ->orderByDesc('total_completed_count')
            ->orderBy('field_engineer_name')
            ->get()
            ->values()
            ->map(function (object $row, int $index): object {
                return (object) [
                    'sequence' => $index + 1,
                    'field_engineer_name' => (string) $row->field_engineer_name,
                    'accepted_count' => (int) $row->accepted_count,
                    'rejected_count' => (int) $row->rejected_count,
                    'need_review_count' => (int) $row->need_review_count,
                    'total_completed_count' => (int) $row->total_completed_count,
                ];
            });
    }

    private function buildingBaseQuery(array $filters): Builder
    {
        $acceptedPlaceholders = $this->placeholders(self::ACCEPTED_STATUS_NAMES);
        $rejectedPlaceholders = $this->placeholders(self::REJECTED_STATUS_NAMES);
        $needReviewPlaceholders = $this->placeholders(self::NEED_REVIEW_STATUS_NAMES);

        $query = Building::query()
            ->leftJoin('building_statuses', function ($join): void {
                $join->on('building_statuses.building_id', '=', 'buildings.objectid')
                    ->where('building_statuses.type', self::ENGINEER_AUDITOR_TYPE);
            })
            ->leftJoin('assessment_statuses', 'building_statuses.status_id', '=', 'assessment_statuses.id')
            ->whereRaw('LOWER(TRIM(COALESCE(buildings.field_status, \'\'))) = ?', [self::COMPLETED_FIELD_STATUS])
            ->selectRaw($this->normalizedEngineerExpression().' as field_engineer_name')
            ->selectRaw('COUNT(buildings.id) as total_completed_count')
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_statuses.name, ''))) IN ({$acceptedPlaceholders}) THEN 1 ELSE 0 END) as accepted_count",
                self::ACCEPTED_STATUS_NAMES,
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_statuses.name, ''))) IN ({$rejectedPlaceholders}) THEN 1 ELSE 0 END) as rejected_count",
                self::REJECTED_STATUS_NAMES,
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_statuses.name, ''))) IN ({$needReviewPlaceholders}) THEN 1 ELSE 0 END) as need_review_count",
                self::NEED_REVIEW_STATUS_NAMES,
            );

        $this->applyAssignedToFilter($query, $filters);

        $query
            ->whereDate('buildings.'.self::SUBMISSION_DATE_FIELD, '>=', (string) $filters['start_date'])
            ->whereDate('buildings.'.self::SUBMISSION_DATE_FIELD, '<=', (string) $filters['end_date']);

        return $query;
    }

    private function housingUnitBaseQuery(array $filters): Builder
    {
        $acceptedPlaceholders = $this->placeholders(self::ACCEPTED_STATUS_NAMES);
        $rejectedPlaceholders = $this->placeholders(self::REJECTED_STATUS_NAMES);
        $needReviewPlaceholders = $this->placeholders(self::NEED_REVIEW_STATUS_NAMES);
        $dateExpression = "COALESCE(NULLIF(housing_units.building_submit_date, ''), housing_units.creationdate)";

        $query = HousingUnit::query()
            ->join('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->leftJoin('housing_statuses', function ($join): void {
                $join->on('housing_statuses.housing_id', '=', 'housing_units.objectid')
                    ->where('housing_statuses.type', self::ENGINEER_AUDITOR_TYPE);
            })
            ->leftJoin('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->whereRaw('LOWER(TRIM(COALESCE(buildings.field_status, \'\'))) = ?', [self::COMPLETED_FIELD_STATUS])
            ->selectRaw($this->normalizedEngineerExpression().' as field_engineer_name')
            ->selectRaw('COUNT(housing_units.id) as total_completed_count')
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_statuses.name, ''))) IN ({$acceptedPlaceholders}) THEN 1 ELSE 0 END) as accepted_count",
                self::ACCEPTED_STATUS_NAMES,
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_statuses.name, ''))) IN ({$rejectedPlaceholders}) THEN 1 ELSE 0 END) as rejected_count",
                self::REJECTED_STATUS_NAMES,
            )
            ->selectRaw(
                "SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_statuses.name, ''))) IN ({$needReviewPlaceholders}) THEN 1 ELSE 0 END) as need_review_count",
                self::NEED_REVIEW_STATUS_NAMES,
            );

        $this->applyAssignedToFilter($query, $filters);

        $query
            ->whereDate(DB::raw($dateExpression), '>=', (string) $filters['start_date'])
            ->whereDate(DB::raw($dateExpression), '<=', (string) $filters['end_date']);

        return $query;
    }

    private function applyAssignedToFilter(Builder $query, array $filters): void
    {
        $assignedToValues = $this->filterValues($filters['assignedto'] ?? null);

        if ($assignedToValues === []) {
            return;
        }

        $assignedToPlaceholders = $this->placeholders($assignedToValues);

        $query->whereRaw(
            "TRIM(COALESCE(buildings.assignedto, '')) IN ({$assignedToPlaceholders})",
            $assignedToValues,
        );
    }

    private function resolveFilters(array $filters): array
    {
        $startDate = filled($filters['start_date'] ?? null)
            ? Carbon::parse((string) $filters['start_date'])->toDateString()
            : self::DEFAULT_START_DATE;

        $endDate = filled($filters['end_date'] ?? null)
            ? Carbon::parse((string) $filters['end_date'])->toDateString()
            : today()->toDateString();

        if (Carbon::parse($endDate)->lt(Carbon::parse($startDate))) {
            $endDate = $startDate;
        }

        return [
            ...$filters,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    private function normalizedEngineerExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(buildings.assignedto), ''), 'غير محدد')";
    }

    private function resolveReportType(mixed $reportType): string
    {
        return $reportType === self::REPORT_TYPE_HOUSING_UNITS
            ? self::REPORT_TYPE_HOUSING_UNITS
            : self::REPORT_TYPE_BUILDINGS;
    }

    private function firstFilterValue(mixed $value): string
    {
        return $this->filterValues($value)[0] ?? '';
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

    /**
     * @param  list<string>  $values
     */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
