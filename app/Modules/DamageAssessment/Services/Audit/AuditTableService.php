<?php

namespace App\Modules\DamageAssessment\Services\Audit;

use App\Models\Building;
use App\Models\HousingUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditTableService
{
    private const ACCEPTED_BUILDING_STATUS_NAMES = [
        'accepted_by_engineer',
        'accepted_by_lawyer',
        'final_approval',
    ];

    private const UNEVALUATED_UNIT_STATUS_NAMES = [
        'assigned_to_engineer',
        'assigned_to_lawyer',
        'need_review',
    ];

    /**
     * @var list<string>
     */
    private const ADVANCED_BUILDING_FILTERS = [
        'building_status_visit',
        'building_debris_exist',
        'building_debris_qty',
        'building_debris_blocking',
        'assessment_obstacle',
        'uxo_present',
        'bodies_present',
        'building_type',
        'building_use',
        'building_material',
        'building_age',
        'building_roof_type',
        'building_ownership',
        'owner_status',
        'building_responsible',
        'building_authorization',
        'has_elevator',
        'elevator_status',
        'has_solar',
        'solar_damage_status',
        'has_well',
        'well_damage_status',
        'has_fence',
        'fence_damage_status',
        'has_parking',
        'parking_status',
    ];

    public function applyFilters(Builder $query, Request $request, bool $includeAssignmentFilters = true): void
    {
        if ($includeAssignmentFilters) {
            $engineerIds = $this->filterValues($request, 'engineer_id');
            if ($engineerIds !== []) {
                $query->whereHas('engineerAssignment', function (Builder $assignmentQuery) use ($engineerIds): void {
                    $assignmentQuery->whereIn('user_id', $engineerIds);
                });
            }

            $lawyerIds = $this->filterValues($request, 'lawyer_id');
            if ($lawyerIds !== []) {
                $query->whereHas('lawyerAssignment', function (Builder $assignmentQuery) use ($lawyerIds): void {
                    $assignmentQuery->whereIn('user_id', $lawyerIds);
                });
            }
        }

        $statusMap = [
            'assigned_to_engineer' => 'assigned_to_engineer',
            'assigned_to_lawyer' => 'assigned_to_lawyer',
        ];

        $this->applyStatusValuesFilter($query, 'engineerStatus', $this->filterValues($request, 'eng_status', $statusMap));
        $this->applyStatusValuesFilter($query, 'lawyerStatus', $this->filterValues($request, 'legal_status', $statusMap));

        if ($request->boolean('accepted_with_unevaluated_units')) {
            $this->applyAcceptedWithUnevaluatedUnitsFilter($query);
        }

        if ($request->boolean('floor_area_mismatch')) {
            $this->applyFloorAreaMismatchFilter($query);
        }

        if ($request->boolean('buildings_without_units')) {
            $this->applyBuildingsWithoutUnitsFilter($query);
        }

        $damageStatuses = $this->filterValues($request, 'damage_status');
        if ($damageStatuses !== []) {
            $query->whereIn('building_damage_status', $damageStatuses);
        }

        $fieldStatuses = $this->filterValues($request, 'field_status');
        if ($fieldStatuses === []) {
            $fieldStatuses = ['completed'];
        }

        if (! in_array('all', $fieldStatuses, true)) {
            $query->whereIn(DB::raw('LOWER(TRIM(field_status))'), $fieldStatuses);
        }

        $legalChallenges = $this->filterValues($request, 'legal_challenge');
        if ($legalChallenges !== []) {
            $query->whereIn('legal_challenge', $legalChallenges);
        }

        $fieldEngineers = $this->filterValues($request, 'field_engineer');
        if ($fieldEngineers !== []) {
            $query->whereIn(DB::raw('LOWER(TRIM(assignedto))'), $fieldEngineers);
        }

        $finalStatuses = $this->filterValues($request, 'final_status');
        if ($finalStatuses !== []) {
            $query->whereHas('finalApproval.assessment_status', function (Builder $statusQuery) use ($finalStatuses): void {
                $statusQuery->whereIn(DB::raw('LOWER(TRIM(name))'), $finalStatuses);
            });
        }

        if ($request->filled('building_name')) {
            $query->where('building_name', 'like', '%'.$request->string('building_name').'%');
        }

        if ($request->filled('objectid')) {
            $query->where('objectid', '=', trim((string) $request->input('objectid')));
        }

        if ($request->filled('area')) {
            $query->where('neighborhood', 'like', '%'.$request->string('area').'%');
        }

        if ($request->filled('filter_from_date')) {
            $query->whereDate('creationdate', '>=', $request->input('filter_from_date'));
        }

        if ($request->filled('filter_to_date')) {
            $query->whereDate('buildings.creationdate', '<=', $request->input('filter_to_date'));
        }

        $this->applyAdvancedBuildingFilters($query, $request);
    }

    public function applyStatusDateFilters(Builder $query, Request $request): void
    {
        if (! $request->filled('status_from_date') && ! $request->filled('status_to_date')) {
            return;
        }

        $query->whereExists(function ($statusQuery) use ($request): void {
            $statusQuery->selectRaw('1')
                ->from('building_statuses as bs')
                ->whereColumn('bs.building_id', 'buildings.objectid');

            if ($request->filled('status_from_date')) {
                $statusQuery->whereDate('bs.updated_at', '>=', $request->input('status_from_date'));
            }

            if ($request->filled('status_to_date')) {
                $statusQuery->whereDate('bs.updated_at', '<=', $request->input('status_to_date'));
            }
        });
    }

    /**
     * @return array{housing_units_count: int, housing_units_with_status_count: int}
     */
    public function housingStatusCountsForBuilding(string $buildingGlobalId): array
    {
        if ($buildingGlobalId === '') {
            return [
                'housing_units_count' => 0,
                'housing_units_with_status_count' => 0,
            ];
        }

        $counts = HousingUnit::query()
            ->leftJoin('housing_statuses', function ($join): void {
                $join->on('housing_statuses.housing_id', '=', 'housing_units.objectid')
                    ->where('housing_statuses.status_id', 4);
            })
            ->where('housing_units.parentglobalid', $buildingGlobalId)
            ->selectRaw('COUNT(DISTINCT housing_units.objectid) as housing_units_count')
            ->selectRaw('COUNT(DISTINCT housing_statuses.housing_id) as housing_units_with_status_count')
            ->first();

        return [
            'housing_units_count' => (int) ($counts?->housing_units_count ?? 0),
            'housing_units_with_status_count' => (int) ($counts?->housing_units_with_status_count ?? 0),
        ];
    }

    public function floorAreaMismatchRows(Request $request): Collection
    {
        $query = Building::query()
            ->select('buildings.objectid')
            ->selectRaw($this->floorAreaDifferenceSql('0', 'ground_floor_area__m2').' as ground_floor_difference')
            ->selectRaw($this->floorAreaDifferenceSql('1', 'floor_area_m2').' as repeated_floor_difference');

        $request->merge(['floor_area_mismatch' => '1']);

        $this->applyFilters($query, $request);
        $this->applyStatusDateFilters($query, $request);

        return $query
            ->orderBy('buildings.objectid')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function filterValues(Request $request, string $key, array $map = []): array
    {
        $values = $request->input($key, []);

        if (! is_array($values)) {
            $values = [$values];
        }

        return collect($values)
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => $value !== '')
            ->map(fn (string $value): string => $map[$value] ?? $value)
            ->unique()
            ->values()
            ->all();
    }

    private function applyAdvancedBuildingFilters(Builder $query, Request $request): void
    {
        $advancedFilters = $request->input('advanced_filters', []);

        if (! is_array($advancedFilters)) {
            return;
        }

        foreach (self::ADVANCED_BUILDING_FILTERS as $field) {
            $values = $advancedFilters[$field] ?? [];

            if (! is_array($values)) {
                $values = [$values];
            }

            $values = collect($values)
                ->map(fn ($value): string => trim((string) $value))
                ->filter(fn (string $value): bool => $value !== '')
                ->unique()
                ->values()
                ->all();

            if ($values !== []) {
                $query->whereIn($field, $values);
            }
        }

        foreach (['floor_nos', 'units_nos', 'damaged_units_nos'] as $field) {
            $from = $advancedFilters[$field.'_from'] ?? null;
            $to = $advancedFilters[$field.'_to'] ?? null;

            if ($from !== null && $from !== '') {
                $query->where($field, '>=', $from);
            }

            if ($to !== null && $to !== '') {
                $query->where($field, '<=', $to);
            }
        }
    }

    /**
     * @param  array<int, string>  $values
     */
    private function applyStatusValuesFilter(Builder $query, string $relation, array $values): void
    {
        if ($values === []) {
            return;
        }

        $query->where(function ($statusQuery) use ($relation, $values): void {
            $statusValues = array_values(array_filter($values, fn (string $value): bool => $value !== 'pending'));
            $hasPending = in_array('pending', $values, true);

            if ($hasPending) {
                $statusQuery->whereDoesntHave($relation);
            }

            if ($statusValues !== []) {
                $method = $hasPending ? 'orWhereHas' : 'whereHas';

                $statusQuery->{$method}($relation.'.status', function ($q) use ($statusValues): void {
                    $q->whereIn(DB::raw('LOWER(TRIM(name))'), $statusValues);
                });
            }
        });
    }

    private function applyAcceptedWithUnevaluatedUnitsFilter(Builder $query): void
    {
        $query
            ->whereExists(function ($statusQuery): void {
                $statusQuery->selectRaw('1')
                    ->from('building_statuses as accepted_building_statuses')
                    ->join('assessment_statuses as accepted_statuses', 'accepted_building_statuses.status_id', '=', 'accepted_statuses.id')
                    ->whereColumn('accepted_building_statuses.building_id', 'buildings.objectid')
                    ->whereIn(DB::raw('LOWER(TRIM(accepted_statuses.name))'), self::ACCEPTED_BUILDING_STATUS_NAMES);
            })
            ->whereExists(function ($unitQuery): void {
                $unitQuery->selectRaw('1')
                    ->from('housing_units as unevaluated_units')
                    ->whereColumn('unevaluated_units.parentglobalid', 'buildings.globalid')
                    ->where(function ($unitStatusQuery): void {
                        $unitStatusQuery
                            ->whereNotExists(function ($housingStatusQuery): void {
                                $housingStatusQuery->selectRaw('1')
                                    ->from('housing_statuses as any_housing_status')
                                    ->whereColumn('any_housing_status.housing_id', 'unevaluated_units.objectid');
                            })
                            ->orWhereExists(function ($housingStatusQuery): void {
                                $housingStatusQuery->selectRaw('1')
                                    ->from('housing_statuses as unevaluated_housing_statuses')
                                    ->join('assessment_statuses as unevaluated_statuses', 'unevaluated_housing_statuses.status_id', '=', 'unevaluated_statuses.id')
                                    ->whereColumn('unevaluated_housing_statuses.housing_id', 'unevaluated_units.objectid')
                                    ->whereIn(DB::raw('LOWER(TRIM(unevaluated_statuses.name))'), self::UNEVALUATED_UNIT_STATUS_NAMES);
                            });
                    });
            });
    }

    private function applyFloorAreaMismatchFilter(Builder $query): void
    {
        $query
            ->whereRaw($this->buildingFullDamageSql())
            ->where(function (Builder $mismatchQuery): void {
                $mismatchQuery
                    ->whereRaw($this->floorAreaMismatchSql('0', 'ground_floor_area__m2'))
                    ->orWhereRaw($this->floorAreaMismatchSql('1', 'floor_area_m2'));
            });
    }

    private function applyBuildingsWithoutUnitsFilter(Builder $query): void
    {
        $query->whereNotExists(function ($unitQuery): void {
            $unitQuery->selectRaw('1')
                ->from('housing_units')
                ->whereColumn('housing_units.parentglobalid', 'buildings.globalid');
        });
    }

    private function buildingFullDamageSql(): string
    {
        return "LOWER(TRIM(COALESCE((
            SELECT damage_status_edits.field_value
            FROM edit_assessments AS damage_status_edits
            WHERE damage_status_edits.type = 'building_table'
                AND damage_status_edits.field_name = 'building_damage_status'
                AND damage_status_edits.global_id = buildings.globalid
            ORDER BY damage_status_edits.id DESC
            LIMIT 1
        ), buildings.building_damage_status, ''))) = 'fully_damaged'";
    }

    private function floorAreaMismatchSql(string $floorNumber, string $buildingAreaField): string
    {
        return 'ABS('.$this->floorAreaDifferenceSql($floorNumber, $buildingAreaField).') > 0.01';
    }

    private function floorAreaDifferenceSql(string $floorNumber, string $buildingAreaField): string
    {
        return $this->floorAreaUnitsTotalSql($floorNumber).' - '.$this->buildingAreaSql($buildingAreaField);
    }

    private function floorAreaUnitsTotalSql(string $floorNumber): string
    {
        $floorValue = $this->sqlQuote($floorNumber);

        return "COALESCE((
                SELECT SUM(COALESCE((
                    SELECT area_edits.field_value
                    FROM edit_assessments AS area_edits
                    WHERE area_edits.type = 'housing_table'
                        AND area_edits.field_name = 'damaged_area_m2'
                        AND area_edits.global_id = floor_area_units.globalid
                    ORDER BY area_edits.id DESC
                    LIMIT 1
                ), floor_area_units.damaged_area_m2, 0) + 0)
                FROM housing_units AS floor_area_units
                WHERE floor_area_units.parentglobalid = buildings.globalid
                    AND LOWER(TRIM(COALESCE((
                        SELECT floor_edits.field_value
                        FROM edit_assessments AS floor_edits
                        WHERE floor_edits.type = 'housing_table'
                            AND floor_edits.field_name = 'floor_number'
                            AND floor_edits.global_id = floor_area_units.globalid
                        ORDER BY floor_edits.id DESC
                        LIMIT 1
                    ), floor_area_units.floor_number, ''))) = {$floorValue}
            ), 0)";
    }

    private function buildingAreaSql(string $buildingAreaField): string
    {
        $buildingAreaFieldValue = $this->sqlQuote($buildingAreaField);

        return "(COALESCE((
                SELECT building_area_edits.field_value
                FROM edit_assessments AS building_area_edits
                WHERE building_area_edits.type = 'building_table'
                    AND building_area_edits.field_name = {$buildingAreaFieldValue}
                    AND building_area_edits.global_id = buildings.globalid
                ORDER BY building_area_edits.id DESC
                LIMIT 1
            ), buildings.{$buildingAreaField}, 0) + 0)";
    }

    private function sqlQuote(string $value): string
    {
        return DB::getPdo()->quote($value);
    }
}
