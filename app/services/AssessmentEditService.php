<?php

namespace App\services;

use App\Jobs\SyncAssessmentAssignmentToSourceArcgis;
use App\Jobs\SyncAuditEditToArcgis;
use App\Models\AssessmentEditHistory;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AssessmentEditService
{
    /**
     * @return array{changed: bool, edit: EditAssessment|null, history: AssessmentEditHistory|null, old_value: mixed, new_value: mixed}
     */
    public function save(string $type, string $globalId, string $fieldName, mixed $newValue, Request $request): array
    {
        $modelClass = $type === 'building_table'
            ? Building::class
            : HousingUnit::class;

        $fillable = (new $modelClass)->getFillable();

        if (! in_array($fieldName, $fillable, true)) {
            throw ValidationException::withMessages([
                'field' => 'هذا الحقل غير قابل للتعديل',
            ]);
        }

        if (is_array($newValue)) {
            $newValue = implode(',', $newValue);
        }

        $result = DB::transaction(function () use ($modelClass, $type, $globalId, $fieldName, $newValue, $request): array {
            /** @var Building|HousingUnit $record */
            $record = $modelClass::query()
                ->where('globalid', $globalId)
                ->firstOrFail();

            $edit = EditAssessment::query()
                ->where('global_id', $globalId)
                ->where('type', $type)
                ->where('field_name', $fieldName)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $oldValue = $edit?->field_value ?? $this->originalValue($record, $fieldName);

            if (trim((string) $oldValue) === trim((string) $newValue)) {
                return [
                    'changed' => false,
                    'edit' => $edit,
                    'history' => null,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ];
            }

            $edit = EditAssessment::query()->create([
                'global_id' => $globalId,
                'type' => $type,
                'field_name' => $fieldName,
                'field_value' => $newValue,
                'user_id' => auth()->id(),
            ]);

            $history = AssessmentEditHistory::query()->create([
                'global_id' => $globalId,
                'objectid' => $record->objectid,
                'type' => $type,
                'field_name' => $fieldName,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'edited_by' => auth()->id(),
                'edit_assessment_id' => $edit->id,
                'return_request_id' => $this->returnRequestId($type, $record),
                'source' => 'manual',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $this->syncAuditedCacheRow($type, $record, $fieldName, $newValue);
            $this->syncSourceAssignmentRow($record, $fieldName, $newValue);

            return [
                'changed' => true,
                'edit' => $edit->load('user'),
                'history' => $history,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ];
        });

        if ($result['changed'] && $this->shouldSyncInlineAuditEditToArcgis()) {
            SyncAuditEditToArcgis::dispatch($type, $globalId, $fieldName, $newValue)
                ->afterCommit()
                ->onQueue('arcgis');
        }

        if ($result['changed'] && $this->shouldSyncSourceAssignmentToArcgis($fieldName)) {
            SyncAssessmentAssignmentToSourceArcgis::dispatch($type, $globalId, $newValue)
                ->afterCommit()
                ->onQueue('arcgis');
        }

        if ($result['changed']) {
            $this->bustDashboardStatsCache();
        }

        return $result;
    }

    private function originalValue(Model $record, string $fieldName): mixed
    {
        return array_key_exists($fieldName, $record->getAttributes())
            ? $record->getRawOriginal($fieldName)
            : null;
    }

    private function syncAuditedCacheRow(string $type, Model $record, string $fieldName, mixed $newValue): void
    {
        $table = $type === 'building_table'
            ? 'audited_buildings'
            : 'audited_housing_units';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $fieldName)) {
            return;
        }

        $cacheColumns = Schema::getColumnListing($table);
        $row = [$fieldName => $newValue];

        if (in_array('is_audited', $cacheColumns, true)) {
            $row['is_audited'] = true;
        }

        if (in_array('last_audit_user_id', $cacheColumns, true)) {
            $row['last_audit_user_id'] = auth()->id();
        }

        if (in_array('last_audit_at', $cacheColumns, true)) {
            $row['last_audit_at'] = now();
        }

        if ($fieldName === 'field_status') {
            if (in_array('last_status_user_id', $cacheColumns, true)) {
                $row['last_status_user_id'] = auth()->id();
            }

            if (in_array('last_status_at', $cacheColumns, true)) {
                $row['last_status_at'] = now();
            }
        }

        if (in_array('updated_at', $cacheColumns, true)) {
            $row['updated_at'] = now();
        }

        if (in_array('created_at', $cacheColumns, true)) {
            $row['created_at'] = $row['created_at'] ?? now();
        }

        $key = $record->getAttribute('objectid') !== null
            ? ['objectid' => $record->getAttribute('objectid')]
            : ['globalid' => $record->getAttribute('globalid')];

        $query = DB::table($table);

        foreach ($key as $column => $value) {
            $query->where($column, $value);
        }

        if ($query->exists()) {
            DB::table($table)->where($key)->update($row);

            return;
        }

        $attributes = $record->getAttributes();

        foreach ($cacheColumns as $column) {
            if (! array_key_exists($column, $row) && array_key_exists($column, $attributes)) {
                $row[$column] = $attributes[$column];
            }
        }

        DB::table($table)->insert($row);
    }

    private function syncSourceAssignmentRow(Model $record, string $fieldName, mixed $newValue): void
    {
        if ($fieldName !== 'assignedto') {
            return;
        }

        $record->forceFill([
            'assignedto' => $newValue,
        ])->saveQuietly();
    }

    private function returnRequestId(string $type, Model $record): ?int
    {
        $building = null;

        if ($type === 'building_table') {
            $building = $record;
        }

        if ($type === 'housing_table') {
            $building = Building::query()
                ->where('globalid', $record->getAttribute('parentglobalid'))
                ->first();
        }

        if (! $building) {
            return null;
        }

        $archiveObject = BuildingSurveyArchiveObject::query()
            ->where(function ($query) use ($building): void {
                if ($building->objectid) {
                    $query->where('building_objectid', $building->objectid);
                }

                if ($building->globalid) {
                    $query->orWhere('building_globalid', $building->globalid);
                }
            })
            ->latest('archived_at')
            ->latest('id')
            ->first();

        return $archiveObject?->return_request_id;
    }

    private function shouldSyncInlineAuditEditToArcgis(): bool
    {
        foreach (['username', 'password', 'referer', 'target_service', 'source_service'] as $key) {
            $value = config('services.arcgis.'.$key);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function shouldSyncSourceAssignmentToArcgis(string $fieldName): bool
    {
        if ($fieldName !== 'assignedto') {
            return false;
        }

        foreach (['username', 'password', 'referer', 'source_service'] as $key) {
            $value = config('services.arcgis.'.$key);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function bustDashboardStatsCache(): void
    {
        Cache::add('damage_dashboard.stats_version', 1);
        Cache::increment('damage_dashboard.stats_version');
    }
}
