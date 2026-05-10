<?php

namespace App\Services;

use App\Models\AssessmentEditHistory;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return DB::transaction(function () use ($modelClass, $type, $globalId, $fieldName, $newValue, $request): array {
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

            if ($edit) {
                $edit->forceFill([
                    'field_value' => $newValue,
                    'user_id' => auth()->id(),
                ])->save();
            } else {
                $edit = EditAssessment::query()->create([
                    'global_id' => $globalId,
                    'type' => $type,
                    'field_name' => $fieldName,
                    'field_value' => $newValue,
                    'user_id' => auth()->id(),
                ]);
            }

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

            return [
                'changed' => true,
                'edit' => $edit->load('user'),
                'history' => $history,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ];
        });
    }

    private function originalValue(Model $record, string $fieldName): mixed
    {
        return array_key_exists($fieldName, $record->getAttributes())
            ? $record->getRawOriginal($fieldName)
            : null;
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
}
