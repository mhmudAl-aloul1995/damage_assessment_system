<?php

namespace App\Services;

use App\Models\AssessmentEditHistory;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\EditAssessment;
use Illuminate\Support\Facades\Schema;

class FieldReturnAssessmentChangeService
{
    public const SOURCE = 'field_sync';

    /**
     * @param  array<string, mixed>  $incomingRow
     * @param  array<int, string>  $tableColumns
     */
    public function recordBuildingChanges(object $existingBuilding, array $incomingRow, array $tableColumns): int
    {
        if (! $this->requiredTablesExist()) {
            return 0;
        }

        if (! $this->isCompleted(data_get($existingBuilding, 'field_status'))) {
            return 0;
        }

        if (! $this->isCompleted($incomingRow['field_status'] ?? null)) {
            return 0;
        }

        $archiveObject = $this->latestCompletedReturnArchive($existingBuilding);

        if (! $archiveObject instanceof BuildingSurveyArchiveObject || ! is_array($archiveObject->building_snapshot)) {
            return 0;
        }

        $globalId = (string) ($incomingRow['globalid'] ?? data_get($existingBuilding, 'globalid', ''));

        if ($globalId === '') {
            return 0;
        }

        $changes = 0;
        $fillableFields = array_flip((new Building)->getFillable());

        foreach ($incomingRow as $fieldName => $newValue) {
            if (! is_string($fieldName) || ! isset($fillableFields[$fieldName])) {
                continue;
            }

            if (! in_array($fieldName, $tableColumns, true) || $this->shouldIgnoreField($fieldName)) {
                continue;
            }

            if (! array_key_exists($fieldName, $archiveObject->building_snapshot)) {
                continue;
            }

            $oldValue = $archiveObject->building_snapshot[$fieldName];

            if ($this->sameValue($oldValue, $newValue)) {
                continue;
            }

            if ($this->historyAlreadyRecorded($archiveObject, $globalId, $fieldName)) {
                continue;
            }

            $edit = EditAssessment::query()->create([
                'global_id' => $globalId,
                'type' => 'building_table',
                'field_name' => $fieldName,
                'field_value' => $this->toStoredValue($newValue),
                'user_id' => null,
            ]);

            AssessmentEditHistory::query()->create([
                'global_id' => $globalId,
                'objectid' => data_get($existingBuilding, 'objectid'),
                'type' => 'building_table',
                'field_name' => $fieldName,
                'old_value' => $this->toStoredValue($oldValue),
                'new_value' => $this->toStoredValue($newValue),
                'edited_by' => null,
                'edit_assessment_id' => $edit->id,
                'return_request_id' => $archiveObject->return_request_id,
                'source' => self::SOURCE,
            ]);

            $changes++;
        }

        return $changes;
    }

    private function latestCompletedReturnArchive(object $existingBuilding): ?BuildingSurveyArchiveObject
    {
        $objectId = data_get($existingBuilding, 'objectid');
        $globalId = data_get($existingBuilding, 'globalid');

        if (($objectId === null || $objectId === '') && ($globalId === null || $globalId === '')) {
            return null;
        }

        return BuildingSurveyArchiveObject::query()
            ->whereNotNull('return_request_id')
            ->whereHas('request', function ($query): void {
                $query->where('status', 'completed');
            })
            ->where(function ($query) use ($globalId, $objectId): void {
                if ($objectId !== null && $objectId !== '') {
                    $query->where('building_objectid', $objectId);
                }

                if ($globalId !== null && $globalId !== '') {
                    $query->orWhere('building_globalid', $globalId);
                }
            })
            ->latest('archived_at')
            ->latest('id')
            ->first();
    }

    private function requiredTablesExist(): bool
    {
        foreach (['building_survey_archive_objects', 'building_survey_return_requests', 'edit_assessments', 'assessment_edit_histories'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function shouldIgnoreField(string $fieldName): bool
    {
        return in_array($fieldName, [
            'id',
            'objectid',
            'globalid',
            'created_at',
            'updated_at',
            'arcgis_hash',
            'arcgis_synced_at',
            'all_data',
            'raw_payload',
            'creationdate',
            'editdate',
            'creator',
            'editor',
            'latitude',
            'longitude',
            'location',
            'shape__area',
            'shape__length',
        ], true);
    }

    private function historyAlreadyRecorded(BuildingSurveyArchiveObject $archiveObject, string $globalId, string $fieldName): bool
    {
        return AssessmentEditHistory::query()
            ->where('return_request_id', $archiveObject->return_request_id)
            ->where('global_id', $globalId)
            ->where('type', 'building_table')
            ->where('field_name', $fieldName)
            ->where('source', self::SOURCE)
            ->exists();
    }

    private function sameValue(mixed $oldValue, mixed $newValue): bool
    {
        return $this->normalizeComparableValue($oldValue) === $this->normalizeComparableValue($newValue);
    }

    private function normalizeComparableValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return trim((string) $value);
    }

    private function toStoredValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private function isCompleted(mixed $value): bool
    {
        return strtoupper(trim((string) $value)) === 'COMPLETED';
    }
}
