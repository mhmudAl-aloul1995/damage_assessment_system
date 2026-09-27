<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class MarkPhaseExceptionsNotCompleted extends Command
{
    protected $signature = 'arcgis:mark-phase-exceptions-not-completed
        {file? : XLSX file containing a Building ID column.}
        {--ids= : Building OBJECTIDs separated by commas, spaces, or new lines.}
        {--status=Not_Completed : Status value to write.}
        {--archived-by= : User id recorded as the creator of the archive snapshots.}
        {--dry-run : Preview matching records without writing to the database or ArcGIS.}';

    protected $description = 'Mark listed phase-exception buildings and their units as Not_Completed on the target ArcGIS service.';

    private string $token = '';

    /**
     * @var array<string, array{object_id_field: string|null, fields: array<string, string>}>
     */
    private array $targetLayerMetadata = [];

    public function handle(): int
    {
        $status = trim((string) $this->option('status'));
        $dryRun = (bool) $this->option('dry-run');

        if ($status === '') {
            $this->error('The --status option cannot be empty.');

            return self::FAILURE;
        }

        try {
            $buildingObjectIds = $this->buildingObjectIds();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($buildingObjectIds === []) {
            $this->error('No building ids were provided.');

            return self::FAILURE;
        }

        $this->info('Buildings provided: '.count($buildingObjectIds));
        $this->line('Target service: '.$this->serviceUrl());
        $this->line('Status: '.$status);

        if ($dryRun) {
            $this->warn('Dry run enabled. No database or ArcGIS records will be changed.');
        } else {
            try {
                $archiveUserId = $this->archiveUserId();
            } catch (\Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->token = $this->generateToken();
        }

        $archiveUserId ??= null;

        $summary = [
            'listed_buildings' => count($buildingObjectIds),
            'local_buildings_found' => 0,
            'local_archive_snapshots' => 0,
            'local_buildings_updated' => 0,
            'local_units_updated' => 0,
            'target_buildings_updated' => 0,
            'target_units_updated' => 0,
            'target_status_fields_missing' => 0,
            'target_features_missing' => 0,
            'errors' => 0,
        ];

        try {
            Building::withoutGlobalScopes()
                ->whereIn('objectid', $buildingObjectIds)
                ->orderBy('objectid')
                ->chunkById(50, function (Collection $buildings) use (&$summary, $status, $dryRun, $archiveUserId): void {
                    foreach ($buildings as $building) {
                        $summary['local_buildings_found']++;

                        try {
                            $this->processBuilding($building, $status, $dryRun, $summary, $archiveUserId);
                        } catch (\Throwable $exception) {
                            $summary['errors']++;
                            $this->error('Building '.$building->objectid.' failed: '.$exception->getMessage());
                        }
                    }
                });
        } catch (\Throwable $exception) {
            $this->error('Could not load local buildings: '.$exception->getMessage());

            return self::FAILURE;
        }

        $missingLocal = $summary['listed_buildings'] - $summary['local_buildings_found'];

        if ($missingLocal > 0) {
            $this->warn("Local buildings not found: {$missingLocal}");
        }

        $this->table(
            ['Metric', 'Value'],
            collect($summary)
                ->map(fn (int $value, string $key): array => [$key, (string) $value])
                ->values()
                ->all(),
        );

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function buildingObjectIds(): array
    {
        $ids = [];
        $directIds = $this->buildingObjectIdsFromOption();
        $file = $this->argument('file');

        if ($directIds !== []) {
            $ids = array_merge($ids, $directIds);
        }

        if (is_string($file) && trim($file) !== '') {
            $ids = array_merge($ids, $this->buildingObjectIdsFromWorkbook($file));
        }

        if ($ids === []) {
            throw new RuntimeException('Pass building ids with --ids=829,1116 or provide an XLSX file.');
        }

        return collect($ids)->unique()->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function buildingObjectIdsFromOption(): array
    {
        $option = $this->option('ids');

        if ($option === null || trim((string) $option) === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', trim((string) $option)) ?: [];
        $ids = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (! ctype_digit($part)) {
                throw new RuntimeException("Invalid building id in --ids option: {$part}");
            }

            $ids[] = (int) $part;
        }

        return $ids;
    }

    /**
     * @return array<int, int>
     */
    private function buildingObjectIdsFromWorkbook(string $file): array
    {
        if (! is_file($file)) {
            throw new RuntimeException('Workbook file not found: '.$file);
        }

        $worksheet = IOFactory::load($file)->getActiveSheet();
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        $headers = $worksheet->rangeToArray("A1:{$highestColumn}1", null, true, true, true)[1] ?? [];
        $buildingIdColumn = $this->buildingIdColumn($headers);

        if ($buildingIdColumn === null) {
            throw new RuntimeException('The workbook must contain a Building ID column.');
        }

        $ids = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $value = $worksheet->getCell($buildingIdColumn.$row)->getCalculatedValue();

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            if (! is_numeric($value)) {
                throw new RuntimeException("Invalid Building ID at row {$row}: {$value}");
            }

            $ids[] = (int) $value;
        }

        return collect($ids)->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function buildingIdColumn(array $headers): ?string
    {
        foreach ($headers as $column => $header) {
            $normalized = strtolower(trim((string) $header));
            $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized) ?: '';

            if (in_array($normalized, ['buildingid', 'buildingobjectid', 'objectid'], true)) {
                return (string) $column;
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function processBuilding(Building $building, string $status, bool $dryRun, array &$summary, ?int $archiveUserId): void
    {
        $this->line('Processing building OBJECTID: '.$building->objectid);

        $units = HousingUnit::query()
            ->where('parentglobalid', $building->globalid)
            ->orderBy('objectid')
            ->get();

        if ($dryRun) {
            $this->line('Matched local units: '.$units->count());

            return;
        }

        if ($archiveUserId === null) {
            throw new RuntimeException('Could not resolve the archive user id.');
        }

        $summary['local_archive_snapshots'] += $this->archiveLocalSnapshot($building, $units, $archiveUserId, $status);
        $missingStatusFields = 0;

        $targetBuilding = $this->targetFeature(
            $this->targetBuildingsLayer(),
            [
                'old_objectid_B' => $building->objectid,
                'old_global_id_B' => $building->globalid,
                'objectid' => $building->objectid,
            ],
            $dryRun,
        );

        if ($targetBuilding === null) {
            $summary['target_features_missing']++;
            $this->warn('Target building not found for OBJECTID '.$building->objectid);
        } else {
            if (! $this->updateTargetFeature($this->targetBuildingsLayer(), (int) $targetBuilding['object_id'], [
                'field_status' => $status,
            ])) {
                $summary['target_status_fields_missing']++;
                $missingStatusFields++;

                throw new RuntimeException('Target building layer is missing a writable status field.');
            }

            $summary['target_buildings_updated']++;
        }

        foreach ($units as $unit) {
            $targetUnit = $this->targetFeature(
                $this->targetUnitsLayer(),
                [
                    'old_objectid_U' => $unit->objectid,
                    'old_global_id_U' => $unit->globalid,
                    'globalid' => $unit->globalid,
                ],
                $dryRun,
            );

            if ($targetUnit === null) {
                $summary['target_features_missing']++;
                $this->warn('Target unit not found for OBJECTID '.$unit->objectid);

                continue;
            }

            if (! $this->updateTargetFeature($this->targetUnitsLayer(), (int) $targetUnit['object_id'], [
                'field_status' => $status,
                'building_field_status' => $status,
            ])) {
                $summary['target_status_fields_missing']++;
                $missingStatusFields++;

                continue;
            }

            $summary['target_units_updated']++;
        }

        if ($missingStatusFields > 0) {
            throw new RuntimeException('One or more target features were not updated because their layer is missing status fields.');
        }

        $building->forceFill(['field_status' => $status])->save();
        $summary['local_buildings_updated']++;

        $unitUpdates = [];

        if (Schema::hasColumn('housing_units', 'field_status')) {
            $unitUpdates['field_status'] = $status;
        }

        if (Schema::hasColumn('housing_units', 'building_field_status')) {
            $unitUpdates['building_field_status'] = $status;
        }

        if ($unitUpdates !== []) {
            $summary['local_units_updated'] += HousingUnit::query()
                ->where('parentglobalid', $building->globalid)
                ->update($unitUpdates);
        }
    }

    /**
     * @param  Collection<int, HousingUnit>  $units
     */
    private function archiveLocalSnapshot(Building $building, Collection $units, int $archiveUserId, string $status): int
    {
        $archivedAt = now();
        $notes = "Snapshot before marking phase exception as {$status}.";
        $common = [
            'building_objectid' => (int) $building->objectid,
            'building_globalid' => $building->globalid,
            'source_type' => 'phase_exception_not_completed',
            'archived_by' => $archiveUserId,
            'archived_at' => $archivedAt,
            'notes' => $notes,
            'building_snapshot' => $building->getAttributes(),
        ];

        $created = $this->createArchiveSnapshotIfMissing($common, null);

        foreach ($units as $unit) {
            $created += $this->createArchiveSnapshotIfMissing($common + [
                'housing_unit_objectid' => $unit->objectid,
                'housing_unit_globalid' => $unit->globalid,
                'housing_unit_snapshot' => $unit->getAttributes(),
            ], $unit->objectid);
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createArchiveSnapshotIfMissing(array $attributes, int|string|null $housingUnitObjectId): int
    {
        $query = BuildingSurveyArchiveObject::query()
            ->where('source_type', 'phase_exception_not_completed')
            ->where('building_objectid', $attributes['building_objectid']);

        if ($housingUnitObjectId === null) {
            $query->whereNull('housing_unit_objectid');
        } else {
            $query->where('housing_unit_objectid', $housingUnitObjectId);
        }

        if ($query->exists()) {
            return 0;
        }

        BuildingSurveyArchiveObject::query()->create($attributes);

        return 1;
    }

    private function archiveUserId(): int
    {
        $option = $this->option('archived-by');

        if ($option !== null && $option !== '') {
            if (! is_numeric($option)) {
                throw new RuntimeException('The --archived-by option must be a numeric user id.');
            }

            $userId = (int) $option;

            if (! User::query()->whereKey($userId)->exists()) {
                throw new RuntimeException("Archive user {$userId} was not found.");
            }

            return $userId;
        }

        $userId = User::query()->orderBy('id')->value('id');

        if (! is_numeric($userId)) {
            throw new RuntimeException('Could not resolve an archive user. Pass --archived-by=USER_ID.');
        }

        return (int) $userId;
    }

    /**
     * @param  array<string, mixed>  $matchCandidates
     * @return array{object_id: int, object_id_field: string}|null
     */
    private function targetFeature(int|string $layerId, array $matchCandidates, bool $dryRun): ?array
    {
        if ($dryRun) {
            return null;
        }

        $metadata = $this->targetLayerMetadata($layerId);
        $objectIdField = $metadata['object_id_field'];

        if ($objectIdField === null) {
            throw new RuntimeException("Target layer {$layerId} is missing an object id field.");
        }

        foreach ($matchCandidates as $field => $value) {
            if ($value === null || $value === '' || ! array_key_exists(strtolower($field), $metadata['fields'])) {
                continue;
            }

            $targetField = $metadata['fields'][strtolower($field)];
            $response = $this->http()->get($this->targetLayerUrl($layerId).'/query', [
                'f' => 'json',
                'token' => $this->token,
                'where' => $targetField.' = '.$this->whereValue($value),
                'outFields' => $objectIdField,
                'returnGeometry' => 'false',
                'resultRecordCount' => 1,
            ]);

            $this->throwIfArcgisError($response->json(), 'ArcGIS target lookup failed');

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS target lookup failed: '.$response->body());
            }

            $attributes = $response->json('features.0.attributes');
            $objectId = is_array($attributes) ? ($attributes[$objectIdField] ?? null) : null;

            if (is_numeric($objectId)) {
                return [
                    'object_id' => (int) $objectId,
                    'object_id_field' => $objectIdField,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateTargetFeature(int|string $layerId, int $objectId, array $attributes): bool
    {
        $metadata = $this->targetLayerMetadata($layerId);
        $objectIdField = $metadata['object_id_field'];

        if ($objectIdField === null) {
            throw new RuntimeException("Target layer {$layerId} is missing an object id field.");
        }

        $payloadAttributes = [
            $objectIdField => $objectId,
        ];

        foreach ($attributes as $field => $value) {
            $targetField = $metadata['fields'][strtolower($field)] ?? null;

            if ($targetField !== null) {
                $payloadAttributes[$targetField] = $value;
            }
        }

        if (count($payloadAttributes) === 1) {
            $this->warn("No status fields exist on target layer {$layerId}; skipping OBJECTID {$objectId}.");

            return false;
        }

        $response = $this->http()
            ->asForm()
            ->post($this->targetLayerUrl($layerId).'/updateFeatures', [
                'f' => 'json',
                'token' => $this->token,
                'features' => json_encode([
                    [
                        'attributes' => $payloadAttributes,
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $this->throwIfArcgisError($response->json(), 'ArcGIS updateFeatures failed');

        if (! $response->successful() || ! (bool) $response->json('updateResults.0.success')) {
            throw new RuntimeException('ArcGIS updateFeatures failed: '.$response->body());
        }

        return true;
    }

    /**
     * @return array{object_id_field: string|null, fields: array<string, string>}
     */
    private function targetLayerMetadata(int|string $layerId): array
    {
        $cacheKey = (string) $layerId;

        if (array_key_exists($cacheKey, $this->targetLayerMetadata)) {
            return $this->targetLayerMetadata[$cacheKey];
        }

        $response = $this->http()->get($this->targetLayerUrl($layerId), [
            'f' => 'json',
            'token' => $this->token,
        ]);

        $this->throwIfArcgisError($response->json(), 'ArcGIS target metadata failed');

        if (! $response->successful()) {
            throw new RuntimeException('ArcGIS target metadata failed: '.$response->body());
        }

        $fields = collect($response->json('fields') ?? [])
            ->pluck('name')
            ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
            ->mapWithKeys(fn (string $field): array => [strtolower($field) => $field])
            ->all();

        $objectIdField = $response->json('objectIdField');

        return $this->targetLayerMetadata[$cacheKey] = [
            'object_id_field' => is_string($objectIdField) && $objectIdField !== '' ? $objectIdField : null,
            'fields' => $fields,
        ];
    }

    private function generateToken(): string
    {
        $response = $this->http()
            ->asForm()
            ->post('https://www.arcgis.com/sharing/rest/generateToken', [
                'username' => $this->requiredConfig('username'),
                'password' => $this->requiredConfig('password'),
                'client' => 'referer',
                'referer' => $this->requiredConfig('referer'),
                'expiration' => 60,
                'f' => 'json',
            ]);

        $data = $response->json();
        $this->throwIfArcgisError($data, 'ArcGIS token failed');

        if (! $response->successful() || ! is_string($data['token'] ?? null)) {
            throw new RuntimeException('ArcGIS token failed: '.$response->body());
        }

        return $data['token'];
    }

    private function targetLayerUrl(int|string $layerId): string
    {
        return $this->serviceUrl().'/'.$layerId;
    }

    private function serviceUrl(): string
    {
        $value = config('services.arcgis.target_service');

        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Missing ArcGIS config services.arcgis.target_service.');
        }

        return rtrim($value, '/');
    }

    private function targetBuildingsLayer(): int|string
    {
        return $this->requiredLayerConfig('target_buildings_layer');
    }

    private function targetUnitsLayer(): int|string
    {
        return $this->requiredLayerConfig('target_units_layer');
    }

    private function requiredLayerConfig(string $key): int|string
    {
        $value = config('services.arcgis.'.$key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Missing ArcGIS config services.arcgis.{$key}.");
        }

        return $value;
    }

    private function requiredConfig(string $key): string
    {
        $value = config('services.arcgis.'.$key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing ArcGIS config services.arcgis.{$key}.");
        }

        return $value;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['Referer' => $this->requiredConfig('referer')])
            ->timeout(120)
            ->connectTimeout(30)
            ->retry(2, 1000, throw: false)
            ->withoutVerifying();
    }

    private function whereValue(mixed $value): string
    {
        if (is_numeric($value)) {
            return (string) $value;
        }

        return "'".str_replace("'", "''", (string) $value)."'";
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function throwIfArcgisError(?array $data, string $message): void
    {
        if (! is_array($data) || ! is_array($data['error'] ?? null)) {
            return;
        }

        throw new RuntimeException($message.': '.json_encode($data['error'], JSON_UNESCAPED_UNICODE));
    }
}
