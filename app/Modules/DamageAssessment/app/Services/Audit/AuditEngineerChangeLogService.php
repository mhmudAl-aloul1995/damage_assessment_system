<?php

namespace App\Modules\DamageAssessment\Services\Audit;

use App\Models\Assessment;
use App\Models\AssessmentEditHistory;
use App\Models\Building;
use App\Models\HousingUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AuditEngineerChangeLogService
{
    private const AUDITOR_ROLE_NAMES = [
        'QC/QA Engineer',
        'Engineering Auditor',
    ];

    private const EDIT_TYPES = [
        'building_table',
        'housing_table',
    ];

    private ?Collection $fieldLabels = null;

    public function options(): array
    {
        $histories = $this->baseQuery()
            ->with('user:id,name')
            ->select(['id', 'field_name', 'edited_by'])
            ->get();

        $engineers = $histories
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values();

        $fields = $histories
            ->pluck('field_name')
            ->filter()
            ->unique()
            ->sort()
            ->map(fn (string $fieldName) => [
                'name' => $fieldName,
                'label' => $this->fieldLabel($fieldName),
            ])
            ->values();

        return [
            'engineers' => $engineers,
            'fields' => $fields,
        ];
    }

    public function data(Request $request): array
    {
        $start = max((int) $request->integer('start'), 0);
        $length = (int) $request->integer('length', 10);
        $length = $length === -1 ? 100 : min(max($length, 1), 100);

        $totalQuery = $this->baseQuery();
        $filteredQuery = $this->applyFilters($this->baseQuery(), $request);

        $recordsTotal = (clone $totalQuery)->count();
        $recordsFiltered = (clone $filteredQuery)->count();

        $histories = $filteredQuery
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'draw' => (int) $request->integer('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $this->formatRows($histories)->all(),
        ];
    }

    private function baseQuery(): Builder
    {
        return AssessmentEditHistory::query()
            ->whereIn('type', self::EDIT_TYPES)
            ->whereHas('user.roles', function (Builder $query): void {
                $query->whereIn('name', self::AUDITOR_ROLE_NAMES);
            });
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        $engineerId = $request->integer('engineer_id');
        $fieldName = trim((string) $request->input('field_name', ''));
        $recordType = trim((string) $request->input('record_type', ''));
        $searchValue = trim((string) data_get($request->input('search'), 'value', $request->input('search_value', '')));

        if ($engineerId > 0) {
            $query->where('edited_by', $engineerId);
        }

        if ($fieldName !== '') {
            $query->where('field_name', $fieldName);
        }

        if (in_array($recordType, self::EDIT_TYPES, true)) {
            $query->where('type', $recordType);
        }

        if ($searchValue !== '') {
            $query->where(function (Builder $query) use ($searchValue): void {
                $query
                    ->where('field_name', 'like', "%{$searchValue}%")
                    ->orWhere('old_value', 'like', "%{$searchValue}%")
                    ->orWhere('new_value', 'like', "%{$searchValue}%")
                    ->orWhere('objectid', 'like', "%{$searchValue}%")
                    ->orWhere('global_id', 'like', "%{$searchValue}%")
                    ->orWhereHas('user', function (Builder $query) use ($searchValue): void {
                        $query->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        return $query;
    }

    private function formatRows(Collection $histories): Collection
    {
        $buildingGlobalIds = $histories
            ->where('type', 'building_table')
            ->pluck('global_id')
            ->filter()
            ->values();

        $housingGlobalIds = $histories
            ->where('type', 'housing_table')
            ->pluck('global_id')
            ->filter()
            ->values();

        $housingUnits = HousingUnit::query()
            ->whereIn('globalid', $housingGlobalIds)
            ->get(['objectid', 'globalid', 'parentglobalid', 'housing_unit_number'])
            ->keyBy('globalid');

        $buildingGlobalIds = $buildingGlobalIds
            ->merge($housingUnits->pluck('parentglobalid')->filter())
            ->unique()
            ->values();

        $buildings = Building::query()
            ->whereIn('globalid', $buildingGlobalIds)
            ->get(['objectid', 'globalid', 'building_name'])
            ->keyBy('globalid');

        return $histories->map(function (AssessmentEditHistory $history) use ($buildings, $housingUnits): array {
            $housingUnit = $history->type === 'housing_table'
                ? $housingUnits->get($history->global_id)
                : null;

            $buildingGlobalId = $history->type === 'housing_table'
                ? $housingUnit?->parentglobalid
                : $history->global_id;
            $building = $buildingGlobalId ? $buildings->get($buildingGlobalId) : null;

            return [
                'id' => $history->id,
                'record_type' => $history->type,
                'record_type_label' => $history->type === 'housing_table' ? 'وحدة' : 'مبنى',
                'objectid' => $history->objectid,
                'global_id' => $history->global_id,
                'building_name' => $building?->building_name,
                'building_objectid' => $building?->objectid,
                'housing_unit_number' => $housingUnit?->housing_unit_number,
                'field_name' => $history->field_name,
                'field_label' => $this->fieldLabel($history->field_name),
                'old_value' => $history->old_value,
                'new_value' => $history->new_value,
                'engineer_name' => $history->user?->name ?? '-',
                'edited_at' => optional($history->created_at)->format('Y-m-d H:i'),
                'assessment_url' => $buildingGlobalId
                    ? url("/damage-assessment/showAssessmentAudit/{$buildingGlobalId}".($history->type === 'housing_table' ? "/{$history->global_id}" : ''))
                    : null,
            ];
        });
    }

    private function fieldLabel(string $fieldName): string
    {
        return $this->fieldLabels()->get($fieldName, $fieldName);
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
            ->mapWithKeys(function (Assessment $assessment) use ($labelColumn, $fallbackColumn): array {
                $label = $assessment->{$labelColumn}
                    ?: $assessment->{$fallbackColumn}
                    ?: $assessment->name;

                return [$assessment->name => $label];
            });

        return $this->fieldLabels;
    }
}
