<?php

namespace App\Modules\DamageAssessment\Services\Audit;

use App\Exports\AuditBuildingsExport;
use App\Models\Building;
use App\Models\HousingUnit;
use App\Modules\DamageAssessment\Http\Requests\AuditExportRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditExportService
{
    public function export(AuditExportRequest $request): BinaryFileResponse
    {
        $buildingColumns = $this->selectedColumns(
            $request->input('building_columns', []),
            $this->buildingColumns()
        );
        $housingColumns = $this->selectedColumns(
            $request->input('housing_columns', []),
            $this->housingColumns()
        );

        $includeHousingUnits = $request->input('export_type') === 'buildings_with_units';
        $baseBuildingQuery = $this->query($request);
        $buildingQuery = (clone $baseBuildingQuery)
            ->withCount([
                'housing_unit as housing_units_count',
                'housing_unit as housing_units_with_status_count' => function (Builder $query): void {
                    $query->whereHas('housingStatuses');
                },
            ]);
        $housingQuery = null;

        if ($includeHousingUnits) {
            if ($housingColumns === []) {
                $housingColumns = $this->housingColumns();
            }

            $buildingGlobalIdsQuery = (clone $baseBuildingQuery)
                ->setEagerLoads([])
                ->reorder()
                ->select('buildings.globalid');

            $housingQuery = HousingUnit::query()
                ->with([
                    'building',
                    'engineerStatus.assessment_status',
                    'lawyerStatus.assessment_status',
                    'finalApproval.assessment_status',
                    'housingStatuses',
                ])
                ->whereIn('parentglobalid', $buildingGlobalIdsQuery)
                ->orderBy('parentglobalid')
                ->orderBy('objectid');
        }

        $fileName = 'audit-export-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new AuditBuildingsExport($buildingColumns, $buildingQuery, $includeHousingUnits, $housingColumns, $housingQuery),
            $fileName
        );
    }

    /**
     * @return array<string, string>
     */
    public function buildingColumns(): array
    {
        return [
            'objectid' => 'ObjectID',
            'globalid' => 'GlobalID',
            'building_name' => 'Ø§Ø³Ù… Ø§Ù„Ù…Ø¨Ù†Ù‰',
            'governorate' => 'Ø§Ù„Ù…Ø­Ø§ÙØ¸Ø©',
            'municipality' => 'Ø§Ù„Ø¨Ù„Ø¯ÙŠØ©',
            'neighborhood' => 'Ø§Ù„Ø­ÙŠ',
            'assignedto' => 'Ø§Ù„Ù…Ù‡Ù†Ø¯Ø³ Ø§Ù„Ù…ÙŠØ¯Ø§Ù†ÙŠ',
            'building_damage_status' => 'Ø­Ø§Ù„Ø© Ø§Ù„Ø¶Ø±Ø±',
            'creationdate' => 'ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡',
            'engineer' => 'Ù…Ù‡Ù†Ø¯Ø³ QC/QA',
            'lawyer' => 'Ø§Ù„Ù…Ø¯Ù‚Ù‚ Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠ',
            'engineer_status' => 'Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ù‡Ù†Ø¯Ø³',
            'lawyer_status' => 'Ø­Ø§Ù„Ø© Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠ',
            'final_status' => 'Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„Ù†Ù‡Ø§Ø¦ÙŠØ©',
            'housing_status_progress' => 'ØªÙ‚Ø¯Ù… Ø§Ù„ÙˆØ­Ø¯Ø§Øª',
            'housing_units_count' => 'Ø¹Ø¯Ø¯ Ø§Ù„ÙˆØ­Ø¯Ø§Øª',
            'housing_units_with_status_count' => 'ÙˆØ­Ø¯Ø§Øª Ù„Ù‡Ø§ Ø­Ø§Ù„Ø©',
            'building_status_notes' => 'Ù…Ù„Ø§Ø­Ø¸Ø© Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø¨Ù†Ù‰',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function housingColumns(): array
    {
        return [
            'building_objectid' => 'ObjectID Ø§Ù„Ù…Ø¨Ù†Ù‰',
            'building_name' => 'Ø§Ø³Ù… Ø§Ù„Ù…Ø¨Ù†Ù‰',
            'governorate' => 'Ø§Ù„Ù…Ø­Ø§ÙØ¸Ø©',
            'municipality' => 'Ø§Ù„Ø¨Ù„Ø¯ÙŠØ©',
            'neighborhood' => 'Ø§Ù„Ø­ÙŠ',
            'objectid' => 'ObjectID Ø§Ù„ÙˆØ­Ø¯Ø©',
            'globalid' => 'GlobalID Ø§Ù„ÙˆØ­Ø¯Ø©',
            'parentglobalid' => 'GlobalID Ø§Ù„Ù…Ø¨Ù†Ù‰',
            'housing_unit_number' => 'Ø±Ù‚Ù… Ø§Ù„ÙˆØ­Ø¯Ø©',
            'floor_number' => 'Ø§Ù„Ø·Ø§Ø¨Ù‚',
            'housing_unit_type' => 'Ù†ÙˆØ¹ Ø§Ù„ÙˆØ­Ø¯Ø©',
            'unit_damage_status' => 'Ø­Ø§Ù„Ø© Ø¶Ø±Ø± Ø§Ù„ÙˆØ­Ø¯Ø©',
            'unit_owner' => 'Ù…Ø§Ù„Ùƒ Ø§Ù„ÙˆØ­Ø¯Ø©',
            'engineer_status' => 'Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ù‡Ù†Ø¯Ø³',
            'lawyer_status' => 'Ø­Ø§Ù„Ø© Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠ',
            'final_status' => 'Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„Ù†Ù‡Ø§Ø¦ÙŠØ©',
            'housing_status_notes' => 'Ù…Ù„Ø§Ø­Ø¸Ø© Ø­Ø§Ù„Ø© Ø§Ù„ÙˆØ­Ø¯Ø©',
        ];
    }

    /**
     * @param  array<int, string>  $requestedColumns
     * @param  array<string, string>  $allowedColumns
     * @return array<string, string>
     */
    private function selectedColumns(array $requestedColumns, array $allowedColumns): array
    {
        $selected = collect($requestedColumns)
            ->map(fn ($column): string => (string) $column)
            ->filter(fn (string $column): bool => array_key_exists($column, $allowedColumns))
            ->unique()
            ->values()
            ->all();

        if ($selected === []) {
            return $allowedColumns;
        }

        return collect($selected)
            ->mapWithKeys(fn (string $column): array => [$column => $allowedColumns[$column]])
            ->all();
    }

    private function query(Request $request): Builder
    {
        $query = Building::query()
            ->with([
                'assignedUsers.user',
                'engineerStatus.status',
                'lawyerStatus.status',
                'finalApproval.status',
                'buildingStatuses',
            ])
            ->where('field_status', 'COMPLETED');

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
            $query->where('objectid', $request->input('objectid'));
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

        if ($request->filled('status_from_date') || $request->filled('status_to_date')) {
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

        $query->whereNotExists(function ($statusQuery): void {
            $statusQuery->selectRaw('1')
                ->from('building_statuses as bs')
                ->join('assessment_statuses as s', 'bs.status_id', '=', 's.id')
                ->whereColumn('bs.building_id', 'buildings.objectid')
                ->whereIn('s.name', [
                    'assigned_to_engineer',
                    'assigned_to_lawyer',
                ])
                ->whereRaw('bs.updated_at = (
                    SELECT MAX(bs2.updated_at)
                    FROM building_statuses bs2
                    WHERE bs2.building_id = bs.building_id
                )');
        });

        return $query->orderByDesc('objectid');
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
