<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ArcgisAuditedCacheService
{
    /**
     * @return array<string, int>
     */
    public function refresh(?int $buildingsLimit = null): array
    {
        $this->ensureCacheTablesExist();

        DB::table('audited_housing_units')->truncate();
        DB::table('audited_buildings')->truncate();

        $summary = [
            'buildings_cached' => 0,
            'housing_units_cached' => 0,
        ];

        $buildingGlobalIds = $this->refreshBuildings($summary, $buildingsLimit);
        $this->refreshHousingUnits($summary, $buildingsLimit === null ? null : $buildingGlobalIds);

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     * @return array<int, string>
     */
    private function refreshBuildings(array &$summary, ?int $buildingsLimit): array
    {
        $query = Building::query()->orderBy('id');

        if ($buildingsLimit !== null) {
            $query->limit($buildingsLimit);
        }

        $buildingGlobalIds = [];

        if ($query->getQuery()->limit !== null) {
            $records = $this->applyLatestAuditEdits($query->get(), 'building_table');
            $this->insertCacheRows('audited_buildings', $records);
            $summary['buildings_cached'] += $records->count();

            return $records
                ->pluck('globalid')
                ->filter(fn (mixed $globalId): bool => is_string($globalId) && $globalId !== '')
                ->values()
                ->all();
        }

        $query->chunkById(500, function (Collection $records) use (&$summary, &$buildingGlobalIds): void {
            $records = $this->applyLatestAuditEdits($records, 'building_table');
            $this->insertCacheRows('audited_buildings', $records);
            $summary['buildings_cached'] += $records->count();

            $buildingGlobalIds = array_merge(
                $buildingGlobalIds,
                $records
                    ->pluck('globalid')
                    ->filter(fn (mixed $globalId): bool => is_string($globalId) && $globalId !== '')
                    ->values()
                    ->all()
            );
        });

        return $buildingGlobalIds;
    }

    /**
     * @param  array<string, int>  $summary
     * @param  array<int, string>|null  $buildingGlobalIds
     */
    private function refreshHousingUnits(array &$summary, ?array $buildingGlobalIds): void
    {
        $query = HousingUnit::query()
            ->when($buildingGlobalIds !== null, function ($query) use ($buildingGlobalIds): void {
                $query->whereIn('parentglobalid', $buildingGlobalIds);
            })
            ->orderBy('id');

        $query->chunkById(500, function (Collection $records) use (&$summary): void {
            $records = $this->applyLatestAuditEdits($records, 'housing_table');
            $this->insertCacheRows('audited_housing_units', $records);
            $summary['housing_units_cached'] += $records->count();
        });
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    private function insertCacheRows(string $tableName, Collection $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $cacheColumns = Schema::getColumnListing($tableName);

        $rows = $records
            ->map(function (Model $record) use ($cacheColumns): array {
                $attributes = $record->getAttributes();
                $row = [];

                foreach ($cacheColumns as $column) {
                    if ($column === 'is_audited' && ! array_key_exists($column, $attributes)) {
                        $row[$column] = false;

                        continue;
                    }

                    $row[$column] = array_key_exists($column, $attributes)
                        ? $attributes[$column]
                        : null;
                }

                return $row;
            })
            ->filter(fn (array $row): bool => $row !== [])
            ->values()
            ->all();

        $updateColumns = array_values(array_diff($cacheColumns, ['id']));

        foreach (array_chunk($rows, 10) as $chunk) {
            if (in_array('id', $cacheColumns, true)) {
                DB::table($tableName)->upsert($chunk, ['id'], $updateColumns);

                continue;
            }

            DB::table($tableName)->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return Collection<int, Model>
     */
    private function applyLatestAuditEdits(Collection $records, string $type): Collection
    {
        $globalIds = $records
            ->pluck('globalid')
            ->filter(fn (mixed $globalId): bool => is_string($globalId) && $globalId !== '')
            ->unique()
            ->values();

        if ($globalIds->isEmpty()) {
            return $records;
        }

        $editsByGlobalId = [];
        $latestAuditByGlobalId = [];
        $latestStatusByGlobalId = [];

        EditAssessment::query()
            ->where('type', $type)
            ->whereIn('global_id', $globalIds)
            ->orderBy('id')
            ->get(['global_id', 'field_name', 'field_value', 'user_id', 'updated_at'])
            ->each(function (EditAssessment $edit) use (&$editsByGlobalId, &$latestAuditByGlobalId, &$latestStatusByGlobalId): void {
                $globalId = $edit->getAttribute('global_id');
                $fieldName = $edit->getAttribute('field_name');

                if (! is_string($globalId) || $globalId === '' || ! is_string($fieldName) || $fieldName === '') {
                    return;
                }

                $editsByGlobalId[$globalId][$fieldName] = $edit->getAttribute('field_value');
                $latestAuditByGlobalId[$globalId] = $edit;

                if ($fieldName === 'field_status') {
                    $latestStatusByGlobalId[$globalId] = $edit;
                }
            });

        $buildingStatusRows = $type === 'building_table'
            ? $this->assessmentStatusesByName()
            : [];

        $auditedUnitObjectIds = $type === 'housing_table'
            ? $this->auditedUnitObjectIds($records)
            : [];

        return $records->map(function (Model $record) use ($type, $editsByGlobalId, $latestAuditByGlobalId, $latestStatusByGlobalId, $buildingStatusRows, $auditedUnitObjectIds): Model {
            $globalId = $record->getAttribute('globalid');

            if (is_string($globalId) && $globalId !== '') {
                foreach ($editsByGlobalId[$globalId] ?? [] as $fieldName => $fieldValue) {
                    $record->setAttribute($fieldName, $fieldValue);
                }

                $latestAudit = $latestAuditByGlobalId[$globalId] ?? null;

                if ($latestAudit instanceof EditAssessment) {
                    $record->setAttribute('last_audit_user_id', $latestAudit->getAttribute('user_id'));
                    $record->setAttribute('last_audit_at', $latestAudit->getAttribute('updated_at'));
                }

                $latestStatus = $latestStatusByGlobalId[$globalId] ?? null;

                if ($latestStatus instanceof EditAssessment) {
                    $record->setAttribute('last_status_user_id', $latestStatus->getAttribute('user_id'));
                    $record->setAttribute('last_status_at', $latestStatus->getAttribute('updated_at'));
                }
            }

            if ($type === 'building_table') {
                $record->setAttribute('is_audited', isset($latestAuditByGlobalId[(string) $globalId]) ? 1 : 0);
                $this->applyAssessmentStatusAttributes($record, $buildingStatusRows);
            }

            if ($type === 'housing_table') {
                $record->setAttribute('is_audited', isset($auditedUnitObjectIds[(string) $record->getAttribute('objectid')]) ? 1 : 0);
            }

            return $record;
        });
    }

    /**
     * @return array<string, object>
     */
    private function assessmentStatusesByName(): array
    {
        return DB::table('assessment_statuses')
            ->get(['id', 'name', 'label_en', 'label_ar', 'stage', 'order_step'])
            ->mapWithKeys(fn (object $status): array => [strtolower(trim((string) $status->name)) => $status])
            ->all();
    }

    /**
     * @param  Collection<int, Model>  $units
     * @return array<string, bool>
     */
    private function auditedUnitObjectIds(Collection $units): array
    {
        $objectIds = $units
            ->pluck('objectid')
            ->filter(fn (mixed $objectId): bool => $objectId !== null && $objectId !== '')
            ->values();

        if ($objectIds->isEmpty()) {
            return [];
        }

        $auditedStatusIds = DB::table('assessment_statuses')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), [
                'need_review',
                'accepted_by_engineer',
                'rejected_by_engineer',
            ])
            ->pluck('id');

        if ($auditedStatusIds->isEmpty()) {
            return [];
        }

        return DB::table('housing_statuses')
            ->whereIn('housing_id', $objectIds)
            ->whereIn('status_id', $auditedStatusIds)
            ->pluck('housing_id')
            ->mapWithKeys(fn (mixed $objectId): array => [(string) $objectId => true])
            ->all();
    }

    /**
     * @param  array<string, object>  $statusesByName
     */
    private function applyAssessmentStatusAttributes(Model $building, array $statusesByName): void
    {
        $fieldStatus = $building->getAttribute('field_status');

        if (! is_string($fieldStatus) || trim($fieldStatus) === '') {
            return;
        }

        $status = $statusesByName[strtolower(trim($fieldStatus))] ?? null;

        if (! $status instanceof \stdClass) {
            return;
        }

        $building->setAttribute('audit_status_id', $status->id);
        $building->setAttribute('audit_status_name', $status->name);
        $building->setAttribute('audit_status_label_en', $status->label_en);
        $building->setAttribute('audit_status_label_ar', $status->label_ar);
        $building->setAttribute('audit_status_stage', $status->stage);
        $building->setAttribute('audit_status_order_step', $status->order_step);
    }

    private function ensureCacheTablesExist(): void
    {
        if (! Schema::hasTable('audited_buildings') || ! Schema::hasTable('audited_housing_units')) {
            throw new RuntimeException('Audited ArcGIS cache tables are missing. Run php artisan migrate first.');
        }
    }
}
