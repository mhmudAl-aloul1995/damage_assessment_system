<?php

namespace App\services\BuildingDeletion;

use App\Models\Building;
use App\Models\BuildingDeletionRequest;
use App\Models\BuildingDeletionSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuildingDeletionSnapshotService
{
    public function __construct(
        private readonly BuildingDeletionLayerDiscovery $layers,
        private readonly ArcgisDeletionClient $arcgis,
        private readonly BuildingDeletionSnapshotValidator $validator,
    ) {}

    public function create(BuildingDeletionRequest $request, int $createdBy): BuildingDeletionSnapshot
    {
        $building = Building::query()->where('globalid', $request->building_globalid)->first();
        $schema = $this->schema();
        $baseBuilding = $this->record('buildings', 'globalid', $request->building_globalid, $schema['building_columns']);
        $baseUnits = $this->records('housing_units', 'parentglobalid', $request->building_globalid, $schema['housing_unit_columns']);
        $auditedBuilding = $this->recordIfTableExists('audited_buildings', 'globalid', $request->building_globalid, $schema['audited_building_columns']);
        $auditedUnits = $this->recordsIfTableExists('audited_housing_units', 'parentglobalid', $request->building_globalid, $schema['audited_housing_unit_columns']);
        $gis = $this->captureGis($request);
        $related = $this->relatedData($request, $baseUnits);
        $attachments = $this->archiveAttachments($request, $gis);

        $snapshot = [
            'version' => '1.0',
            'module' => 'damage_assessment',
            'process' => 'building_deletion',
            'base' => [
                'building' => [
                    'database' => $baseBuilding,
                    'gis' => $gis['base_buildings'] ?? ['found' => false],
                ],
                'housing_units' => $this->mergeUnitGis($baseUnits, $gis['base_housing_units'] ?? []),
            ],
            'audited' => [
                'building' => [
                    'database' => $auditedBuilding,
                    'gis' => $gis['audited_buildings'] ?? ['found' => false],
                ],
                'housing_units' => $this->mergeUnitGis($auditedUnits, $gis['audited_housing_units'] ?? []),
            ],
            'target' => null,
            'related' => $related,
            'attachments' => $attachments,
            'schema' => $schema,
            'metadata' => $this->metadata($request, $schema, $baseUnits, $auditedUnits, $gis, $related, $attachments),
            'deletion_plan' => $this->layers->deletionPlan(),
        ];

        $this->validator->validate($snapshot);
        $hash = $this->hash($snapshot);
        $snapshot['metadata']['snapshot_hash_algorithm'] = 'sha256';

        return BuildingDeletionSnapshot::query()->create([
            'request_id' => $request->id,
            'building_id' => $building?->id,
            'building_globalid' => $request->building_globalid,
            'building_objectid' => $request->building_objectid,
            'snapshot_version' => '1.0',
            'base_data' => $snapshot['base'],
            'audited_data' => $snapshot['audited'],
            'target_data' => $snapshot['target'],
            'related_data' => $snapshot['related'],
            'attachments_data' => $snapshot['attachments'],
            'metadata' => $snapshot['metadata'],
            'schema_data' => $snapshot['schema'],
            'snapshot_hash' => $hash,
            'created_by' => $createdBy,
            'verified_at' => now(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function schema(): array
    {
        return [
            'building_columns' => Schema::hasTable('buildings') ? Schema::getColumnListing('buildings') : [],
            'housing_unit_columns' => Schema::hasTable('housing_units') ? Schema::getColumnListing('housing_units') : [],
            'audited_building_columns' => Schema::hasTable('audited_buildings') ? Schema::getColumnListing('audited_buildings') : [],
            'audited_housing_unit_columns' => Schema::hasTable('audited_housing_units') ? Schema::getColumnListing('audited_housing_units') : [],
        ];
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, mixed>|null
     */
    private function record(string $table, string $column, mixed $value, array $columns): ?array
    {
        $row = DB::table($table)->where($column, $value)->first();

        return $row === null ? null : $this->normalizeRow($row, $columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, mixed>|null
     */
    private function recordIfTableExists(string $table, string $column, mixed $value, array $columns): ?array
    {
        return Schema::hasTable($table) ? $this->record($table, $column, $value, $columns) : null;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function records(string $table, string $column, mixed $value, array $columns): array
    {
        return DB::table($table)
            ->where($column, $value)
            ->orderBy('objectid')
            ->get()
            ->map(fn (object $row): array => $this->normalizeRow($row, $columns))
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function recordsIfTableExists(string $table, string $column, mixed $value, array $columns): array
    {
        return Schema::hasTable($table) ? $this->records($table, $column, $value, $columns) : [];
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function normalizeRow(object $row, array $columns): array
    {
        return array_replace(array_fill_keys($columns, null), get_object_vars($row));
    }

    /**
     * @return array<string, mixed>
     */
    private function captureGis(BuildingDeletionRequest $request): array
    {
        $captured = [];

        foreach ($this->layers->layers() as $key => $layer) {
            if ($layer['record_type'] === 'building') {
                $captured[$key] = $this->arcgis->findBuilding($layer['url'], $request->building_globalid);
            } else {
                $captured[$key] = $this->arcgis->findHousingUnits($layer['url'], $request->building_globalid);
            }
        }

        return $captured;
    }

    /**
     * @param  array<int, array<string, mixed>>  $databaseUnits
     * @param  array<int, array<string, mixed>>  $gisUnits
     * @return array<int, array<string, mixed>>
     */
    private function mergeUnitGis(array $databaseUnits, array $gisUnits): array
    {
        $gisByGlobalId = [];

        foreach ($gisUnits as $gisUnit) {
            $globalId = data_get($gisUnit, 'feature.attributes.globalid');

            if ($globalId !== null) {
                $gisByGlobalId[(string) $globalId] = $gisUnit;
            }
        }

        $merged = array_map(fn (array $unit): array => [
            'database' => $unit,
            'gis' => $gisByGlobalId[(string) ($unit['globalid'] ?? '')] ?? ['found' => false],
        ], $databaseUnits);

        $gisOnly = array_values(array_filter(array_map(function (array $gisUnit) use ($databaseUnits): ?array {
            $globalId = data_get($gisUnit, 'feature.attributes.globalid');

            foreach ($databaseUnits as $unit) {
                if ((string) ($unit['globalid'] ?? '') === (string) $globalId) {
                    return null;
                }
            }

            return [
                'database' => null,
                'gis' => $gisUnit,
            ];
        }, $gisUnits)));

        return array_merge($merged, $gisOnly);
    }

    /**
     * @param  array<int, array<string, mixed>>  $baseUnits
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function relatedData(BuildingDeletionRequest $request, array $baseUnits): array
    {
        $buildingObjectIds = array_values(array_unique(array_filter([$request->building_objectid], fn (mixed $value): bool => $value !== null && $value !== '')));
        $unitObjectIds = array_values(array_unique(array_filter(array_column($baseUnits, 'objectid'), fn (mixed $value): bool => $value !== null && $value !== '')));
        $unitGlobalIds = array_values(array_unique(array_filter(array_column($baseUnits, 'globalid'), fn (mixed $value): bool => $value !== null && $value !== '')));

        return [
            'building_statuses' => $this->tableRowsWhereIn('building_statuses', 'building_id', $buildingObjectIds),
            'building_status_histories' => $this->tableRowsWhereIn('building_status_histories', 'building_id', $buildingObjectIds),
            'housing_statuses' => $this->tableRowsWhereIn('housing_statuses', 'housing_id', $unitObjectIds),
            'housing_status_histories' => $this->tableRowsWhereIn('housing_status_histories', 'housing_id', $unitObjectIds),
            'assigned_assessment_users' => $this->tableRowsWhereIn('assigned_assessment_users', 'building_id', $buildingObjectIds),
            'building_edit_assessments' => $this->tableRowsWhere('edit_assessments', [
                'type' => 'building_table',
                'global_id' => $request->building_globalid,
            ]),
            'housing_edit_assessments' => $this->tableRowsWhereIn('edit_assessments', 'global_id', $unitGlobalIds, ['type' => 'housing_table']),
        ];
    }

    /**
     * @param  array<string, mixed>  $where
     * @return array<int, array<string, mixed>>
     */
    private function tableRowsWhere(string $table, array $where): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table);

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        $columns = Schema::getColumnListing($table);

        return $query->get()->map(fn (object $row): array => $this->normalizeRow($row, $columns))->all();
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<string, mixed>  $extraWhere
     * @return array<int, array<string, mixed>>
     */
    private function tableRowsWhereIn(string $table, string $column, array $values, array $extraWhere = []): array
    {
        if (! Schema::hasTable($table) || $values === []) {
            return [];
        }

        $query = DB::table($table)->whereIn($column, $values);

        foreach ($extraWhere as $whereColumn => $whereValue) {
            $query->where($whereColumn, $whereValue);
        }

        $columns = Schema::getColumnListing($table);

        return $query->get()->map(fn (object $row): array => $this->normalizeRow($row, $columns))->all();
    }

    /**
     * @param  array<string, mixed>  $gis
     * @return array<string, mixed>
     */
    private function archiveAttachments(BuildingDeletionRequest $request, array $gis): array
    {
        $records = [];
        $layers = $this->layers->layers();
        $root = 'damage-assessment/building-deletions/'.$request->id.'/attachments';

        foreach ($gis as $source => $payload) {
            $layerUrl = $layers[$source]['url'] ?? null;
            $records[$source] = $this->archiveAttachmentPayload($root, $source, $layerUrl, $payload);
        }

        return [
            'storage_root' => $root,
            'records' => $records,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function archiveAttachmentPayload(string $root, string $source, ?string $layerUrl, array $payload): array
    {
        if ($layerUrl === null) {
            return $payload;
        }

        if (array_key_exists('feature', $payload)) {
            return $this->archiveFeatureAttachments($root, $source, $layerUrl, $payload);
        }

        foreach ($payload as $index => $item) {
            if (is_array($item)) {
                $payload[$index] = $this->archiveFeatureAttachments($root, $source, $layerUrl, $item);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function archiveFeatureAttachments(string $root, string $source, string $layerUrl, array $item): array
    {
        $objectId = data_get($item, 'feature.attributes.objectid');

        foreach (($item['attachments'] ?? []) as $index => $attachment) {
            $attachmentId = $attachment['id'] ?? null;

            if (! filled($objectId) || ! filled($attachmentId)) {
                continue;
            }

            $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($attachment['name'] ?? 'attachment-'.$attachmentId)) ?: 'attachment-'.$attachmentId;
            $path = $root.'/'.$source.'/'.$objectId.'/'.$attachmentId.'-'.$fileName;
            $archivedPath = $this->arcgis->archiveAttachmentFile($layerUrl, $objectId, $attachmentId, $path);
            $item['attachments'][$index]['archived_path'] = $archivedPath;
        }

        return $item;
    }

    /**
     * @param  array<string, array<int, string>>  $schema
     * @param  array<int, array<string, mixed>>  $baseUnits
     * @param  array<int, array<string, mixed>>  $auditedUnits
     * @param  array<string, mixed>  $gis
     * @param  array<string, mixed>  $related
     * @param  array<string, mixed>  $attachments
     * @return array<string, mixed>
     */
    private function metadata(BuildingDeletionRequest $request, array $schema, array $baseUnits, array $auditedUnits, array $gis, array $related, array $attachments): array
    {
        return [
            'snapshot_version' => '1.0',
            'module' => 'damage_assessment',
            'process' => 'building_deletion',
            'created_at' => now()->toIso8601String(),
            'building_globalid' => $request->building_globalid,
            'building_objectid' => $request->building_objectid,
            'building_table' => 'buildings',
            'housing_table' => 'housing_units',
            'building_columns_count' => count($schema['building_columns']),
            'housing_columns_count' => count($schema['housing_unit_columns']),
            'housing_units_count_db' => count($baseUnits),
            'audited_housing_units_count_db' => count($auditedUnits),
            'gis_layers_checked' => array_keys($gis),
            'gis_layers_found' => array_keys(array_filter($gis, fn (mixed $value): bool => $value !== [])),
            'attachments_count' => count($attachments['records'] ?? []),
            'related_records_count' => array_sum(array_map('count', $related)),
            'app_version' => config('app.version'),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }
}
