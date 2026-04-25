<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FieldEngineerReportService
{
    private ?Collection $fieldLabels = null;

    public function filterOptions(): array
    {
        return [
            'engineers' => DB::table('buildings')
                ->select('assignedto')
                ->whereNotNull('assignedto')
                ->where('assignedto', '!=', '')
                ->distinct()
                ->orderBy('assignedto')
                ->pluck('assignedto')
                ->values()
                ->all(),
            'municipalities' => DB::table('buildings')
                ->select('municipalitie')
                ->whereNotNull('municipalitie')
                ->where('municipalitie', '!=', '')
                ->distinct()
                ->orderBy('municipalitie')
                ->pluck('municipalitie')
                ->values()
                ->all(),
            'neighborhoods' => DB::table('buildings')
                ->select('neighborhood')
                ->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '')
                ->distinct()
                ->orderBy('neighborhood')
                ->pluck('neighborhood')
                ->values()
                ->all(),
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

    public function summary(array $filters): array
    {
        $buildingsQuery = $this->filteredBuildingsQuery($filters);
        $housingUnitsQuery = $this->filteredHousingUnitsQuery($filters);
        $editsQuery = $this->filteredEditsQuery($filters);

        $buildingsSummary = DB::query()
            ->fromSub($buildingsQuery, 'filtered_buildings')
            ->selectRaw("
                COUNT(*) as total_buildings,
                SUM(CASE WHEN field_status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_buildings,
                SUM(CASE WHEN field_status <> 'COMPLETED' OR field_status IS NULL THEN 1 ELSE 0 END) as not_completed_buildings,
                SUM(CASE WHEN building_damage_status IS NOT NULL AND building_damage_status <> '' AND building_damage_status NOT IN ('no_damage', 'no_damage2') THEN 1 ELSE 0 END) as damaged_buildings,
                SUM(CASE WHEN final_status_name LIKE '%accept%' THEN 1 ELSE 0 END) as accepted_statuses,
                SUM(CASE WHEN final_status_name LIKE '%reject%' THEN 1 ELSE 0 END) as rejected_statuses,
                SUM(CASE WHEN final_status_name = 'need_review' THEN 1 ELSE 0 END) as need_review_statuses,
                MAX(COALESCE(editdate, creationdate)) as last_updated_at
            ")
            ->first();

        $housingSummary = DB::query()
            ->fromSub($housingUnitsQuery, 'filtered_housing_units')
            ->selectRaw("
                COUNT(*) as total_housing_units,
                SUM(CASE WHEN unit_damage_status IS NOT NULL AND unit_damage_status <> '' AND unit_damage_status NOT IN ('no_damage', 'no_damage2') THEN 1 ELSE 0 END) as damaged_housing_units
            ")
            ->first();

        $editsSummary = DB::query()
            ->fromSub($editsQuery, 'filtered_edits')
            ->selectRaw("
                SUM(CASE WHEN source_type = 'building_table' THEN 1 ELSE 0 END) as building_edits,
                SUM(CASE WHEN source_type = 'housing_table' THEN 1 ELSE 0 END) as housing_edits
            ")
            ->first();

        $totalBuildings = (int) ($buildingsSummary->total_buildings ?? 0);
        $completedBuildings = (int) ($buildingsSummary->completed_buildings ?? 0);

        return [
            'total_buildings' => $totalBuildings,
            'total_housing_units' => (int) ($housingSummary->total_housing_units ?? 0),
            'damaged_buildings' => (int) ($buildingsSummary->damaged_buildings ?? 0),
            'damaged_housing_units' => (int) ($housingSummary->damaged_housing_units ?? 0),
            'building_edits' => (int) ($editsSummary->building_edits ?? 0),
            'housing_edits' => (int) ($editsSummary->housing_edits ?? 0),
            'accepted_statuses' => (int) ($buildingsSummary->accepted_statuses ?? 0),
            'rejected_statuses' => (int) ($buildingsSummary->rejected_statuses ?? 0),
            'need_review_statuses' => (int) ($buildingsSummary->need_review_statuses ?? 0),
            'last_updated_at' => $buildingsSummary->last_updated_at,
            'completion_rate' => $totalBuildings > 0 ? round(($completedBuildings / $totalBuildings) * 100, 1) : 0.0,
            'completed_buildings' => $completedBuildings,
            'not_completed_buildings' => (int) ($buildingsSummary->not_completed_buildings ?? 0),
        ];
    }

    public function filteredBuildingsQuery(array $filters): Builder
    {
        $municipalityEdit = $this->latestEditValueSubquery('building_table', 'municipalitie');
        $neighborhoodEdit = $this->latestEditValueSubquery('building_table', 'neighborhood');
        $damageStatusEdit = $this->latestEditValueSubquery('building_table', 'building_damage_status');
        $buildingUseEdit = $this->latestEditValueSubquery('building_table', 'building_use');
        $buildingNameEdit = $this->latestEditValueSubquery('building_table', 'building_name');
        $engineerStatus = $this->buildingStatusSubquery('QC/QA Engineer', null, 'engineer');
        $legalStatus = $this->buildingStatusSubquery('Legal Auditor', null, 'legal');
        $finalStatus = $this->buildingStatusSubquery(null, 'team_leader', 'final');

        $query = DB::table('buildings')
            ->leftJoinSub($municipalityEdit, 'edit_municipalitie', fn ($join) => $join->whereRaw($this->collatedEquals('edit_municipalitie.global_id', 'buildings.globalid')))
            ->leftJoinSub($neighborhoodEdit, 'edit_neighborhood', fn ($join) => $join->whereRaw($this->collatedEquals('edit_neighborhood.global_id', 'buildings.globalid')))
            ->leftJoinSub($damageStatusEdit, 'edit_building_damage_status', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_damage_status.global_id', 'buildings.globalid')))
            ->leftJoinSub($buildingUseEdit, 'edit_building_use', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_use.global_id', 'buildings.globalid')))
            ->leftJoinSub($buildingNameEdit, 'edit_building_name', fn ($join) => $join->whereRaw($this->collatedEquals('edit_building_name.global_id', 'buildings.globalid')))
            ->leftJoinSub($engineerStatus, 'engineer_statuses', fn ($join) => $join->on('engineer_statuses.building_id', '=', 'buildings.objectid'))
            ->leftJoinSub($legalStatus, 'legal_statuses', fn ($join) => $join->on('legal_statuses.building_id', '=', 'buildings.objectid'))
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
                DB::raw('engineer_statuses.status_name as engineer_status_name'),
                DB::raw('engineer_statuses.status_label as engineer_status_label'),
                DB::raw('legal_statuses.status_name as legal_status_name'),
                DB::raw('legal_statuses.status_label as legal_status_label'),
                DB::raw('final_statuses.status_name as final_status_name'),
                DB::raw('final_statuses.status_label as final_status_label'),
            ]);

        return $this->applyBuildingFilters($query, $filters);
    }

    public function filteredHousingUnitsQuery(array $filters): Builder
    {
        $buildingMunicipalityEdit = $this->latestEditValueSubquery('building_table', 'municipalitie');
        $buildingNeighborhoodEdit = $this->latestEditValueSubquery('building_table', 'neighborhood');
        $buildingDamageStatusEdit = $this->latestEditValueSubquery('building_table', 'building_damage_status');
        $engineerStatus = $this->buildingStatusSubquery('QC/QA Engineer', null, 'engineer');
        $legalStatus = $this->buildingStatusSubquery('Legal Auditor', null, 'legal');
        $finalStatus = $this->buildingStatusSubquery(null, 'team_leader', 'final');
        $housingTypeEdit = $this->latestEditValueSubquery('housing_table', 'housing_unit_type');
        $housingDamageEdit = $this->latestEditValueSubquery('housing_table', 'unit_damage_status');
        $housingOccupiedEdit = $this->latestEditValueSubquery('housing_table', 'occupied');

        $query = DB::table('housing_units')
            ->join('buildings', fn ($join) => $join->whereRaw($this->collatedEquals('housing_units.parentglobalid', 'buildings.globalid')))
            ->leftJoinSub($buildingMunicipalityEdit, 'building_edit_municipalitie', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_municipalitie.global_id', 'buildings.globalid')))
            ->leftJoinSub($buildingNeighborhoodEdit, 'building_edit_neighborhood', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_neighborhood.global_id', 'buildings.globalid')))
            ->leftJoinSub($buildingDamageStatusEdit, 'building_edit_damage_status', fn ($join) => $join->whereRaw($this->collatedEquals('building_edit_damage_status.global_id', 'buildings.globalid')))
            ->leftJoinSub($engineerStatus, 'engineer_statuses', fn ($join) => $join->on('engineer_statuses.building_id', '=', 'buildings.objectid'))
            ->leftJoinSub($legalStatus, 'legal_statuses', fn ($join) => $join->on('legal_statuses.building_id', '=', 'buildings.objectid'))
            ->leftJoinSub($finalStatus, 'final_statuses', fn ($join) => $join->on('final_statuses.building_id', '=', 'buildings.objectid'))
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
                DB::raw('COALESCE(building_edit_municipalitie.field_value, buildings.municipalitie) as building_municipalitie'),
                DB::raw('COALESCE(building_edit_neighborhood.field_value, buildings.neighborhood) as building_neighborhood'),
                DB::raw('COALESCE(building_edit_damage_status.field_value, buildings.building_damage_status) as building_damage_status'),
                'buildings.assignedto',
                DB::raw('engineer_statuses.status_name as engineer_status_name'),
                DB::raw('legal_statuses.status_name as legal_status_name'),
                DB::raw('final_statuses.status_name as final_status_name'),
            ]);

        return $this->applyHousingFilters($query, $filters);
    }

    public function filteredEditsQuery(array $filters): Builder
    {
        $filteredBuildings = $this->filteredBuildingsQuery($filters)->select([
            'buildings.id',
            'buildings.objectid',
            'buildings.globalid',
            'buildings.assignedto',
        ]);

        $filteredHousing = $this->filteredHousingUnitsQuery($filters)->select([
            'housing_units.objectid',
            'housing_units.globalid',
            'housing_units.parentglobalid',
            'assignedto',
        ]);

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
            ->leftJoinSub($filteredBuildings, 'filtered_buildings', function ($join) {
                $join->whereRaw($this->collatedEquals('filtered_buildings.globalid', 'edit_assessments.global_id'))
                    ->where('edit_assessments.type', '=', 'building_table');
            })
            ->leftJoinSub($filteredHousing, 'filtered_housing', function ($join) {
                $join->whereRaw($this->collatedEquals('filtered_housing.globalid', 'edit_assessments.global_id'))
                    ->where('edit_assessments.type', '=', 'housing_table');
            })
            ->leftJoin('users', 'users.id', '=', 'edit_assessments.user_id')
            ->where(function ($query) {
                $query->whereNotNull('filtered_buildings.globalid')
                    ->orWhereNotNull('filtered_housing.globalid');
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
        $filteredBuildings = $this->filteredBuildingsQuery($filters)->select([
            'buildings.objectid',
            'buildings.globalid',
            'buildings.assignedto',
        ]);

        $filteredHousing = $this->filteredHousingUnitsQuery($filters)->select([
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
        return DB::table('edit_assessments as latest_edit')
            ->select([
                'latest_edit.global_id',
                'latest_edit.field_value',
            ])
            ->where('latest_edit.type', $type)
            ->where('latest_edit.field_name', $fieldName)
            ->whereRaw('latest_edit.id = (
                select inner_edit.id
                from edit_assessments as inner_edit
                where inner_edit.type = ?
                    and inner_edit.field_name = ?
                    and '.$this->collatedEquals('inner_edit.global_id', 'latest_edit.global_id').'
                order by inner_edit.updated_at desc, inner_edit.id desc
                limit 1
            )', [$type, $fieldName]);
    }

    private function buildingStatusSubquery(?string $type, ?string $stage, string $alias): Builder
    {
        $query = DB::table('building_statuses')
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
            $query->whereRaw($municipalityExpression.' = ?', [$filters['municipalitie']]);
        }

        if ($filters['neighborhood']) {
            $query->whereRaw($neighborhoodExpression.' = ?', [$filters['neighborhood']]);
        }

        if ($filters['building_damage_status']) {
            $query->whereRaw($damageExpression.' = ?', [$filters['building_damage_status']]);
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
                    ->orWhereRaw($buildingNameExpression.' like ?', ['%'.$search.'%'])
                    ->orWhereRaw($municipalityExpression.' like ?', ['%'.$search.'%'])
                    ->orWhereRaw($neighborhoodExpression.' like ?', ['%'.$search.'%'])
                    ->orWhereRaw($damageExpression.' like ?', ['%'.$search.'%']);
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
            $query->whereRaw('COALESCE(building_edit_municipalitie.field_value, buildings.municipalitie) = ?', [$filters['municipalitie']]);
        }

        if ($filters['neighborhood']) {
            $query->whereRaw('COALESCE(building_edit_neighborhood.field_value, buildings.neighborhood) = ?', [$filters['neighborhood']]);
        }

        if ($filters['building_damage_status']) {
            $query->whereRaw('COALESCE(building_edit_damage_status.field_value, buildings.building_damage_status) = ?', [$filters['building_damage_status']]);
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
                    ->orWhereRaw('COALESCE(housing_edit_type.field_value, housing_units.housing_unit_type) like ?', ['%'.$search.'%'])
                    ->orWhereRaw('COALESCE(housing_edit_damage.field_value, housing_units.unit_damage_status) like ?', ['%'.$search.'%'])
                    ->orWhereRaw('COALESCE(housing_edit_occupied.field_value, housing_units.occupied) like ?', ['%'.$search.'%']);
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
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "{$leftColumn} = {$rightColumn}";
        }

        return "{$leftColumn} COLLATE utf8mb4_unicode_ci = {$rightColumn} COLLATE utf8mb4_unicode_ci";
    }
}
