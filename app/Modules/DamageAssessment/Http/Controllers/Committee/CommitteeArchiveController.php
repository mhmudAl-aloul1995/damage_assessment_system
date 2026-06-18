<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\HousingUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CommitteeArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $archives = BuildingSurveyArchiveObject::query()
            ->with('committeeDecision')
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
            ->latest('archived_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('damage-assessment::committee.archive.index', [
            'archives' => $archives,
            'filters' => $request->only(['source_type', 'record_type', 'objectid', 'snapshot']),
        ]);
    }

    public function show(BuildingSurveyArchiveObject $archiveObject): View
    {
        abort_unless(in_array($archiveObject->source_type, ['committee_decision', 'temporary_committee_excel_archive'], true), 404);

        $archiveObject->loadMissing('committeeDecision');

        $currentBuilding = Building::query()
            ->where('objectid', $archiveObject->building_objectid)
            ->first();

        $currentHousingUnit = $archiveObject->housing_unit_objectid === null
            ? null
            : HousingUnit::query()
                ->where('objectid', $archiveObject->housing_unit_objectid)
                ->first();

        return view('damage-assessment::committee.archive.show', [
            'archiveObject' => $archiveObject,
            'currentBuilding' => $currentBuilding,
            'currentHousingUnit' => $currentHousingUnit,
            'buildingRows' => $this->comparisonRows(
                $archiveObject->building_snapshot,
                $currentBuilding?->attributesToArray(),
                $this->buildingFields(),
            ),
            'housingRows' => $this->comparisonRows(
                $archiveObject->housing_unit_snapshot,
                $currentHousingUnit?->attributesToArray(),
                $this->housingUnitFields(),
            ),
            'decisionRows' => $this->comparisonRows(
                $archiveObject->committee_decision_snapshot,
                $archiveObject->committeeDecision?->attributesToArray(),
                $this->decisionFields(),
            ),
        ]);
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
        $allFields = collect(array_keys($oldRecord ?? []))
            ->merge(array_keys($currentRecord ?? []))
            ->unique()
            ->values();

        $fields = collect($priorityFields)
            ->merge($allFields
                ->reject(fn (string $field): bool => array_key_exists($field, $priorityFields))
                ->sort()
                ->mapWithKeys(fn (string $field): array => [$field => $field]));

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
}
