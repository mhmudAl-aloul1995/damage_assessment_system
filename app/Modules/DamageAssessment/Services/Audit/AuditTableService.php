<?php

namespace App\Modules\DamageAssessment\Services\Audit;

use App\Models\HousingUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditTableService
{
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

        $damageStatuses = $this->filterValues($request, 'damage_status');
        if ($damageStatuses !== []) {
            $query->whereIn('building_damage_status', $damageStatuses);
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
}
