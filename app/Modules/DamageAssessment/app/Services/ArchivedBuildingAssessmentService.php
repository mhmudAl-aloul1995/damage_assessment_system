<?php

namespace App\Modules\DamageAssessment\Services;

use App\Models\BuildingDeletionSnapshot;
use App\Models\BuildingSurveyArchiveObject;
use Illuminate\Support\Collection;

class ArchivedBuildingAssessmentService
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $buildingGlobalId, string $source): ?array
    {
        $snapshot = BuildingDeletionSnapshot::query()
            ->with('request')
            ->where('building_globalid', $buildingGlobalId)
            ->latest('verified_at')
            ->latest('id')
            ->first();

        $archiveObjects = BuildingSurveyArchiveObject::query()
            ->where('source_type', 'building_deletion_management')
            ->where('building_globalid', $buildingGlobalId)
            ->whereNotNull('building_snapshot')
            ->latest('archived_at')
            ->latest('id')
            ->get();

        if (! $snapshot && $archiveObjects->isEmpty()) {
            return null;
        }

        $preferredData = $source === 'base' ? $snapshot?->base_data : $snapshot?->audited_data;
        $fallbackData = $source === 'base' ? $snapshot?->audited_data : $snapshot?->base_data;

        $building = $this->buildingFromSnapshot($preferredData)
            ?? $this->buildingFromSnapshot($fallbackData)
            ?? $this->buildingFromArchive($archiveObjects);

        if ($building === null) {
            return null;
        }

        $housingUnits = $this->housingUnitsFromSnapshot($preferredData);

        if ($housingUnits === []) {
            $housingUnits = $this->housingUnitsFromSnapshot($fallbackData);
        }

        if ($housingUnits === []) {
            $housingUnits = $this->housingUnitsFromArchive($archiveObjects);
        }

        return [
            'source' => $source,
            'buildingGlobalId' => $buildingGlobalId,
            'buildingRecord' => $building,
            'housingUnitRecords' => $housingUnits,
            'snapshot' => $snapshot,
            'archiveObject' => $archiveObjects->first(),
            'deletionRequest' => $snapshot?->request,
            'archivedAt' => $snapshot?->verified_at ?? $archiveObjects->first()?->archived_at,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshotData
     * @return array<string, mixed>|null
     */
    private function buildingFromSnapshot(?array $snapshotData): ?array
    {
        $databaseRecord = data_get($snapshotData, 'building.database');

        if ($this->hasMeaningfulValues($databaseRecord)) {
            return $databaseRecord;
        }

        $gisRecord = data_get($snapshotData, 'building.gis.feature.attributes');

        return $this->hasMeaningfulValues($gisRecord) ? $gisRecord : null;
    }

    /**
     * @param  Collection<int, BuildingSurveyArchiveObject>  $archiveObjects
     * @return array<string, mixed>|null
     */
    private function buildingFromArchive(Collection $archiveObjects): ?array
    {
        return $archiveObjects
            ->pluck('building_snapshot')
            ->first(fn (mixed $record): bool => $this->hasMeaningfulValues($record));
    }

    /**
     * @param  array<string, mixed>|null  $snapshotData
     * @return array<int, array<string, mixed>>
     */
    private function housingUnitsFromSnapshot(?array $snapshotData): array
    {
        return collect(data_get($snapshotData, 'housing_units', []))
            ->map(function (mixed $unit): ?array {
                $databaseRecord = data_get($unit, 'database');

                if ($this->hasMeaningfulValues($databaseRecord)) {
                    return $databaseRecord;
                }

                $gisRecord = data_get($unit, 'gis.feature.attributes');

                return $this->hasMeaningfulValues($gisRecord) ? $gisRecord : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, BuildingSurveyArchiveObject>  $archiveObjects
     * @return array<int, array<string, mixed>>
     */
    private function housingUnitsFromArchive(Collection $archiveObjects): array
    {
        return $archiveObjects
            ->pluck('housing_unit_snapshot')
            ->filter(fn (mixed $record): bool => $this->hasMeaningfulValues($record))
            ->values()
            ->all();
    }

    private function hasMeaningfulValues(mixed $record): bool
    {
        if (! is_array($record)) {
            return false;
        }

        return collect($record)->contains(fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
