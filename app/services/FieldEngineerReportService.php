<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FieldEngineerReportService
{
    private ?Collection $fieldLabels = null;

    private ?bool $forceCollation = null;

    public function filterOptions(): array
    {
        return [
            'engineers' => Cache::remember('field-engineer-report:engineers', now()->addMinutes(30), function () {
                return DB::table('buildings')
                    ->select('assignedto')
                    ->whereNotNull('assignedto')
                    ->where('assignedto', '!=', '')
                    ->distinct()
                    ->orderBy('assignedto')
                    ->pluck('assignedto')
                    ->values()
                    ->all();
            }),
            'municipalities' => Cache::remember('field-engineer-report:municipalities', now()->addMinutes(30), function () {
                return DB::table('buildings')
                    ->select('municipalitie')
                    ->whereNotNull('municipalitie')
                    ->where('municipalitie', '!=', '')
                    ->distinct()
                    ->orderBy('municipalitie')
                    ->pluck('municipalitie')
                    ->values()
                    ->all();
            }),
            'neighborhoods' => Cache::remember('field-engineer-report:neighborhoods', now()->addMinutes(30), function () {
                return DB::table('buildings')
                    ->select('neighborhood')
                    ->whereNotNull('neighborhood')
                    ->where('neighborhood', '!=', '')
                    ->distinct()
                    ->orderBy('neighborhood')
                    ->pluck('neighborhood')
                    ->values()
                    ->all();
            }),
            'building_damage_statuses' => DB::table('buildings')
                ->select('building_damage_status')
                ->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '')
                ->distinct()
                ->orderBy('building_damage_status')
                ->pluck('building_damage_status')
                ->values()
                ->all(),
            'engineer_statuses' => $this->statusOptionsByStage('engineer'),
            'legal_statuses' => $this->statusOptionsByStage('lawyer'),
            'final_statuses' => $this->statusOptionsByStage('team_leader'),
        ];
    }

    public function normalizeFilters(array $validated): array
    {
        return [
            'assignedto' => $this->normalizeString($validated['assignedto'] ?? null),
            'municipalitie' => $this->normalizeString($validated['municipalitie'] ?? null),
            'neighborhood' => $this->normalizeString($validated['neighborhood'] ?? null),
            'building_damage_status' => $this->normalizeString($validated['building_damage_status'] ?? null),
            'engineer_status' => $this->normalizeString($validated['engineer_status'] ?? null),
            'legal_status' => $this->normalizeString($validated['legal_status'] ?? null),
            'final_status' => $this->normalizeString($validated['final_status'] ?? null),
            'from_date' => $this->normalizeString($validated['from_date'] ?? null),
            'to_date' => $this->normalizeString($validated['to_date'] ?? null),
            'search' => $this->normalizeString($validated['search'] ?? null),
        ];
    }

    public function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if ($key !== 'tab' && filled($value)) {
                return true;
            }
        }

        return false;
    }

    public function summary(array $filters): array
    {
        if (! $this->hasActiveFilters($filters)) {
            return $this->emptySummary();
        }

        $cacheKey = 'field-engineer-report:summary:'.md5(json_encode($filters));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($filters) {
            return $this->calculateSummary($filters);
        });
    }

    private function calculateSummary(array $filters): array
    {
        $buildingIdentifiersQuery = $this->filteredBuildingIdentifiersQuery($filters);
        $housingIdentifiersQuery = $this->filteredHousingIdentifiersQuery($filters);
        $totalBuildings = DB::query()
            ->fromSub($buildingIdentifiersQuery, 'filtered_buildings')
            ->count();

        $completedBuildings = DB::query()
            ->fromSub($buildingIdentifiersQuery, 'filtered_buildings')
            ->join('buildings', 'buildings.id', '=', 'filtered_buildings.id')
            ->where('buildings.field_status', 'COMPLETED')
            ->count();

        $notCompletedBuildings = DB::query()
            ->fromSub($buildingIdentifiersQuery, 'filtered_buildings')
            ->join('buildings', 'buildings.id', '=', 'filtered_buildings.id')
            ->where(function ($query) {
                $query->whereNull('buildings.field_status')
                    ->orWhere('buildings.field_status', '!=', 'COMPLETED');
            })
            ->count();

        $damagedBuildings = DB::query()
            ->fromSub($buildingIdentifiersQuery, 'filtered_buildings')
            ->join('buildings', 'buildings.id', '=', 'filtered_buildings.id')
            ->leftJoinSub($this->latestEditValueSubquery('building_table', 'building_damage_status'), 'edit_building_damage_status', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_damage_status.global_id', 'buildings.globalid')))
            ->whereNotNull(DB::raw('COALESCE(edit_building_damage_status.field_value, buildings.building_damage_status)'))
            ->whereNotIn(DB::raw('COALESCE(edit_building_damage_status.field_value, buildings.building_damage_status)'), ['no_damage', 'no_damage2', ''])
            ->count();

        $lastUpdatedAt = DB::query()
            ->fromSub($buildingIdentifiersQuery, 'filtered_buildings')
            ->join('buildings', 'buildings.id', '=', 'filtered_buildings.id')
            ->max(DB::raw('COALESCE(buildings.editdate, buildings.creationdate)'));

        $finalStatusesBase = DB::query()
            ->fromSub($buildingIdentifiersQuery, 'filtered_buildings')
            ->joinSub($this->buildingStatusSubquery(null, 'team_leader', 'final'), 'final_statuses', fn ($join) => $join->on('final_statuses.building_id', '=', 'filtered_buildings.objectid'));

        $acceptedStatuses = (clone $finalStatusesBase)
            ->where('final_statuses.status_name', 'like', '%accept%')
            ->count();

        $rejectedStatuses = (clone $finalStatusesBase)
            ->where('final_statuses.status_name', 'like', '%reject%')
            ->count();

        $needReviewStatuses = (clone $finalStatusesBase)
            ->where('final_statuses.status_name', 'need_review')
            ->count();

        $totalHousingUnits = DB::query()
            ->fromSub($housingIdentifiersQuery, 'filtered_housing_units')
            ->count();

        $damagedHousingUnits = DB::query()
            ->fromSub($housingIdentifiersQuery, 'filtered_housing_units')
            ->join('housing_units', 'housing_units.id', '=', 'filtered_housing_units.id')
            ->leftJoinSub($this->latestEditValueSubquery('housing_table', 'unit_damage_status'), 'edit_housing_damage', fn ($join) => $join->whereRaw($this->collatedEquals('edit_housing_damage.global_id', 'housing_units.globalid')))
            ->whereNotNull(DB::raw('COALESCE(edit_housing_damage.field_value, housing_units.unit_damage_status)'))
            ->whereNotIn(DB::raw('COALESCE(edit_housing_damage.field_value, housing_units.unit_damage_status)'), ['no_damage', 'no_damage2', ''])
            ->count();

        $buildingEdits = DB::table('edit_assessments')
            ->where('edit_assessments.type', 'building_table')
            ->whereExists(function ($existsQuery) use ($filters) {
                $existsQuery->fromSub($this->filteredBuildingIdentifiersQuery($filters), 'filtered_buildings')
                    ->selectRaw('1')
                    ->whereRaw($this->collatedEquals('filtered_buildings.globalid', 'edit_assessments.global_id'));
            })
            ->count();

        $housingEdits = DB::table('edit_assessments')
            ->where('edit_assessments.type', 'housing_table')
            ->whereExists(function ($existsQuery) use ($filters) {
                $existsQuery->fromSub($this->filteredHousingIdentifiersQuery($filters), 'filtered_housing')
                    ->selectRaw('1')
                    ->whereRaw($this->collatedEquals('filtered_housing.globalid', 'edit_assessments.global_id'));
            })
            ->count();

        return [
            'total_buildings' => $totalBuildings,
            'total_housing_units' => $totalHousingUnits,
            'damaged_buildings' => $damagedBuildings,
            'damaged_housing_units' => $damagedHousingUnits,
            'building_edits' => $buildingEdits,
            'housing_edits' => $housingEdits,
            'accepted_statuses' => $acceptedStatuses,
            'rejected_statuses' => $rejectedStatuses,
            'need_review_statuses' => $needReviewStatuses,
            'last_updated_at' => $lastUpdatedAt,
            'completion_rate' => $totalBuildings > 0 ? round(($completedBuildings / $totalBuildings) * 100, 1) : 0.0,
            'completed_buildings' => $completedBuildings,
            'not_completed_buildings' => $notCompletedBuildings,
        ];
    }

    public function emptySummary(): array
    {
        return [
            'total_buildings' => 0,
            'total_housing_units' => 0,
            'damaged_buildings' => 0,
            'damaged_housing_units' => 0,
            'building_edits' => 0,
            'housing_edits' => 0,
            'accepted_statuses' => 0,
            'rejected_statuses' => 0,
            'need_review_statuses' => 0,
            'last_updated_at' => null,
            'completion_rate' => 0.0,
            'completed_buildings' => 0,
            'not_completed_buildings' => 0,
        ];
    }

    public function filteredBuildingsQuery(array $filters): Builder
    {
        $municipalityEdit = $this->latestEditValueSubquery('building_table', 'municipalitie');
        $neighborhoodEdit = $this->latestEditValueSubquery('building_table', 'neighborhood');
        $damageStatusEdit = $this->latestEditValueSubquery('building_table', 'building_damage_status');
        $buildingUseEdit = $this->latestEditValueSubquery('building_table', 'building_use');
        $buildingNameEdit = $this->latestEditValueSubquery('building_table', 'building_name');
        $finalStatus = $this->buildingStatusSubquery(null, 'team_leader', 'final');
        $includeEngineerStatus = (bool) $filters['engineer_status'];
        $includeLegalStatus = (bool) $filters['legal_status'];

        $query = DB::table('buildings')
            ->leftJoinSub($municipalityEdit, 'edit_municipalitie', fn ($join) => $join->whereRaw($this->collatedEquals('edit_municipalitie.global_id', 'buildings.globalid')))
            ->leftJoinSub($neighborhoodEdit, 'edit_neighborhood', fn ($join) => $join->whereRaw($this->collatedEquals('edit_neighborhood.global_id', 'buildings.globalid')))
            ->leftJoinSub($damageStatusEdit, 'edit_building_damage_status', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_damage_status.global_id', 'buildings.globalid')))
            ->leftJoinSub($buildingUseEdit, 'edit_building_use', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_use.global_id', 'buildings.globalid')))
            ->leftJoinSub($buildingNameEdit, 'edit_building_name', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_name.global_id', 'buildings.globalid')))
            ->leftJoinSub($finalStatus, 'final_statuses', fn ($join) => $join->on('final_statuses.building_id', '=', 'buildings.objectid'))
            ->select([
                'buildings.id',
                'buildings.objectid',
                'buildings.globalid',
                'buildings.assignedto',
                'buildings.parcel_no1',
                'buildings.creationdate',
                'buildings.editdate',
                'buildings.field_status',
                DB::raw('COALESCE(edit_building_name.field_value, buildings.building_name) as building_name'),
                DB::raw('COALESCE(edit_municipalitie.field_value, buildings.municipalitie) as municipalitie'),
                DB::raw('COALESCE(edit_neighborhood.field_value, buildings.neighborhood) as neighborhood'),
                DB::raw('COALESCE(edit_building_use.field_value, buildings.building_use) as building_use'),
                DB::raw('COALESCE(edit_building_damage_status.field_value, buildings.building_damage_status) as building_damage_status'),
                DB::raw(($includeEngineerStatus ? 'engineer_statuses.status_name' : 'null').' as engineer_status_name'),
                DB::raw(($includeEngineerStatus ? 'engineer_statuses.status_label' : 'null').' as engineer_status_label'),
                DB::raw(($includeLegalStatus ? 'legal_statuses.status_name' : 'null').' as legal_status_name'),
                DB::raw(($includeLegalStatus ? 'legal_statuses.status_label' : 'null').' as legal_status_label'),
                DB::raw('final_statuses.status_name as final_status_name'),
                DB::raw('final_statuses.status_label as final_status_label'),
            ]);

        if ($includeEngineerStatus) {
            $engineerStatus = $this->buildingStatusSubquery('QC/QA Engineer', null, 'engineer');
            $query->leftJoinSub($engineerStatus, 'engineer_statuses', fn ($join) => $join->on('engineer_statuses.building_id', '=', 'buildings.objectid'));
        }

        if ($includeLegalStatus) {
            $legalStatus = $this->buildingStatusSubquery('Legal Auditor', null, 'legal');
            $query->leftJoinSub($legalStatus, 'legal_statuses', fn ($join) => $join->on('legal_statuses.building_id', '=', 'buildings.objectid'));
        }

        return $this->applyBuildingFilters($query, $filters);
    }

    public function filteredHousingUnitsQuery(array $filters): Builder
    {
        $housingTypeEdit = $this->latestEditValueSubquery('housing_table', 'housing_unit_type');
        $housingDamageEdit = $this->latestEditValueSubquery('housing_table', 'unit_damage_status');
        $housingOccupiedEdit = $this->latestEditValueSubquery('housing_table', 'occupied');
        $includeMunicipality = (bool) $filters['municipalitie'];
        $includeNeighborhood = (bool) $filters['neighborhood'];
        $includeBuildingDamage = (bool) $filters['building_damage_status'];
        $includeEngineerStatus = (bool) $filters['engineer_status'];
        $includeLegalStatus = (bool) $filters['legal_status'];
        $includeFinalStatus = (bool) $filters['final_status'];

        $query = DB::table('housing_units')
            ->join('buildings', fn ($join) => $join->whereRaw($this->collatedEquals('housing_units.parentglobalid', 'buildings.globalid')))
            ->leftJoinSub($housingTypeEdit, 'housing_edit_type', fn ($join) => $join->whereRaw($this->collatedEquals('housing_edit_type.global_id', 'housing_units.globalid')))
            ->leftJoinSub($housingDamageEdit, 'housing_edit_damage', fn ($join) => $join->whereRaw($this->collatedEquals('housing_edit_damage.global_id', 'housing_units.globalid')))
            ->leftJoinSub($housingOccupiedEdit, 'housing_edit_occupied', fn ($join) => $join->whereRaw($this->collatedEquals('housing_edit_occupied.global_id', 'housing_units.globalid')))
            ->select([
                'housing_units.id',
                'housing_units.objectid',
                'housing_units.globalid',
                'housing_units.parentglobalid',
                'housing_units.creationdate',
                DB::raw('buildings.objectid as building_objectid'),
                DB::raw('COALESCE(housing_edit_type.field_value, housing_units.housing_unit_type) as housing_unit_type'),
                DB::raw('COALESCE(housing_edit_damage.field_value, housing_units.unit_damage_status) as unit_damage_status'),
                DB::raw('COALESCE(housing_edit_occupied.field_value, housing_units.occupied) as occupied'),
                DB::raw(($includeMunicipality ? 'COALESCE(building_edit_municipalitie.field_value, buildings.municipalitie)' : 'buildings.municipalitie').' as building_municipalitie'),
                DB::raw(($includeNeighborhood ? 'COALESCE(building_edit_neighborhood.field_value, buildings.neighborhood)' : 'buildings.neighborhood').' as building_neighborhood'),
                DB::raw(($includeBuildingDamage ? 'COALESCE(building_edit_damage_status.field_value, buildings.building_damage_status)' : 'buildings.building_damage_status').' as building_damage_status'),
                'buildings.assignedto',
                DB::raw(($includeEngineerStatus ? 'engineer_statuses.status_name' : 'null').' as engineer_status_name'),
                DB::raw(($includeLegalStatus ? 'legal_statuses.status_name' : 'null').' as legal_status_name'),
                DB::raw(($includeFinalStatus ? 'final_statuses.status_name' : 'null').' as final_status_name'),
            ]);

        if ($includeMunicipality) {
            $buildingMunicipalityEdit = $this->latestEditValueSubquery('building_table', 'municipalitie');
            $query->leftJoinSub($buildingMunicipalityEdit, 'building_edit_municipalitie', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_municipalitie.global_id', 'buildings.globalid')));
        }

        if ($includeNeighborhood) {
            $buildingNeighborhoodEdit = $this->latestEditValueSubquery('building_table', 'neighborhood');
            $query->leftJoinSub($buildingNeighborhoodEdit, 'building_edit_neighborhood', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_neighborhood.global_id', 'buildings.globalid')));
        }

        if ($includeBuildingDamage) {
            $buildingDamageStatusEdit = $this->latestEditValueSubquery('building_table', 'building_damage_status');
            $query->leftJoinSub($buildingDamageStatusEdit, 'building_edit_damage_status', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_damage_status.global_id', 'buildings.globalid')));
        }

        if ($includeEngineerStatus) {
            $engineerStatus = $this->buildingStatusSubquery('QC/QA Engineer', null, 'engineer');
            $query->leftJoinSub($engineerStatus, 'engineer_statuses', fn ($join) => $join->on('engineer_statuses.building_id', '=', 'buildings.objectid'));
        }

        if ($includeLegalStatus) {
            $legalStatus = $this->buildingStatusSubquery('Legal Auditor', null, 'legal');
            $query->leftJoinSub($legalStatus, 'legal_statuses', fn ($join) => $join->on('legal_statuses.building_id', '=', 'buildings.objectid'));
        }

        if ($includeFinalStatus) {
            $finalStatus = $this->buildingStatusSubquery(null, 'team_leader', 'final');
            $query->leftJoinSub($finalStatus, 'final_statuses', fn ($join) => $join->on('final_statuses.building_id', '=', 'buildings.objectid'));
        }

        return $this->applyHousingFilters($query, $filters);
    }

    public function filteredEditsQuery(array $filters): Builder
    {
        $previousValueSubquery = '
            (
                select previous_edit.field_value
                from edit_assessments as previous_edit
                where previous_edit.global_id = edit_assessments.global_id
                    and previous_edit.type = edit_assessments.type
                    and previous_edit.field_name = edit_assessments.field_name
                    and (
                        previous_edit.updated_at < edit_assessments.updated_at
                        or (
                            previous_edit.updated_at = edit_assessments.updated_at
                            and previous_edit.id < edit_assessments.id
                        )
                    )
                order by previous_edit.updated_at desc, previous_edit.id desc
                limit 1
            )
        ';

        $query = DB::table('edit_assessments')
            ->leftJoin('users', 'users.id', '=', 'edit_assessments.user_id')
            ->where(function ($query) use ($filters) {
                $query->where(function ($buildingQuery) use ($filters) {
                    $buildingQuery->where('edit_assessments.type', 'building_table')
                        ->whereExists(function ($existsQuery) use ($filters) {
                            $existsQuery->fromSub($this->filteredBuildingIdentifiersQuery($filters), 'filtered_buildings')
                                ->selectRaw('1')
                                ->whereRaw($this->collatedEquals('filtered_buildings.globalid', 'edit_assessments.global_id'));
                        });
                })->orWhere(function ($housingQuery) use ($filters) {
                    $housingQuery->where('edit_assessments.type', 'housing_table')
                        ->whereExists(function ($existsQuery) use ($filters) {
                            $existsQuery->fromSub($this->filteredHousingIdentifiersQuery($filters), 'filtered_housing')
                                ->selectRaw('1')
                                ->whereRaw($this->collatedEquals('filtered_housing.globalid', 'edit_assessments.global_id'));
                        });
                });
            })
            ->select([
                'edit_assessments.id',
                DB::raw('edit_assessments.type as source_type'),
                'edit_assessments.global_id',
                'edit_assessments.field_name',
                DB::raw("COALESCE({$previousValueSubquery}, '-') as old_value"),
                'edit_assessments.field_value as new_value',
                DB::raw("COALESCE(users.name, users.email, '-') as updated_by"),
                'edit_assessments.updated_at',
            ]);

        if ($search = $filters['search']) {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('edit_assessments.global_id', 'like', '%'.$search.'%')
                    ->orWhere('edit_assessments.field_name', 'like', '%'.$search.'%')
                    ->orWhere('edit_assessments.field_value', 'like', '%'.$search.'%')
                    ->orWhere('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    public function filteredStatusHistoryQuery(array $filters): Builder
    {
        $filteredBuildings = $this->filteredBuildingIdentifiersQuery($filters)->select([
            'buildings.objectid',
            'buildings.globalid',
            'buildings.assignedto',
        ]);

        $filteredHousing = $this->filteredHousingIdentifiersQuery($filters)->select([
            'housing_units.objectid',
            'housing_units.globalid',
            'assignedto',
        ]);

        $buildingHistoryQuery = DB::table('building_status_histories as history')
            ->joinSub($filteredBuildings, 'filtered_buildings', fn ($join) => $join->on('filtered_buildings.objectid', '=', 'history.building_id'))
            ->join('assessment_statuses', 'assessment_statuses.id', '=', 'history.status_id')
            ->leftJoin('users', 'users.id', '=', 'history.user_id')
            ->select([
                DB::raw("'building' as item_type"),
                'history.id',
                DB::raw('history.building_id as item_number'),
                'assessment_statuses.name as status_name',
                DB::raw($this->statusLabelExpression().' as status_label'),
                DB::raw("COALESCE(users.name, users.email, '-') as changed_by"),
                'history.created_at',
            ]);

        $housingHistoryQuery = DB::table('housing_status_histories as history')
            ->joinSub($filteredHousing, 'filtered_housing', fn ($join) => $join->on('filtered_housing.objectid', '=', 'history.housing_id'))
            ->join('assessment_statuses', 'assessment_statuses.id', '=', 'history.status_id')
            ->leftJoin('users', 'users.id', '=', 'history.user_id')
            ->select([
                DB::raw("'housing' as item_type"),
                'history.id',
                DB::raw('history.housing_id as item_number'),
                'assessment_statuses.name as status_name',
                DB::raw($this->statusLabelExpression().' as status_label'),
                DB::raw("COALESCE(users.name, users.email, '-') as changed_by"),
                'history.created_at',
            ]);

        $query = DB::query()->fromSub($buildingHistoryQuery->unionAll($housingHistoryQuery), 'status_history');

        if ($search = $filters['search']) {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('status_history.item_type', 'like', '%'.$search.'%')
                    ->orWhere('status_history.item_number', 'like', '%'.$search.'%')
                    ->orWhere('status_history.status_label', 'like', '%'.$search.'%')
                    ->orWhere('status_history.status_name', 'like', '%'.$search.'%')
                    ->orWhere('status_history.changed_by', 'like', '%'.$search.'%');
            });
        }

        return $query->select([
            'status_history.id',
            'status_history.item_type',
            'status_history.item_number',
            'status_history.status_name',
            'status_history.status_label',
            'status_history.changed_by',
            'status_history.created_at',
        ]);
    }

    public function filteredAssignmentsQuery(array $filters): Builder
    {
        $municipalityEdit = $this->latestEditValueSubquery('building_table', 'municipalitie');
        $neighborhoodEdit = $this->latestEditValueSubquery('building_table', 'neighborhood');
        $damageStatusEdit = $this->latestEditValueSubquery('building_table', 'building_damage_status');
        $engineerStatus = $this->buildingStatusSubquery('QC/QA Engineer', null, 'engineer');
        $legalStatus = $this->buildingStatusSubquery('Legal Auditor', null, 'legal');
        $finalStatus = $this->buildingStatusSubquery(null, 'team_leader', 'final');

        $query = DB::table('assigned_assessment_users')
            ->join('buildings', 'buildings.id', '=', 'assigned_assessment_users.building_id')
            ->leftJoinSub($municipalityEdit, 'edit_municipalitie', fn ($join) => $join->whereRaw($this->collatedEquals('edit_municipalitie.global_id', 'buildings.globalid')))
            ->leftJoinSub($neighborhoodEdit, 'edit_neighborhood', fn ($join) => $join->whereRaw($this->collatedEquals('edit_neighborhood.global_id', 'buildings.globalid')))
            ->leftJoinSub($damageStatusEdit, 'edit_building_damage_status', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_damage_status.global_id', 'buildings.globalid')))
            ->leftJoinSub($engineerStatus, 'engineer_statuses', fn ($join) => $join->on('engineer_statuses.building_id', '=', 'buildings.objectid'))
            ->leftJoinSub($legalStatus, 'legal_statuses', fn ($join) => $join->on('legal_statuses.building_id', '=', 'buildings.objectid'))
            ->leftJoinSub($finalStatus, 'final_statuses', fn ($join) => $join->on('final_statuses.building_id', '=', 'buildings.objectid'))
            ->leftJoin('users as assigned_user', 'assigned_user.id', '=', 'assigned_assessment_users.user_id')
            ->leftJoin('users as manager_user', 'manager_user.id', '=', 'assigned_assessment_users.manager_id')
            ->select([
                'assigned_assessment_users.id',
                'assigned_assessment_users.building_id',
                'assigned_assessment_users.type',
                DB::raw("COALESCE(assigned_user.name, assigned_user.email, '-') as assigned_user"),
                DB::raw("COALESCE(manager_user.name, manager_user.email, '-') as assigned_by"),
                'assigned_assessment_users.created_at as assigned_date',
                DB::raw("'-' as notes"),
            ]);

        $query = $this->applyBuildingFilters($query, $filters, 'buildings', [
            'municipalitie' => 'COALESCE(edit_municipalitie.field_value, buildings.municipalitie)',
            'neighborhood' => 'COALESCE(edit_neighborhood.field_value, buildings.neighborhood)',
            'building_damage_status' => 'COALESCE(edit_building_damage_status.field_value, buildings.building_damage_status)',
            'building_name' => 'buildings.building_name',
            'engineer_status' => 'engineer_statuses.status_name',
            'legal_status' => 'legal_statuses.status_name',
            'final_status' => 'final_statuses.status_name',
        ]);

        if ($search = $filters['search']) {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('assigned_assessment_users.building_id', 'like', '%'.$search.'%')
                    ->orWhere('assigned_user.name', 'like', '%'.$search.'%')
                    ->orWhere('assigned_user.email', 'like', '%'.$search.'%')
                    ->orWhere('manager_user.name', 'like', '%'.$search.'%')
                    ->orWhere('manager_user.email', 'like', '%'.$search.'%')
                    ->orWhere('assigned_assessment_users.type', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    public function exportRows(string $tab, array $filters): array
    {
        return match ($tab) {
            'buildings' => $this->exportBuildingsRows($filters),
            'housing_units' => $this->exportHousingRows($filters),
            'edits' => $this->exportEditsRows($filters),
            'status_history' => $this->exportStatusHistoryRows($filters),
            'assignments' => $this->exportAssignmentsRows($filters),
            default => [[], collect()],
        };
    }

    public function fieldLabel(string $fieldName): string
    {
        return $this->fieldLabels()->get($fieldName, $fieldName);
    }

    public function paginateBuildings(array $filters, int $start, int $length): array
    {
        $baseQuery = $this->filteredBuildingIdentifiersQuery($filters);
        $total = (clone $baseQuery)->count();
        $pageIds = (clone $baseQuery)
            ->orderByDesc('buildings.creationdate')
            ->orderByDesc('buildings.id')
            ->offset($start)
            ->limit($length)
            ->pluck('buildings.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pageIds === []) {
            return ['total' => $total, 'rows' => collect()];
        }

        $rows = $this->filteredBuildingsQuery($filters)
            ->whereIn('buildings.id', $pageIds)
            ->get();

        return [
            'total' => $total,
            'rows' => $this->sortRowsBySequence($rows, $pageIds, 'id'),
        ];
    }

    public function paginateHousingUnits(array $filters, int $start, int $length): array
    {
        $baseQuery = $this->filteredHousingIdentifiersQuery($filters);
        $total = (clone $baseQuery)->count();
        $pageIds = (clone $baseQuery)
            ->orderByDesc('housing_units.creationdate')
            ->orderByDesc('housing_units.id')
            ->offset($start)
            ->limit($length)
            ->pluck('housing_units.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pageIds === []) {
            return ['total' => $total, 'rows' => collect()];
        }

        $rows = $this->filteredHousingUnitsQuery($filters)
            ->whereIn('housing_units.id', $pageIds)
            ->get();

        return [
            'total' => $total,
            'rows' => $this->sortRowsBySequence($rows, $pageIds, 'id'),
        ];
    }

    public function paginateEdits(array $filters, int $start, int $length): array
    {
        $baseQuery = DB::table('edit_assessments')
            ->select(['edit_assessments.id', 'edit_assessments.updated_at'])
            ->where(function ($query) use ($filters) {
                $query->where(function ($buildingQuery) use ($filters) {
                    $buildingQuery->where('edit_assessments.type', 'building_table')
                        ->whereExists(function ($existsQuery) use ($filters) {
                            $existsQuery->fromSub($this->filteredBuildingIdentifiersQuery($filters), 'filtered_buildings')
                                ->selectRaw('1')
                                ->whereRaw($this->collatedEquals('filtered_buildings.globalid', 'edit_assessments.global_id'));
                        });
                })->orWhere(function ($housingQuery) use ($filters) {
                    $housingQuery->where('edit_assessments.type', 'housing_table')
                        ->whereExists(function ($existsQuery) use ($filters) {
                            $existsQuery->fromSub($this->filteredHousingIdentifiersQuery($filters), 'filtered_housing')
                                ->selectRaw('1')
                                ->whereRaw($this->collatedEquals('filtered_housing.globalid', 'edit_assessments.global_id'));
                        });
                });
            });

        if ($search = $filters['search']) {
            $baseQuery->where(function ($searchQuery) use ($search) {
                $searchQuery->where('edit_assessments.global_id', 'like', '%'.$search.'%')
                    ->orWhere('edit_assessments.field_name', 'like', '%'.$search.'%')
                    ->orWhere('edit_assessments.field_value', 'like', '%'.$search.'%');
            });
        }

        $total = (clone $baseQuery)->count();
        $pageIds = (clone $baseQuery)
            ->orderByDesc('edit_assessments.updated_at')
            ->orderByDesc('edit_assessments.id')
            ->offset($start)
            ->limit($length)
            ->pluck('edit_assessments.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pageIds === []) {
            return ['total' => $total, 'rows' => collect()];
        }

        $rows = $this->filteredEditsQuery($filters)
            ->whereIn('edit_assessments.id', $pageIds)
            ->get();

        return [
            'total' => $total,
            'rows' => $this->sortRowsBySequence($rows, $pageIds, 'id'),
        ];
    }

    public function paginateStatusHistory(array $filters, int $start, int $length): array
    {
        $baseQuery = $this->statusHistoryIdentifiersQuery($filters);
        $total = DB::query()->fromSub(clone $baseQuery, 'status_history_ids')->count();
        $pageRows = DB::query()
            ->fromSub($baseQuery, 'status_history_ids')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($start)
            ->limit($length)
            ->get();

        if ($pageRows->isEmpty()) {
            return ['total' => $total, 'rows' => collect()];
        }

        $pageBuildingIds = $pageRows->where('item_type', 'building')->pluck('id')->all();
        $pageHousingIds = $pageRows->where('item_type', 'housing')->pluck('id')->all();

        $rows = $this->filteredStatusHistoryQuery($filters)
            ->where(function ($query) use ($pageBuildingIds, $pageHousingIds) {
                if ($pageBuildingIds !== []) {
                    $query->where(function ($innerQuery) use ($pageBuildingIds) {
                        $innerQuery->where('status_history.item_type', 'building')
                            ->whereIn('status_history.id', $pageBuildingIds);
                    });
                }

                if ($pageHousingIds !== []) {
                    $query->orWhere(function ($innerQuery) use ($pageHousingIds) {
                        $innerQuery->where('status_history.item_type', 'housing')
                            ->whereIn('status_history.id', $pageHousingIds);
                    });
                }
            })
            ->get();

        $sequence = $pageRows
            ->map(fn ($row) => $row->item_type.'-'.$row->id)
            ->all();

        return [
            'total' => $total,
            'rows' => $this->sortRowsByCompositeSequence($rows, $sequence, fn ($row) => $row->item_type.'-'.$row->id),
        ];
    }

    public function paginateAssignments(array $filters, int $start, int $length): array
    {
        $filteredBuildings = $this->filteredBuildingIdentifiersQuery($filters)->select([
            'buildings.id',
        ]);

        $baseQuery = DB::table('assigned_assessment_users')
            ->join('buildings', 'buildings.id', '=', 'assigned_assessment_users.building_id')
            ->joinSub($filteredBuildings, 'filtered_buildings', fn ($join) => $join->on('filtered_buildings.id', '=', 'buildings.id'))
            ->select(['assigned_assessment_users.id', 'assigned_assessment_users.created_at']);

        if ($search = $filters['search']) {
            $baseQuery->where('assigned_assessment_users.type', 'like', '%'.$search.'%');
        }

        $total = (clone $baseQuery)->count();
        $pageIds = (clone $baseQuery)
            ->orderByDesc('assigned_assessment_users.created_at')
            ->orderByDesc('assigned_assessment_users.id')
            ->offset($start)
            ->limit($length)
            ->pluck('assigned_assessment_users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pageIds === []) {
            return ['total' => $total, 'rows' => collect()];
        }

        $rows = $this->filteredAssignmentsQuery($filters)
            ->whereIn('assigned_assessment_users.id', $pageIds)
            ->get();

        return [
            'total' => $total,
            'rows' => $this->sortRowsBySequence($rows, $pageIds, 'id'),
        ];
    }

    private function exportBuildingsRows(array $filters): array
    {
        $rows = $this->filteredBuildingsQuery($filters)->orderBy('creationdate', 'desc')->get();

        return [[
            $this->trans('tabs.buildings'),
            $this->trans('columns.object_id'),
            $this->trans('columns.globalid'),
            $this->trans('columns.assignedto'),
            $this->trans('columns.municipality'),
            $this->trans('columns.neighborhood'),
            $this->trans('columns.parcel_number'),
            $this->trans('columns.building_use'),
            $this->trans('columns.building_damage_status'),
            $this->trans('columns.creationdate'),
            $this->trans('columns.last_update'),
            $this->trans('columns.final_status'),
        ], $rows->map(function ($row) {
            return [
                $this->trans('tabs.buildings'),
                $row->objectid,
                $row->globalid,
                $row->assignedto,
                $row->municipalitie,
                $row->neighborhood,
                $row->parcel_no1,
                $row->building_use,
                $row->building_damage_status,
                $row->creationdate,
                $row->editdate,
                $row->final_status_label,
            ];
        })];
    }

    private function exportHousingRows(array $filters): array
    {
        $rows = $this->filteredHousingUnitsQuery($filters)->orderBy('housing_units.creationdate', 'desc')->get();

        return [[
            $this->trans('tabs.housing_units'),
            $this->trans('columns.object_id'),
            $this->trans('columns.parentglobalid'),
            $this->trans('columns.building_number'),
            $this->trans('columns.unit_use'),
            $this->trans('columns.damage_status'),
            $this->trans('columns.occupant_status'),
            $this->trans('columns.creationdate'),
        ], $rows->map(fn ($row) => [
            $this->trans('tabs.housing_units'),
            $row->objectid,
            $row->parentglobalid,
            $row->building_objectid,
            $row->housing_unit_type,
            $row->unit_damage_status,
            $row->occupied,
            $row->creationdate,
        ])];
    }

    private function exportEditsRows(array $filters): array
    {
        $rows = $this->filteredEditsQuery($filters)->orderByDesc('edit_assessments.updated_at')->get();

        return [[
            $this->trans('tabs.edits'),
            $this->trans('columns.type'),
            $this->trans('columns.globalid'),
            $this->trans('columns.field_name'),
            $this->trans('columns.old_value'),
            $this->trans('columns.new_value'),
            $this->trans('columns.updated_by'),
            $this->trans('columns.updated_at'),
        ], $rows->map(fn ($row) => [
            $this->trans('tabs.edits'),
            Str::replace('_', ' ', (string) $row->source_type),
            $row->global_id,
            $this->fieldLabel((string) $row->field_name),
            $row->old_value,
            $row->new_value,
            $row->updated_by,
            $row->updated_at,
        ])];
    }

    private function exportStatusHistoryRows(array $filters): array
    {
        $rows = $this->filteredStatusHistoryQuery($filters)->orderByDesc('created_at')->get();

        return [[
            $this->trans('tabs.status_history'),
            $this->trans('columns.type'),
            $this->trans('columns.item_number'),
            $this->trans('columns.status'),
            $this->trans('columns.changed_by'),
            $this->trans('columns.changed_at'),
        ], $rows->map(fn ($row) => [
            $this->trans('tabs.status_history'),
            $row->item_type,
            $row->item_number,
            $row->status_label,
            $row->changed_by,
            $row->created_at,
        ])];
    }

    private function exportAssignmentsRows(array $filters): array
    {
        $rows = $this->filteredAssignmentsQuery($filters)->orderByDesc('assigned_assessment_users.created_at')->get();

        return [[
            $this->trans('tabs.assignments'),
            $this->trans('columns.building_id'),
            $this->trans('columns.assigned_user'),
            $this->trans('columns.assigned_by'),
            $this->trans('columns.assigned_date'),
            $this->trans('columns.notes'),
        ], $rows->map(fn ($row) => [
            $this->trans('tabs.assignments'),
            $row->building_id,
            $row->assigned_user,
            $row->assigned_by,
            $row->assigned_date,
            $row->notes,
        ])];
    }

    private function latestEditValueSubquery(string $type, string $fieldName): Builder
    {
        $latestEditIds = DB::table('edit_assessments')
            ->selectRaw('MAX(id) as latest_id, global_id')
            ->where('type', $type)
            ->where('field_name', $fieldName)
            ->groupBy('global_id');

        return DB::table('edit_assessments as latest_edit')
            ->joinSub($latestEditIds, 'latest_edit_ids', fn ($join) => $join->on('latest_edit_ids.latest_id', '=', 'latest_edit.id'))
            ->select([
                'latest_edit.global_id',
                'latest_edit.field_value',
            ]);
    }

    private function statusHistoryIdentifiersQuery(array $filters): Builder
    {
        $filteredBuildings = $this->filteredBuildingIdentifiersQuery($filters)->select([
            'buildings.objectid',
        ]);

        $filteredHousing = $this->filteredHousingIdentifiersQuery($filters)->select([
            'housing_units.objectid',
        ]);

        $buildingHistoryQuery = DB::table('building_status_histories as history')
            ->joinSub($filteredBuildings, 'filtered_buildings', fn ($join) => $join->on('filtered_buildings.objectid', '=', 'history.building_id'))
            ->select([
                DB::raw("'building' as item_type"),
                'history.id',
                'history.created_at',
            ]);

        $housingHistoryQuery = DB::table('housing_status_histories as history')
            ->joinSub($filteredHousing, 'filtered_housing', fn ($join) => $join->on('filtered_housing.objectid', '=', 'history.housing_id'))
            ->select([
                DB::raw("'housing' as item_type"),
                'history.id',
                'history.created_at',
            ]);

        return DB::query()->fromSub($buildingHistoryQuery->unionAll($housingHistoryQuery), 'status_history_ids');
    }

    private function filteredBuildingIdentifiersQuery(array $filters): Builder
    {
        $query = DB::table('buildings')->select([
            'buildings.id',
            'buildings.objectid',
            'buildings.globalid',
            'buildings.assignedto',
        ]);

        if ($filters['municipalitie']) {
            $municipalityEdit = $this->latestEditValueSubquery('building_table', 'municipalitie');
            $query->leftJoinSub($municipalityEdit, 'edit_municipalitie', fn ($join) => $join->whereRaw($this->collatedEquals('edit_municipalitie.global_id', 'buildings.globalid')));
            $query->whereRaw(
                $this->collatedExpression('COALESCE(edit_municipalitie.field_value, buildings.municipalitie)')
                .' = '.$this->collatedLiteral($filters['municipalitie'])
            );
        }

        if ($filters['neighborhood']) {
            $neighborhoodEdit = $this->latestEditValueSubquery('building_table', 'neighborhood');
            $query->leftJoinSub($neighborhoodEdit, 'edit_neighborhood', fn ($join) => $join->whereRaw($this->collatedEquals('edit_neighborhood.global_id', 'buildings.globalid')));
            $query->whereRaw(
                $this->collatedExpression('COALESCE(edit_neighborhood.field_value, buildings.neighborhood)')
                .' = '.$this->collatedLiteral($filters['neighborhood'])
            );
        }

        if ($filters['building_damage_status']) {
            $damageStatusEdit = $this->latestEditValueSubquery('building_table', 'building_damage_status');
            $query->leftJoinSub($damageStatusEdit, 'edit_building_damage_status', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_damage_status.global_id', 'buildings.globalid')));
            $query->whereRaw(
                $this->collatedExpression('COALESCE(edit_building_damage_status.field_value, buildings.building_damage_status)')
                .' = '.$this->collatedLiteral($filters['building_damage_status'])
            );
        }

        if ($filters['engineer_status']) {
            $engineerStatus = $this->buildingStatusSubquery('QC/QA Engineer', null, 'engineer');
            $query->joinSub($engineerStatus, 'engineer_statuses', fn ($join) => $join->on('engineer_statuses.building_id', '=', 'buildings.objectid'));
            $query->where('engineer_statuses.status_name', $filters['engineer_status']);
        }

        if ($filters['legal_status']) {
            $legalStatus = $this->buildingStatusSubquery('Legal Auditor', null, 'legal');
            $query->joinSub($legalStatus, 'legal_statuses', fn ($join) => $join->on('legal_statuses.building_id', '=', 'buildings.objectid'));
            $query->where('legal_statuses.status_name', $filters['legal_status']);
        }

        if ($filters['final_status']) {
            $finalStatus = $this->buildingStatusSubquery(null, 'team_leader', 'final');
            $query->joinSub($finalStatus, 'final_statuses', fn ($join) => $join->on('final_statuses.building_id', '=', 'buildings.objectid'));
            $query->where('final_statuses.status_name', $filters['final_status']);
        }

        if ($filters['assignedto']) {
            $query->where('buildings.assignedto', $filters['assignedto']);
        }

        if ($filters['from_date']) {
            $query->whereDate('buildings.creationdate', '>=', $filters['from_date']);
        }

        if ($filters['to_date']) {
            $query->whereDate('buildings.creationdate', '<=', $filters['to_date']);
        }

        return $query;
    }

    private function sortRowsBySequence(Collection $rows, array $sequence, string $key): Collection
    {
        $positions = array_flip($sequence);

        return $rows
            ->sortBy(fn ($row) => $positions[(int) data_get($row, $key)] ?? PHP_INT_MAX)
            ->values();
    }

    private function sortRowsByCompositeSequence(Collection $rows, array $sequence, callable $resolver): Collection
    {
        $positions = array_flip($sequence);

        return $rows
            ->sortBy(fn ($row) => $positions[$resolver($row)] ?? PHP_INT_MAX)
            ->values();
    }

    private function filteredHousingIdentifiersQuery(array $filters): Builder
    {
        $query = DB::table('housing_units')
            ->join('buildings', fn ($join) => $join->whereRaw($this->collatedEquals('housing_units.parentglobalid', 'buildings.globalid')))
            ->select([
                'housing_units.id',
                'housing_units.objectid',
                'housing_units.globalid',
                'housing_units.parentglobalid',
                'assignedto',
            ]);

        if ($filters['municipalitie']) {
            $buildingMunicipalityEdit = $this->latestEditValueSubquery('building_table', 'municipalitie');
            $query->leftJoinSub($buildingMunicipalityEdit, 'building_edit_municipalitie', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_municipalitie.global_id', 'buildings.globalid')));
            $query->whereRaw(
                $this->collatedExpression('COALESCE(building_edit_municipalitie.field_value, buildings.municipalitie)')
                .' = '.$this->collatedLiteral($filters['municipalitie'])
            );
        }

        if ($filters['neighborhood']) {
            $buildingNeighborhoodEdit = $this->latestEditValueSubquery('building_table', 'neighborhood');
            $query->leftJoinSub($buildingNeighborhoodEdit, 'building_edit_neighborhood', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_neighborhood.global_id', 'buildings.globalid')));
            $query->whereRaw(
                $this->collatedExpression('COALESCE(building_edit_neighborhood.field_value, buildings.neighborhood)')
                .' = '.$this->collatedLiteral($filters['neighborhood'])
            );
        }

        if ($filters['building_damage_status']) {
            $buildingDamageStatusEdit = $this->latestEditValueSubquery('building_table', 'building_damage_status');
            $query->leftJoinSub($buildingDamageStatusEdit, 'building_edit_damage_status', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_damage_status.global_id', 'buildings.globalid')));
            $query->whereRaw(
                $this->collatedExpression('COALESCE(building_edit_damage_status.field_value, buildings.building_damage_status)')
                .' = '.$this->collatedLiteral($filters['building_damage_status'])
            );
        }

        if ($filters['engineer_status']) {
            $engineerStatus = $this->buildingStatusSubquery('QC/QA Engineer', null, 'engineer');
            $query->joinSub($engineerStatus, 'engineer_statuses', fn ($join) => $join->on('engineer_statuses.building_id', '=', 'buildings.objectid'));
            $query->where('engineer_statuses.status_name', $filters['engineer_status']);
        }

        if ($filters['legal_status']) {
            $legalStatus = $this->buildingStatusSubquery('Legal Auditor', null, 'legal');
            $query->joinSub($legalStatus, 'legal_statuses', fn ($join) => $join->on('legal_statuses.building_id', '=', 'buildings.objectid'));
            $query->where('legal_statuses.status_name', $filters['legal_status']);
        }

        if ($filters['final_status']) {
            $finalStatus = $this->buildingStatusSubquery(null, 'team_leader', 'final');
            $query->joinSub($finalStatus, 'final_statuses', fn ($join) => $join->on('final_statuses.building_id', '=', 'buildings.objectid'));
            $query->where('final_statuses.status_name', $filters['final_status']);
        }

        if ($filters['assignedto']) {
            $query->where('buildings.assignedto', $filters['assignedto']);
        }

        if ($filters['from_date']) {
            $query->whereDate('buildings.creationdate', '>=', $filters['from_date']);
        }

        if ($filters['to_date']) {
            $query->whereDate('buildings.creationdate', '<=', $filters['to_date']);
        }

        return $query;
    }

    private function buildingStatusSubquery(?string $type, ?string $stage, string $alias): Builder
    {
        $latestStatusIds = DB::table('building_statuses as status_lookup')
            ->join('assessment_statuses as assessment_status_lookup', 'assessment_status_lookup.id', '=', 'status_lookup.status_id')
            ->selectRaw('MAX(status_lookup.id) as latest_id')
            ->groupBy('status_lookup.building_id');

        if ($type !== null) {
            $latestStatusIds->where('status_lookup.type', $type);
        }

        if ($stage !== null) {
            $latestStatusIds->where('assessment_status_lookup.stage', $stage);
        }

        $query = DB::table('building_statuses')
            ->joinSub($latestStatusIds, $alias.'_latest_ids', fn ($join) => $join->on($alias.'_latest_ids.latest_id', '=', 'building_statuses.id'))
            ->join('assessment_statuses', 'assessment_statuses.id', '=', 'building_statuses.status_id')
            ->select([
                'building_statuses.building_id',
                DB::raw('assessment_statuses.name as status_name'),
                DB::raw($this->statusLabelExpression().' as status_label'),
            ]);

        if ($type !== null) {
            $query->where('building_statuses.type', $type);
        }

        if ($stage !== null) {
            $query->where('assessment_statuses.stage', $stage);
        }

        return $query;
    }

    private function applyBuildingFilters(Builder $query, array $filters, string $buildingTable = 'buildings', array $expressions = []): Builder
    {
        $municipalityExpression = $expressions['municipalitie'] ?? "COALESCE(edit_municipalitie.field_value, {$buildingTable}.municipalitie)";
        $neighborhoodExpression = $expressions['neighborhood'] ?? "COALESCE(edit_neighborhood.field_value, {$buildingTable}.neighborhood)";
        $damageExpression = $expressions['building_damage_status'] ?? "COALESCE(edit_building_damage_status.field_value, {$buildingTable}.building_damage_status)";
        $buildingNameExpression = $expressions['building_name'] ?? "COALESCE(edit_building_name.field_value, {$buildingTable}.building_name)";
        $engineerStatusExpression = $expressions['engineer_status'] ?? 'engineer_statuses.status_name';
        $legalStatusExpression = $expressions['legal_status'] ?? 'legal_statuses.status_name';
        $finalStatusExpression = $expressions['final_status'] ?? 'final_statuses.status_name';

        if ($filters['assignedto']) {
            $query->where("{$buildingTable}.assignedto", $filters['assignedto']);
        }

        if ($filters['municipalitie']) {
            $query->whereRaw(
                $this->collatedExpression($municipalityExpression).' = '.$this->collatedLiteral($filters['municipalitie'])
            );
        }

        if ($filters['neighborhood']) {
            $query->whereRaw(
                $this->collatedExpression($neighborhoodExpression).' = '.$this->collatedLiteral($filters['neighborhood'])
            );
        }

        if ($filters['building_damage_status']) {
            $query->whereRaw(
                $this->collatedExpression($damageExpression).' = '.$this->collatedLiteral($filters['building_damage_status'])
            );
        }

        if ($filters['engineer_status']) {
            $query->where($engineerStatusExpression, $filters['engineer_status']);
        }

        if ($filters['legal_status']) {
            $query->where($legalStatusExpression, $filters['legal_status']);
        }

        if ($filters['final_status']) {
            $query->where($finalStatusExpression, $filters['final_status']);
        }

        if ($filters['from_date']) {
            $query->whereDate("{$buildingTable}.creationdate", '>=', $filters['from_date']);
        }

        if ($filters['to_date']) {
            $query->whereDate("{$buildingTable}.creationdate", '<=', $filters['to_date']);
        }

        if ($search = $filters['search']) {
            $query->where(function ($searchQuery) use (
                $search,
                $buildingTable,
                $municipalityExpression,
                $neighborhoodExpression,
                $damageExpression,
                $buildingNameExpression
            ) {
                $searchQuery->where("{$buildingTable}.objectid", 'like', '%'.$search.'%')
                    ->orWhere("{$buildingTable}.globalid", 'like', '%'.$search.'%')
                    ->orWhere("{$buildingTable}.assignedto", 'like', '%'.$search.'%')
                    ->orWhereRaw($this->collatedExpression($buildingNameExpression).' like '.$this->collatedLiteral('%'.$search.'%'))
                    ->orWhereRaw($this->collatedExpression($municipalityExpression).' like '.$this->collatedLiteral('%'.$search.'%'))
                    ->orWhereRaw($this->collatedExpression($neighborhoodExpression).' like '.$this->collatedLiteral('%'.$search.'%'))
                    ->orWhereRaw($this->collatedExpression($damageExpression).' like '.$this->collatedLiteral('%'.$search.'%'));
            });
        }

        return $query;
    }

    private function applyHousingFilters(Builder $query, array $filters): Builder
    {
        if ($filters['assignedto']) {
            $query->where('buildings.assignedto', $filters['assignedto']);
        }

        if ($filters['municipalitie']) {
            $query->whereRaw(
                $this->collatedExpression('COALESCE(building_edit_municipalitie.field_value, buildings.municipalitie)')
                .' = '.$this->collatedLiteral($filters['municipalitie'])
            );
        }

        if ($filters['neighborhood']) {
            $query->whereRaw(
                $this->collatedExpression('COALESCE(building_edit_neighborhood.field_value, buildings.neighborhood)')
                .' = '.$this->collatedLiteral($filters['neighborhood'])
            );
        }

        if ($filters['building_damage_status']) {
            $query->whereRaw(
                $this->collatedExpression('COALESCE(building_edit_damage_status.field_value, buildings.building_damage_status)')
                .' = '.$this->collatedLiteral($filters['building_damage_status'])
            );
        }

        if ($filters['engineer_status']) {
            $query->where('engineer_statuses.status_name', $filters['engineer_status']);
        }

        if ($filters['legal_status']) {
            $query->where('legal_statuses.status_name', $filters['legal_status']);
        }

        if ($filters['final_status']) {
            $query->where('final_statuses.status_name', $filters['final_status']);
        }

        if ($filters['from_date']) {
            $query->whereDate('buildings.creationdate', '>=', $filters['from_date']);
        }

        if ($filters['to_date']) {
            $query->whereDate('buildings.creationdate', '<=', $filters['to_date']);
        }

        if ($search = $filters['search']) {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('housing_units.objectid', 'like', '%'.$search.'%')
                    ->orWhere('housing_units.globalid', 'like', '%'.$search.'%')
                    ->orWhere('housing_units.parentglobalid', 'like', '%'.$search.'%')
                    ->orWhere('buildings.objectid', 'like', '%'.$search.'%')
                    ->orWhere('buildings.assignedto', 'like', '%'.$search.'%')
                    ->orWhereRaw($this->collatedExpression('COALESCE(housing_edit_type.field_value, housing_units.housing_unit_type)').' like '.$this->collatedLiteral('%'.$search.'%'))
                    ->orWhereRaw($this->collatedExpression('COALESCE(housing_edit_damage.field_value, housing_units.unit_damage_status)').' like '.$this->collatedLiteral('%'.$search.'%'))
                    ->orWhereRaw($this->collatedExpression('COALESCE(housing_edit_occupied.field_value, housing_units.occupied)').' like '.$this->collatedLiteral('%'.$search.'%'));
            });
        }

        return $query;
    }

    private function statusOptionsByStage(string $stage): array
    {
        return AssessmentStatus::query()
            ->where('stage', $stage)
            ->orderBy('order_step')
            ->get()
            ->map(fn (AssessmentStatus $status) => [
                'name' => $status->name,
                'label' => app()->getLocale() === 'ar'
                    ? ($status->label_ar ?: $status->label_en ?: $status->name)
                    : ($status->label_en ?: $status->label_ar ?: $status->name),
            ])
            ->all();
    }

    private function fieldLabels(): Collection
    {
        if ($this->fieldLabels !== null) {
            return $this->fieldLabels;
        }

        $hasArabicLabel = Schema::hasColumn('assessments', 'label_ar');
        $hasEnglishLabel = Schema::hasColumn('assessments', 'label_en');
        $hasLegacyLabel = Schema::hasColumn('assessments', 'label');
        $labelColumn = app()->getLocale() === 'ar'
            ? ($hasArabicLabel ? 'label_ar' : ($hasLegacyLabel ? 'label' : 'name'))
            : ($hasEnglishLabel ? 'label_en' : ($hasLegacyLabel ? 'label' : 'name'));

        $fallbackColumn = app()->getLocale() === 'ar'
            ? ($hasEnglishLabel ? 'label_en' : ($hasLegacyLabel ? 'label' : 'name'))
            : ($hasArabicLabel ? 'label_ar' : ($hasLegacyLabel ? 'label' : 'name'));

        $this->fieldLabels = Assessment::query()
            ->select(['name', $labelColumn, $fallbackColumn])
            ->get()
            ->mapWithKeys(function (Assessment $assessment) use ($labelColumn, $fallbackColumn) {
                $label = $assessment->{$labelColumn}
                    ?: $assessment->{$fallbackColumn}
                    ?: $assessment->name;

                return [$assessment->name => $label];
            });

        return $this->fieldLabels;
    }

    private function statusLabelExpression(): string
    {
        if (app()->getLocale() === 'ar') {
            return "COALESCE(NULLIF(assessment_statuses.label_ar, ''), NULLIF(assessment_statuses.label_en, ''), assessment_statuses.name)";
        }

        return "COALESCE(NULLIF(assessment_statuses.label_en, ''), NULLIF(assessment_statuses.label_ar, ''), assessment_statuses.name)";
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmedValue = trim($value);

        return $trimmedValue === '' ? null : $trimmedValue;
    }

    private function trans(string $key): string
    {
        return (string) __("multilingual.field_engineer_report.{$key}");
    }

    private function collatedEquals(string $leftColumn, string $rightColumn): string
    {
        if (! $this->shouldForceCollation()) {
            return "{$leftColumn} = {$rightColumn}";
        }

        return "{$leftColumn} COLLATE utf8mb4_unicode_ci = {$rightColumn} COLLATE utf8mb4_unicode_ci";
    }

    private function collatedValue(string $column): string
    {
        if (! $this->shouldForceCollation()) {
            return $column;
        }

        return "{$column} COLLATE utf8mb4_unicode_ci";
    }

    private function collatedExpression(string $expression): string
    {
        if (! $this->shouldForceCollation()) {
            return $expression;
        }

        return "({$expression}) COLLATE utf8mb4_unicode_ci";
    }

    private function collatedLiteral(string $value): string
    {
        $quotedValue = "'".str_replace("'", "''", $value)."'";

        if (! $this->shouldForceCollation()) {
            return $quotedValue;
        }

        return "{$quotedValue} COLLATE utf8mb4_unicode_ci";
    }

    private function shouldForceCollation(): bool
    {
        if ($this->forceCollation !== null) {
            return $this->forceCollation;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return $this->forceCollation = false;
        }

        $databaseName = DB::connection()->getDatabaseName();

        if (! $databaseName) {
            return $this->forceCollation = true;
        }

        $collations = collect(DB::table('information_schema.COLUMNS')
            ->select('COLLATION_NAME')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where(function ($query) {
                $query->where(function ($innerQuery) {
                    $innerQuery->where('TABLE_NAME', 'buildings')
                        ->whereIn('COLUMN_NAME', ['globalid', 'assignedto', 'field_status', 'building_damage_status']);
                })->orWhere(function ($innerQuery) {
                    $innerQuery->where('TABLE_NAME', 'housing_units')
                        ->whereIn('COLUMN_NAME', ['globalid', 'parentglobalid']);
                })->orWhere(function ($innerQuery) {
                    $innerQuery->where('TABLE_NAME', 'edit_assessments')
                        ->whereIn('COLUMN_NAME', ['global_id']);
                })->orWhere(function ($innerQuery) {
                    $innerQuery->where('TABLE_NAME', 'assessment_statuses')
                        ->whereIn('COLUMN_NAME', ['name', 'stage']);
                });
            })
            ->pluck('COLLATION_NAME'))
            ->filter()
            ->unique()
            ->values();

        return $this->forceCollation = $collations->count() > 1;
    }
}
