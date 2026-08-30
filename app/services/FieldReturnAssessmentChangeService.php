<?php

namespace App\services;

use App\Models\AssessmentEditHistory;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        return $this->recordChanges(
            existingRecord: $existingBuilding,
            incomingRow: $incomingRow,
            tableColumns: $tableColumns,
            fillableFields: (new Building)->getFillable(),
            type: 'building_table',
            snapshotColumn: 'building_snapshot',
            archiveObject: $this->latestCompletedBuildingReturnArchive($existingBuilding),
        );
    }

    /**
     * @param  array<string, mixed>  $incomingRow
     * @param  array<int, string>  $tableColumns
     */
    public function recordHousingUnitChanges(object $existingHousingUnit, array $incomingRow, array $tableColumns): int
    {
        return $this->recordChanges(
            existingRecord: $existingHousingUnit,
            incomingRow: $incomingRow,
            tableColumns: $tableColumns,
            fillableFields: (new HousingUnit)->getFillable(),
            type: 'housing_table',
            snapshotColumn: 'housing_unit_snapshot',
            archiveObject: $this->latestCompletedHousingUnitReturnArchive($existingHousingUnit),
        );
    }

    /**
     * @param  array<string, mixed>  $incomingRow
     * @param  array<int, string>  $tableColumns
     * @param  array<int, string>  $fillableFields
     */
    private function recordChanges(
        object $existingRecord,
        array $incomingRow,
        array $tableColumns,
        array $fillableFields,
        string $type,
        string $snapshotColumn,
        ?BuildingSurveyArchiveObject $archiveObject,
    ): int {
        if (! $this->requiredTablesExist()) {
            return 0;
        }

        if (! $this->isCompletedStatus($existingRecord, $incomingRow, $type)) {
            return 0;
        }

        $snapshot = $archiveObject?->{$snapshotColumn};

        if (! $archiveObject instanceof BuildingSurveyArchiveObject || ! is_array($snapshot)) {
            return 0;
        }

        $globalId = (string) ($incomingRow['globalid'] ?? data_get($existingRecord, 'globalid', ''));

        if ($globalId === '') {
            return 0;
        }

        $editorUserId = $this->fieldEngineerUserId($incomingRow, $existingRecord);
        $changes = 0;
        $fillableFields = array_flip($fillableFields);

        foreach ($incomingRow as $fieldName => $newValue) {
            if (! is_string($fieldName) || ! isset($fillableFields[$fieldName])) {
                continue;
            }

            if (! in_array($fieldName, $tableColumns, true) || $this->shouldIgnoreField($fieldName)) {
                continue;
            }

            $oldValue = array_key_exists($fieldName, $snapshot)
                ? $snapshot[$fieldName]
                : data_get($existingRecord, $fieldName);

            if ($this->sameValue($oldValue, $newValue)) {
                continue;
            }

            if ($this->historyAlreadyRecorded($archiveObject, $globalId, $fieldName)) {
                continue;
            }

            $edit = EditAssessment::query()->create([
                'global_id' => $globalId,
                'type' => $type,
                'field_name' => $fieldName,
                'field_value' => $this->toStoredValue($newValue),
                'user_id' => $editorUserId,
            ]);

            AssessmentEditHistory::query()->create([
                'global_id' => $globalId,
                'objectid' => data_get($existingRecord, 'objectid'),
                'type' => $type,
                'field_name' => $fieldName,
                'old_value' => $this->toStoredValue($oldValue),
                'new_value' => $this->toStoredValue($newValue),
                'edited_by' => $editorUserId,
                'edit_assessment_id' => $edit->id,
                'return_request_id' => $archiveObject->return_request_id,
                'source' => self::SOURCE,
            ]);

            $changes++;
        }

        return $changes;
    }

    private function fieldEngineerUserId(array $incomingRow, object $existingRecord): ?int
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        $arcgisUsername = trim((string) ($incomingRow['assignedto'] ?? data_get($existingRecord, 'assignedto', '')));

        if ($arcgisUsername === '' && Schema::hasTable('buildings')) {
            $parentGlobalId = $incomingRow['parentglobalid'] ?? data_get($existingRecord, 'parentglobalid');

            if ($parentGlobalId !== null && $parentGlobalId !== '') {
                $arcgisUsername = trim((string) DB::table('buildings')
                    ->where('globalid', $parentGlobalId)
                    ->value('assignedto'));
            }
        }

        if ($arcgisUsername === '') {
            return null;
        }

        return User::query()
            ->where('username_arcgis', $arcgisUsername)
            ->value('id');
    }

    private function isCompletedStatus(object $existingRecord, array $incomingRow, string $type): bool
    {
        $fieldName = $type === 'housing_table' ? 'building_field_status' : 'field_status';

        if (! $this->isCompleted(data_get($existingRecord, $fieldName))) {
            return false;
        }

        return $this->isCompleted($incomingRow[$fieldName] ?? null);
    }

    private function latestCompletedBuildingReturnArchive(object $existingBuilding): ?BuildingSurveyArchiveObject
    {
        $objectId = data_get($existingBuilding, 'objectid');
        $globalId = data_get($existingBuilding, 'globalid');

        if (($objectId === null || $objectId === '') && ($globalId === null || $globalId === '')) {
            return null;
        }

        return BuildingSurveyArchiveObject::query()
            ->whereNotNull('return_request_id')
            ->whereNull('housing_unit_objectid')
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

    private function latestCompletedHousingUnitReturnArchive(object $existingHousingUnit): ?BuildingSurveyArchiveObject
    {
        $objectId = data_get($existingHousingUnit, 'objectid');
        $globalId = data_get($existingHousingUnit, 'globalid');

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
                    $query->where('housing_unit_objectid', $objectId);
                }

                if ($globalId !== null && $globalId !== '') {
                    $query->orWhere('housing_unit_globalid', $globalId);
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
        $oldNormalizedValue = $this->normalizeComparableValue($oldValue);
        $newNormalizedValue = $this->normalizeComparableValue($newValue);

        if ($oldNormalizedValue === $newNormalizedValue) {
            return true;
        }

        $oldGroups = $this->normalizeComparableGroups($oldValue);
        $newGroups = $this->normalizeComparableGroups($newValue);

        if (count($oldGroups) !== count($newGroups)) {
            return false;
        }

        foreach ($oldGroups as $index => $oldCandidates) {
            if (array_intersect($oldCandidates, $newGroups[$index] ?? []) === []) {
                return false;
            }
        }

        return true;
    }

    private function normalizeComparableValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $this->normalizeComparableToken($value);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function normalizeComparableGroups(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [[null]];
        }

        if (is_array($value) || is_object($value)) {
            return [[$this->normalizeComparableValue($value)]];
        }

        $parts = array_values(array_filter(
            preg_split('/\s*,\s*/u', trim((string) $value)) ?: [],
            fn (string $part): bool => $part !== ''
        ));

        if ($parts === []) {
            return [[null]];
        }

        return collect($parts)
            ->map(function (string $part): array {
                $candidates = [$this->normalizeComparableToken($part)];

                foreach (preg_split('/\s*\/\s*/u', $part) ?: [] as $segment) {
                    $normalizedSegment = $this->normalizeComparableToken($segment);

                    if ($normalizedSegment !== null) {
                        $candidates[] = $normalizedSegment;
                    }
                }

                return array_values(array_unique(array_filter($candidates)));
            })
            ->all();
    }

    private function normalizeComparableToken(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value)
            : strtolower($value);

        return preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $value));
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
