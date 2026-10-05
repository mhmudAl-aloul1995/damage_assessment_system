<?php

declare(strict_types=1);

namespace App\Support\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommitteeReviewArchiveFilter
{
    private const BUILDING_DAMAGE_FIELD = 'building_damage_status';

    private const UNIT_DAMAGE_FIELD = 'unit_damage_status';

    private const BUILDING_COMMITTEE_STATUSES = [
        'committee_review',
        'committee_review2',
        'commite_review',
    ];

    private const UNIT_COMMITTEE_STATUSES = [
        'committee_review2',
        'committee_review',
        'commite_review',
    ];

    private const ARCHIVE_SOURCE_TYPES = [
        'committee_decision',
        'temporary_committee_excel_archive',
    ];

    /**
     * @param  array<int, mixed>  $values
     */
    public function isBuildingCommitteeFilter(string $field, array $values): bool
    {
        return $field === self::BUILDING_DAMAGE_FIELD
            && $this->committeeValues($values, self::BUILDING_COMMITTEE_STATUSES) !== [];
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public function isUnitCommitteeFilter(string $field, array $values): bool
    {
        return $field === self::UNIT_DAMAGE_FIELD
            && $this->committeeValues($values, self::UNIT_COMMITTEE_STATUSES) !== [];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function hasCommitteeFilter(array $params): bool
    {
        return $this->hasBuildingCommitteeFilter($params) || $this->hasUnitCommitteeFilter($params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function hasBuildingCommitteeFilter(array $params): bool
    {
        $filters = (array) ($params['filters'] ?? []);

        foreach ($filters as $field => $values) {
            $values = $this->normalizedValues((array) $values);

            if ($this->isBuildingCommitteeFilter((string) $field, $values)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function hasUnitCommitteeFilter(array $params): bool
    {
        $filters = (array) ($params['filters'] ?? []);

        foreach ($filters as $field => $values) {
            $values = $this->normalizedValues((array) $values);

            if ($this->isUnitCommitteeFilter((string) $field, $values)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<string, mixed>  $params
     */
    public function applyBuildingDamageFilter(Builder $query, string $statusExpression, string $objectIdColumn, array $values, array $params): void
    {
        $this->applyCommitteeAwareFilter(
            query: $query,
            statusExpression: $statusExpression,
            objectIdColumn: $objectIdColumn,
            values: $values,
            committeeStatuses: self::BUILDING_COMMITTEE_STATUSES,
            archiveObjectIdColumn: 'building_objectid',
            archiveSnapshotStatusPath: 'building_snapshot->building_damage_status',
            params: $params,
            isHousingUnit: false,
        );
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<string, mixed>  $params
     */
    public function applyUnitDamageFilter(Builder $query, string $statusExpression, string $objectIdColumn, array $values, array $params): void
    {
        $this->applyCommitteeAwareFilter(
            query: $query,
            statusExpression: $statusExpression,
            objectIdColumn: $objectIdColumn,
            values: $values,
            committeeStatuses: self::UNIT_COMMITTEE_STATUSES,
            archiveObjectIdColumn: 'housing_unit_objectid',
            archiveSnapshotStatusPath: 'housing_unit_snapshot->unit_damage_status',
            params: $params,
            isHousingUnit: true,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function applyBuildingEndFilter(Builder $query, string $currentEndExpression, array $params, bool $includeHousing = false): void
    {
        $from = $this->filledString($params['building_end_from'] ?? null);
        $to = $this->filledString($params['building_end_to'] ?? null);

        if ($from === null && $to === null) {
            return;
        }

        if (! $this->hasCommitteeFilter($params) || ! $this->canUseArchive()) {
            $this->applyCurrentBuildingEndFilter($query, $currentEndExpression, $from, $to);

            return;
        }

        $hasBuildingCommitteeFilter = $this->hasBuildingCommitteeFilter($params);
        $hasUnitCommitteeFilter = $includeHousing && $this->hasUnitCommitteeFilter($params);

        $query->where(function (Builder $dateQuery) use ($currentEndExpression, $from, $to, $hasBuildingCommitteeFilter, $hasUnitCommitteeFilter): void {
            $dateQuery->where(function (Builder $currentDateQuery) use ($currentEndExpression, $from, $to): void {
                $this->applyCurrentBuildingEndFilter($currentDateQuery, $currentEndExpression, $from, $to);
            });

            if ($hasBuildingCommitteeFilter) {
                $dateQuery->orWhereExists(function (Builder $archiveQuery) use ($from, $to): void {
                    $archiveQuery
                        ->select(DB::raw(1))
                        ->from('building_survey_archive_objects as committee_archives')
                        ->whereColumn('committee_archives.building_objectid', 'b.objectid')
                        ->whereNull('committee_archives.housing_unit_objectid')
                        ->whereIn('committee_archives.source_type', self::ARCHIVE_SOURCE_TYPES)
                        ->whereIn('committee_archives.building_snapshot->building_damage_status', self::BUILDING_COMMITTEE_STATUSES);

                    $this->applyArchiveDateFilter($archiveQuery, $from, $to, [
                        'building_snapshot->end',
                        'building_snapshot->submission_date',
                    ]);
                });
            }

            if ($hasUnitCommitteeFilter) {
                $dateQuery->orWhereExists(function (Builder $archiveQuery) use ($from, $to): void {
                    $archiveQuery
                        ->select(DB::raw(1))
                        ->from('building_survey_archive_objects as committee_archives')
                        ->whereColumn('committee_archives.housing_unit_objectid', 'h.objectid')
                        ->whereIn('committee_archives.source_type', self::ARCHIVE_SOURCE_TYPES)
                        ->whereIn('committee_archives.housing_unit_snapshot->unit_damage_status', self::UNIT_COMMITTEE_STATUSES);

                    $this->applyArchiveDateFilter($archiveQuery, $from, $to, [
                        'housing_unit_snapshot->building_submit_date',
                        'building_snapshot->end',
                        'building_snapshot->submission_date',
                    ]);
                });
            }
        });
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $committeeStatuses
     * @param  array<string, mixed>  $params
     */
    private function applyCommitteeAwareFilter(
        Builder $query,
        string $statusExpression,
        string $objectIdColumn,
        array $values,
        array $committeeStatuses,
        string $archiveObjectIdColumn,
        string $archiveSnapshotStatusPath,
        array $params,
        bool $isHousingUnit,
    ): void {
        $values = $this->normalizedValues($values);
        $committeeValues = $this->committeeValues($values, $committeeStatuses);
        $nonCommitteeValues = array_values(array_diff($values, $committeeValues));

        if ($committeeValues === []) {
            $query->whereIn(DB::raw($statusExpression), $values);

            return;
        }

        $query->where(function (Builder $filterQuery) use ($statusExpression, $objectIdColumn, $committeeValues, $nonCommitteeValues, $archiveObjectIdColumn, $archiveSnapshotStatusPath, $params, $isHousingUnit): void {
            if ($nonCommitteeValues !== []) {
                $filterQuery->whereIn(DB::raw($statusExpression), $nonCommitteeValues);
            }

            $method = $nonCommitteeValues === [] ? 'where' : 'orWhere';

            $filterQuery->{$method}(function (Builder $committeeQuery) use ($statusExpression, $objectIdColumn, $committeeValues, $archiveObjectIdColumn, $archiveSnapshotStatusPath, $params, $isHousingUnit): void {
                $committeeQuery->whereIn(DB::raw($statusExpression), $committeeValues);

                if (! $this->canUseArchive()) {
                    return;
                }

                $committeeQuery->orWhereExists(function (Builder $archiveQuery) use ($objectIdColumn, $committeeValues, $archiveObjectIdColumn, $archiveSnapshotStatusPath, $params, $isHousingUnit): void {
                    $archiveQuery
                        ->select(DB::raw(1))
                        ->from('building_survey_archive_objects as committee_archives')
                        ->whereColumn("committee_archives.{$archiveObjectIdColumn}", $objectIdColumn)
                        ->whereIn('committee_archives.source_type', self::ARCHIVE_SOURCE_TYPES)
                        ->whereIn("committee_archives.{$archiveSnapshotStatusPath}", $committeeValues);

                    if (! $isHousingUnit) {
                        $archiveQuery->whereNull('committee_archives.housing_unit_objectid');
                    }

                    $from = $this->filledString($params['building_end_from'] ?? null);
                    $to = $this->filledString($params['building_end_to'] ?? null);

                    if ($from !== null || $to !== null) {
                        $this->applyArchiveDateFilter(
                            $archiveQuery,
                            $from,
                            $to,
                            $isHousingUnit
                                ? ['housing_unit_snapshot->building_submit_date', 'building_snapshot->end', 'building_snapshot->submission_date']
                                : ['building_snapshot->end', 'building_snapshot->submission_date'],
                        );
                    }
                });
            });
        });
    }

    private function applyCurrentBuildingEndFilter(Builder $query, string $currentEndExpression, ?string $from, ?string $to): void
    {
        if ($from !== null) {
            $query->whereDate(DB::raw($currentEndExpression), '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate(DB::raw($currentEndExpression), '<=', $to);
        }
    }

    /**
     * @param  array<int, string>  $jsonDatePaths
     */
    private function applyArchiveDateFilter(Builder $query, ?string $from, ?string $to, array $jsonDatePaths): void
    {
        $query->where(function (Builder $dateQuery) use ($from, $to, $jsonDatePaths): void {
            foreach ($jsonDatePaths as $index => $jsonDatePath) {
                $method = $index === 0 ? 'where' : 'orWhere';

                $dateQuery->{$method}(function (Builder $pathQuery) use ($from, $to, $jsonDatePath): void {
                    if ($from !== null) {
                        $pathQuery->whereDate("committee_archives.{$jsonDatePath}", '>=', $from);
                    }

                    if ($to !== null) {
                        $pathQuery->whereDate("committee_archives.{$jsonDatePath}", '<=', $to);
                    }
                });
            }
        });
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizedValues(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $committeeStatuses
     * @return array<int, string>
     */
    private function committeeValues(array $values, array $committeeStatuses): array
    {
        return array_values(array_intersect($this->normalizedValues($values), $committeeStatuses));
    }

    private function filledString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function canUseArchive(): bool
    {
        return Schema::hasTable('building_survey_archive_objects');
    }
}
