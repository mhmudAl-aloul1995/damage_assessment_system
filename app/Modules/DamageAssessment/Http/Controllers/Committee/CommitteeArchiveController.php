<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\HousingUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CommitteeArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $archives = BuildingSurveyArchiveObject::query()
            ->with(['building', 'committeeDecision.signatures.committeeMember'])
            ->whereIn('source_type', ['committee_decision', 'temporary_committee_excel_archive'])
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->string('source_type')))
            ->when($request->filled('record_type'), function ($query) use ($request): void {
                if ($request->string('record_type')->toString() === 'housing-unit') {
                    $query->whereNotNull('housing_unit_objectid');

                    return;
                }

                if ($request->string('record_type')->toString() === 'building') {
                    $query->whereNull('housing_unit_objectid');
                }
            })
            ->when($request->filled('objectid'), function ($query) use ($request): void {
                $objectId = $request->string('objectid')->toString();

                $query->where(function ($query) use ($objectId): void {
                    $query
                        ->where('building_objectid', $objectId)
                        ->orWhere('housing_unit_objectid', $objectId);
                });
            })
            ->when($request->filled('snapshot'), function ($query) use ($request): void {
                if ($request->string('snapshot')->toString() === 'missing') {
                    $query->whereNull('building_snapshot');

                    return;
                }

                if ($request->string('snapshot')->toString() === 'available') {
                    $query->whereNotNull('building_snapshot');
                }
            })
            ->when($request->filled('municipality'), function ($query) use ($request): void {
                $municipality = $request->string('municipality')->toString();

                $query->where(function ($query) use ($municipality): void {
                    $query
                        ->where('building_snapshot->municipalitie', $municipality)
                        ->orWhere('housing_unit_snapshot->municipalitie', $municipality)
                        ->orWhereHas('building', fn ($query) => $query->where('municipalitie', $municipality))
                        ->orWhereExists(
                            HousingUnit::query()
                                ->selectRaw('1')
                                ->whereColumn('housing_units.objectid', 'building_survey_archive_objects.housing_unit_objectid')
                                ->where('municipalitie', $municipality)
                        );
                });
            })
            ->when($request->filled('old_damage_status'), function ($query) use ($request): void {
                $status = $request->string('old_damage_status')->toString();

                $query->where(function ($query) use ($status): void {
                    $query
                        ->where('building_snapshot->building_damage_status', $status)
                        ->orWhere('housing_unit_snapshot->unit_damage_status', $status);
                });
            })
            ->when($request->filled('current_damage_status'), function ($query) use ($request): void {
                $status = $request->string('current_damage_status')->toString();

                $query->where(function ($query) use ($status): void {
                    $query
                        ->whereHas('building', fn ($query) => $query->where('building_damage_status', $status))
                        ->orWhereExists(
                            HousingUnit::query()
                                ->selectRaw('1')
                                ->whereColumn('housing_units.objectid', 'building_survey_archive_objects.housing_unit_objectid')
                                ->where('unit_damage_status', $status)
                        );
                });
            })
            ->when($request->filled('field_status'), function ($query) use ($request): void {
                $fieldStatus = $request->string('field_status')->toString();

                $query->where(function ($query) use ($fieldStatus): void {
                    $query
                        ->where('building_snapshot->field_status', $fieldStatus)
                        ->orWhereHas('building', fn ($query) => $query->where('field_status', $fieldStatus));
                });
            })
            ->when($request->filled('archived_from'), fn ($query) => $query->whereDate('archived_at', '>=', $request->date('archived_from')))
            ->when($request->filled('archived_to'), fn ($query) => $query->whereDate('archived_at', '<=', $request->date('archived_to')))
            ->latest('archived_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('damage-assessment::committee.archive.index', [
            'archives' => $archives,
            'filters' => $request->only([
                'source_type',
                'record_type',
                'objectid',
                'snapshot',
                'municipality',
                'old_damage_status',
                'current_damage_status',
                'field_status',
                'archived_from',
                'archived_to',
            ]),
            'municipalities' => $this->municipalityOptions(),
        ]);
    }

    public function show(BuildingSurveyArchiveObject $archiveObject): View
    {
        abort_unless(in_array($archiveObject->source_type, ['committee_decision', 'temporary_committee_excel_archive'], true), 404);

        $archiveObject->loadMissing([
            'committeeDecision.signatures.committeeMember',
            'committeeDecision.signatures.signedByUser',
        ]);

        $currentBuilding = Building::query()
            ->where('objectid', $archiveObject->building_objectid)
            ->first();

        $currentHousingUnit = $archiveObject->housing_unit_objectid === null
            ? null
            : HousingUnit::query()
                ->where('objectid', $archiveObject->housing_unit_objectid)
                ->first();
        $decisionSnapshot = $archiveObject->committee_decision_snapshot;

        if (is_array($decisionSnapshot)) {
            unset($decisionSnapshot['committee_members']);
        }

        $surveyFields = $this->surveyFields();

        return view('damage-assessment::committee.archive.show', [
            'archiveObject' => $archiveObject,
            'currentBuilding' => $currentBuilding,
            'currentHousingUnit' => $currentHousingUnit,
            'buildingRows' => $this->comparisonRows(
                $archiveObject->building_snapshot,
                $currentBuilding?->attributesToArray(),
                $this->surveyFieldsForRecords($archiveObject->building_snapshot, $currentBuilding?->attributesToArray(), $surveyFields, $this->buildingFields()),
            ),
            'housingRows' => $this->comparisonRows(
                $archiveObject->housing_unit_snapshot,
                $currentHousingUnit?->attributesToArray(),
                $this->surveyFieldsForRecords($archiveObject->housing_unit_snapshot, $currentHousingUnit?->attributesToArray(), $surveyFields, $this->housingUnitFields()),
            ),
            'decisionRows' => $this->comparisonRows(
                $decisionSnapshot,
                $archiveObject->committeeDecision?->attributesToArray(),
                $this->decisionFields(),
            ),
            'committeeMembers' => $this->committeeMembers($archiveObject),
        ]);
    }

    /**
     * @return list<array{name: string|null, title: string|null, status: string|null, notes: string|null, signed_at: string|null, signed_by: string|null}>
     */
    private function committeeMembers(BuildingSurveyArchiveObject $archiveObject): array
    {
        $archivedMembers = data_get($archiveObject->committee_decision_snapshot, 'committee_members');

        if (is_array($archivedMembers)) {
            return $archivedMembers;
        }

        return $archiveObject->committeeDecision?->signatures
            ->sortBy('sort_order')
            ->map(fn ($signature): array => [
                'name' => $signature->committeeMember?->name,
                'title' => $signature->committeeMember?->title,
                'status' => $signature->status,
                'notes' => $signature->notes,
                'signed_at' => $signature->signed_at?->toDateTimeString(),
                'signed_by' => $signature->signedByUser?->name,
            ])
            ->values()
            ->all() ?? [];
    }

    /**
     * @param  array<string, mixed>|null  $oldRecord
     * @param  array<string, mixed>|null  $currentRecord
     * @param  array<string, string>  $priorityFields
     * @return list<array{label: string, old: mixed, current: mixed, changed: bool}>
     */
    private function comparisonRows(?array $oldRecord, ?array $currentRecord, array $priorityFields): array
    {
        $rows = [];
        $fields = collect($priorityFields);

        foreach ($fields as $field => $label) {
            $oldValue = $oldRecord[$field] ?? null;
            $currentValue = $currentRecord[$field] ?? null;

            $rows[] = [
                'label' => $label,
                'old' => $oldValue,
                'current' => $currentValue,
                'changed' => (string) $oldValue !== (string) $currentValue,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function buildingFields(): array
    {
        return [
            'objectid' => 'ObjectID',
            'globalid' => 'GlobalID',
            'building_name' => 'اسم المبنى',
            'owner_name' => 'اسم المالك',
            'municipalitie' => 'البلدية',
            'neighborhood' => 'الحي',
            'assignedto' => 'المهندس الميداني',
            'building_damage_status' => 'حالة ضرر المبنى',
            'field_status' => 'حالة الميدان',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function housingUnitFields(): array
    {
        return [
            'objectid' => 'ObjectID',
            'globalid' => 'GlobalID',
            'housing_unit_number' => 'رقم الوحدة',
            'unit_owner' => 'مالك الوحدة',
            'neighborhood' => 'الحي',
            'unit_damage_status' => 'حالة ضرر الوحدة',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function decisionFields(): array
    {
        return [
            'decision_type' => 'نوع القرار',
            'decision_text' => 'نص القرار',
            'action_text' => 'الإجراء',
            'status' => 'حالة القرار',
            'decision_date' => 'تاريخ القرار',
            'completed_at' => 'تاريخ الاكتمال',
            'arcgis_sync_status' => 'حالة ArcGIS',
        ];
    }

    /**
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    private function defaultVisibleFields(): array
    {
        return [
            'objectid' => 'ObjectID',
            'parcel_no1' => 'رقم القطعة',
            'assignedto' => 'اسم الباحث',
            'building_name' => 'Building Name',
            'shape__length' => 'Shape__Length',
            'creationdate' => 'CreationDate',
            'editdate' => 'EditDate',
            'editor' => 'Editor',
            'security_info' => 'security information',
            'governorate' => 'المحافظة',
            'neighborhood' => 'الحي',
            'decision_type' => 'قرار اللجنة',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function surveyFields(): array
    {
        $columns = ['name', 'label', 'hint'];

        if (Schema::hasColumn('assessments', 'appearance')) {
            $columns[] = 'appearance';
        }

        $query = Assessment::query()->select($columns);

        if (Schema::hasColumn('assessments', 'appearance')) {
            $query->where(fn ($query) => $query
                ->whereNull('appearance')
                ->orWhere('appearance', '!=', 'hidden'));
        }

        return $query
            ->get()
            ->filter(fn (Assessment $assessment): bool => filled($assessment->name))
            ->mapWithKeys(fn (Assessment $assessment): array => [
                (string) $assessment->name => filled($assessment->hint)
                    ? (string) $assessment->hint
                    : (string) ($assessment->label ?: $assessment->name),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $oldRecord
     * @param  array<string, mixed>|null  $currentRecord
     * @param  array<string, string>  $surveyFields
     * @param  array<string, string>  $fallbackFields
     * @return array<string, string>
     */
    private function surveyFieldsForRecords(?array $oldRecord, ?array $currentRecord, array $surveyFields, array $fallbackFields): array
    {
        $recordFields = collect(array_keys($oldRecord ?? []))
            ->merge(array_keys($currentRecord ?? []))
            ->unique()
            ->flip()
            ->all();

        $fields = array_intersect_key($surveyFields, $recordFields);

        return $fields ?: $fallbackFields;
    }

    /**
     * @return list<string>
     */
    private function municipalityOptions(): array
    {
        return collect()
            ->merge(Building::query()
                ->whereNotNull('municipalitie')
                ->distinct()
                ->orderBy('municipalitie')
                ->pluck('municipalitie'))
            ->merge(HousingUnit::query()
                ->whereNotNull('municipalitie')
                ->distinct()
                ->orderBy('municipalitie')
                ->pluck('municipalitie'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
