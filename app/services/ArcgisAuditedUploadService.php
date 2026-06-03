<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VBuildingAudited;
use App\Models\VHousingUnitAudited;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ArcgisAuditedUploadService
{
    private string $token = '';

    /**
     * @var array<string, array{object_id_field: string|null, fields: array<string, string>}>
     */
    private array $targetLayerMetadata = [];

    /**
     * @var array<string, string>
     */
    private array $targetBuildingGlobalIdsBySourceGlobalId = [];

    public function upload(?int $buildingsLimit = null): array
    {
        $summary = $this->emptySummary();

        echo "Generating token...\n";
        $this->refreshToken();
        echo "Token generated successfully.\n";

        echo "Uploading buildings...\n";

        $buildingQuery = VBuildingAudited::query()->orderBy('objectid');

        if ($buildingsLimit !== null) {
            $buildingQuery->limit($buildingsLimit);
            $summary['buildings_limit'] = $buildingsLimit;
        }

        $buildingGlobalIds = [];

        foreach ($buildingQuery->cursor() as $building) {
            try {
                echo 'Building OBJECTID: '.$building->getAttribute('objectid')."\n";

                $this->uploadBuilding($building, $summary);
                $buildingGlobalId = $building->getAttribute('globalid');

                if (is_string($buildingGlobalId) && $buildingGlobalId !== '') {
                    $buildingGlobalIds[] = $buildingGlobalId;
                }

                echo "Building uploaded/copied successfully.\n";
            } catch (Throwable $exception) {
                $summary['errors']++;

                echo 'Failed building OBJECTID: '.$building->getAttribute('objectid')."\n";
                echo $exception->getMessage()."\n";

                Log::error('Failed uploading audited building to ArcGIS.', [
                    'objectid' => $building->getAttribute('objectid'),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        echo "Uploading housing units...\n";

        $unitQuery = VHousingUnitAudited::query()
            ->when($buildingsLimit !== null, function (Builder $query) use ($buildingGlobalIds): void {
                $query->whereIn('parentglobalid', array_values(array_unique($buildingGlobalIds)));
            })
            ->orderBy('objectid');

        foreach ($unitQuery->cursor() as $unit) {
            try {
                echo 'Unit OBJECTID: '.$unit->getAttribute('objectid')."\n";

                $this->uploadUnit($unit, $summary);

                echo "Unit uploaded/copied successfully.\n";
            } catch (Throwable $exception) {
                $summary['errors']++;

                echo 'Failed unit OBJECTID: '.$unit->getAttribute('objectid')."\n";
                echo $exception->getMessage()."\n";

                Log::error('Failed uploading audited housing unit to ArcGIS.', [
                    'objectid' => $unit->getAttribute('objectid'),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    public function generateToken(): string
    {
        $response = $this->http()->post('https://www.arcgis.com/sharing/rest/generateToken', [
            'username' => $this->requiredConfig('username'),
            'password' => $this->requiredConfig('password'),
            'client' => 'referer',
            'referer' => $this->requiredConfig('referer'),
            'expiration' => 60,
            'f' => 'json',
        ]);

        $data = $response->json();

        if (! $response->successful() || ! is_string($data['token'] ?? null)) {
            throw new RuntimeException('ArcGIS token failed: '.$response->body());
        }

        return $data['token'];
    }

    private function refreshToken(): string
    {
        $this->token = $this->generateToken();

        return $this->token;
    }

    public function buildingFeature(VBuildingAudited $building, string $token): array
    {
        $attributes = collect($building->getAttributes())
            ->except(['objectid', 'OBJECTID', 'globalid', 'GlobalID', 'GLOBALID', 'shape', 'created_at', 'updated_at'])
            ->toArray();

        $attributes['old_objectid_B'] = $building->objectid;
        $attributes['old_global_id_B'] = $building->getAttribute('globalid');
        $attributes['is_audited'] = 1;

        $feature = ['attributes' => $attributes];

        $geometry = $this->sourceGeometry(
            $this->layerId('source_buildings_layer'),
            $building->objectid,
            $token
        );

        if ($geometry !== null) {
            $feature['geometry'] = $geometry;
        }

        return $feature;
    }

    public function unitFeature(VHousingUnitAudited $unit, string $token): array
    {
        $attributes = collect($unit->getAttributes())
            ->except(['objectid', 'OBJECTID', 'globalid', 'GlobalID', 'GLOBALID', 'shape', 'created_at', 'updated_at'])
            ->toArray();

        $attributes['old_objectid_U'] = $unit->objectid;
        $attributes['old_global_id_U'] = $unit->getAttribute('globalid');
        $attributes['is_audited'] = 1;

        $parentGlobalId = $unit->getAttribute('parentglobalid');

        if (is_string($parentGlobalId) && $parentGlobalId !== '') {
            $attributes['parentglobalid'] = $this->targetBuildingGlobalId($parentGlobalId, $token);
        }

        $feature = ['attributes' => $attributes];

        $geometry = $this->sourceGeometry(
            $this->layerId('source_units_layer'),
            $unit->objectid,
            $token
        );

        if ($geometry !== null) {
            $feature['geometry'] = $geometry;
        }

        return $feature;
    }

    private function uploadBuilding(VBuildingAudited $building, array &$summary): void
    {
        $targetLayerId = $this->layerId('target_buildings_layer');
        $sourceLayerId = $this->layerId('source_buildings_layer');

        $oldObjectId = $building->getAttribute('objectid');

        echo "Checking target building exists...\n";

        $targetFeature = $this->targetFeatureWithToken($targetLayerId, [
            'old_objectid_B' => $oldObjectId,
            'old_global_id_B' => $building->getAttribute('globalid'),
        ]);
        $targetObjectId = $targetFeature['object_id'] ?? null;

        if ($targetObjectId === null) {
            echo "Adding building feature...\n";

            $targetObjectId = $this->addFeature($targetLayerId, fn (string $token): array => $this->buildingFeature($building, $token));
            $summary['buildings_uploaded']++;
        } else {
            echo "Building already exists. Target OBJECTID: {$targetObjectId}\n";
            echo "Updating building feature...\n";

            $this->updateFeature(
                $targetLayerId,
                $targetObjectId,
                $targetFeature['object_id_field'],
                fn (string $token): array => $this->buildingFeature($building, $token),
            );

            $summary['buildings_updated']++;
        }

        echo "Copying building attachments...\n";

        $attachmentsSummary = $this->copyAttachments(
            $sourceLayerId,
            $targetLayerId,
            $oldObjectId,
            $targetObjectId,
        );

        $summary['attachments_uploaded'] += $attachmentsSummary['uploaded'];
        $summary['errors'] += $attachmentsSummary['errors'];
    }

    private function uploadUnit(VHousingUnitAudited $unit, array &$summary): void
    {
        $targetLayerId = $this->layerId('target_units_layer');
        $sourceLayerId = $this->layerId('source_units_layer');

        $oldObjectId = $unit->getAttribute('objectid');

        echo "Checking target unit exists...\n";

        $targetFeature = $this->targetFeatureWithToken($targetLayerId, [
            'old_objectid_U' => $oldObjectId,
            'old_global_id_U' => $unit->getAttribute('globalid'),
        ]);
        $targetObjectId = $targetFeature['object_id'] ?? null;

        if ($targetObjectId === null) {

            echo "Adding unit feature...\n";

            $targetObjectId = $this->addFeature($targetLayerId, fn (string $token): array => $this->unitFeature($unit, $token));

            $summary['units_uploaded']++;

        } else {

            echo "Unit already exists. Target OBJECTID: {$targetObjectId}\n";
            echo "Updating unit feature...\n";

            $this->updateFeature(
                $targetLayerId,
                $targetObjectId,
                $targetFeature['object_id_field'],
                fn (string $token): array => $this->unitFeature($unit, $token),
            );

            $summary['units_updated']++;
        }

        echo "Copying unit attachments...\n";

        $attachmentsSummary = $this->copyAttachments(
            $sourceLayerId,
            $targetLayerId,
            $oldObjectId,
            $targetObjectId,
        );

        $summary['attachments_uploaded'] += $attachmentsSummary['uploaded'];
        $summary['errors'] += $attachmentsSummary['errors'];
    }

    private function addFeature(int|string $layerId, Closure $featureFactory): int
    {
        return $this->withTokenRetry(function (string $token) use ($layerId, $featureFactory): int {
            $feature = $this->targetFeatureForLayer($layerId, $featureFactory($token), $token);
            $response = $this->http()->post($this->targetLayerUrl($layerId).'/addFeatures', [
                'f' => 'json',
                'token' => $token,
                'features' => json_encode([$feature], JSON_THROW_ON_ERROR),
            ]);

            $data = $response->json();

            $objectId = data_get($data, 'addResults.0.objectId');

            if (! $response->successful() || ! data_get($data, 'addResults.0.success') || ! is_numeric($objectId)) {
                throw new RuntimeException('ArcGIS addFeatures failed: '.$response->body());
            }

            echo "Feature added. Target OBJECTID: {$objectId}\n";

            return (int) $objectId;
        });
    }

    private function updateFeature(
        int|string $layerId,
        int $targetObjectId,
        string $objectIdField,
        Closure $featureFactory,
    ): void {
        $this->withTokenRetry(function (string $token) use ($layerId, $targetObjectId, $objectIdField, $featureFactory): void {
            $feature = $featureFactory($token);
            $feature['attributes'][$objectIdField] = $targetObjectId;
            $feature = $this->targetFeatureForLayer($layerId, $feature, $token);

            $response = $this->http()->post($this->targetLayerUrl($layerId).'/updateFeatures', [
                'f' => 'json',
                'token' => $token,
                'features' => json_encode([$feature], JSON_THROW_ON_ERROR),
            ]);

            $data = $response->json();

            if (! $response->successful() || ! data_get($data, 'updateResults.0.success')) {
                throw new RuntimeException('ArcGIS updateFeatures failed: '.$response->body());
            }

            echo "Feature updated. Target OBJECTID: {$targetObjectId}\n";
        });
    }

    /**
     * @return array{object_id: int, object_id_field: string}|null
     */
    private function targetFeatureWithToken(int|string $layerId, array $matchCandidates): ?array
    {
        return $this->withTokenRetry(function (string $token) use ($layerId, $matchCandidates): ?array {
            $metadata = $this->targetLayerMetadata($layerId, $token);
            $fields = $metadata['fields'];
            $objectIdField = $metadata['object_id_field'];

            if ($objectIdField === null) {
                return null;
            }

            foreach ($matchCandidates as $field => $value) {
                if ($value === null || $value === '' || ! array_key_exists(strtolower((string) $field), $fields)) {
                    continue;
                }

                $targetField = $fields[strtolower((string) $field)];

                $response = $this->http()->get($this->targetLayerUrl($layerId).'/query', [
                    'f' => 'json',
                    'token' => $token,
                    'where' => $targetField.' = '.$this->whereValue($value),
                    'outFields' => $objectIdField,
                    'returnGeometry' => 'false',
                    'resultRecordCount' => 1,
                ]);

                $this->throwIfArcgisError($response, 'ArcGIS target lookup failed');

                if (! $response->successful()) {
                    throw new RuntimeException('ArcGIS target lookup failed: '.$response->body());
                }

                $feature = $response->json('features.0.attributes');

                if (! is_array($feature)) {
                    continue;
                }

                $objectId = $feature[$objectIdField] ?? null;

                if (is_numeric($objectId)) {
                    return [
                        'object_id' => (int) $objectId,
                        'object_id_field' => $objectIdField,
                    ];
                }
            }

            return null;
        });
    }

    private function targetFeatureForLayer(int|string $layerId, array $feature, string $token): array
    {
        $fields = $this->targetLayerMetadata($layerId, $token)['fields'];

        if ($fields === []) {
            return $feature;
        }

        $feature['attributes'] = collect($feature['attributes'] ?? [])
            ->filter(fn (mixed $value, string $field): bool => array_key_exists(strtolower($field), $fields))
            ->mapWithKeys(fn (mixed $value, string $field): array => [$fields[strtolower($field)] => $value])
            ->toArray();

        return $feature;
    }

    private function targetBuildingGlobalId(string $sourceBuildingGlobalId, string $token): string
    {
        if (array_key_exists($sourceBuildingGlobalId, $this->targetBuildingGlobalIdsBySourceGlobalId)) {
            return $this->targetBuildingGlobalIdsBySourceGlobalId[$sourceBuildingGlobalId];
        }

        $targetLayerId = $this->layerId('target_buildings_layer');
        $metadata = $this->targetLayerMetadata($targetLayerId, $token);
        $fields = $metadata['fields'];
        $globalIdField = $fields['globalid'] ?? null;

        if ($globalIdField === null) {
            throw new RuntimeException('Target buildings layer is missing globalid field.');
        }

        foreach (['old_global_id_B', 'globalid'] as $matchField) {
            $targetMatchField = $fields[strtolower($matchField)] ?? null;

            if ($targetMatchField === null) {
                continue;
            }

            $response = $this->http()->get($this->targetLayerUrl($targetLayerId).'/query', [
                'f' => 'json',
                'token' => $token,
                'where' => $targetMatchField.' = '.$this->whereValue($sourceBuildingGlobalId),
                'outFields' => $globalIdField,
                'returnGeometry' => 'false',
                'resultRecordCount' => 1,
            ]);

            $this->throwIfArcgisError($response, 'ArcGIS target building parent lookup failed');

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS target building parent lookup failed: '.$response->body());
            }

            $targetGlobalId = $response->json('features.0.attributes.'.$globalIdField);

            if (is_string($targetGlobalId) && $targetGlobalId !== '') {
                return $this->targetBuildingGlobalIdsBySourceGlobalId[$sourceBuildingGlobalId] = $targetGlobalId;
            }
        }

        throw new RuntimeException("Target building parent not found for source globalid {$sourceBuildingGlobalId}.");
    }

    /**
     * @return array{object_id_field: string|null, fields: array<string, string>}
     */
    private function targetLayerMetadata(int|string $layerId, string $token): array
    {
        $cacheKey = (string) $layerId;

        if (array_key_exists($cacheKey, $this->targetLayerMetadata)) {
            return $this->targetLayerMetadata[$cacheKey];
        }

        $response = $this->http()->get($this->targetLayerUrl($layerId), [
            'f' => 'json',
            'token' => $token,
        ]);

        $this->throwIfArcgisError($response, 'ArcGIS target layer metadata failed');

        if (! $response->successful()) {
            throw new RuntimeException('ArcGIS target layer metadata failed: '.$response->body());
        }

        $fields = collect($response->json('fields') ?? [])
            ->pluck('name')
            ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
            ->mapWithKeys(fn (string $field): array => [strtolower($field) => $field])
            ->toArray();

        $objectIdField = $response->json('objectIdField');

        return $this->targetLayerMetadata[$cacheKey] = [
            'object_id_field' => is_string($objectIdField) && $objectIdField !== '' ? $objectIdField : null,
            'fields' => $fields,
        ];
    }

    private function sourceGeometry(int|string $layerId, int|string $objectId, string $token): ?array
    {
        $response = $this->http()->get($this->sourceLayerUrl($layerId).'/query', [
            'f' => 'json',
            'token' => $token,
            'where' => 'objectid = '.$this->whereValue($objectId),
            'outFields' => 'objectid',
            'returnGeometry' => 'true',
            'outSR' => 4326,
            'resultRecordCount' => 1,
        ]);

        $this->throwIfArcgisError($response, 'ArcGIS source geometry lookup failed');

        if (! $response->successful()) {
            throw new RuntimeException('ArcGIS source geometry lookup failed: '.$response->body());
        }

        $geometry = $response->json('features.0.geometry');

        return is_array($geometry) ? $geometry : null;
    }

    private function copyAttachments(
        int|string $sourceLayerId,
        int|string $targetLayerId,
        int|string|null $sourceObjectId,
        int $targetObjectId,
    ): array {
        if ($sourceObjectId === null || $sourceObjectId === '') {
            return ['uploaded' => 0, 'errors' => 0];
        }

        $uploaded = 0;
        $errors = 0;

        $attachments = $this->attachmentInfos($sourceLayerId, $sourceObjectId);

        echo 'Attachments found: '.count($attachments)."\n";

        foreach ($attachments as $attachmentInfo) {
            try {
                $attachmentId = $attachmentInfo['id'] ?? null;
                $name = (string) ($attachmentInfo['name'] ?? 'attachment-'.$attachmentId);

                $size = isset($attachmentInfo['size']) && is_numeric($attachmentInfo['size'])
                    ? (int) $attachmentInfo['size']
                    : null;

                if ($attachmentId === null) {
                    continue;
                }

                if ($this->targetAttachmentExists($targetLayerId, $targetObjectId, $name, $size)) {
                    echo "Attachment already exists: {$name}\n";

                    continue;
                }

                echo "Downloading attachment: {$name}\n";

                $contents = $this->downloadAttachment(
                    $sourceLayerId,
                    $sourceObjectId,
                    $attachmentId,
                );

                echo "Uploading attachment: {$name}\n";

                $this->addAttachment(
                    $targetLayerId,
                    $targetObjectId,
                    $name,
                    $contents,
                );

                $uploaded++;
            } catch (Throwable $exception) {
                $errors++;

                echo 'Failed attachment: '.($attachmentInfo['id'] ?? '-')."\n";
                echo $exception->getMessage()."\n";

                Log::error('Failed copying audited ArcGIS attachment.', [
                    'source_layer_id' => $sourceLayerId,
                    'target_layer_id' => $targetLayerId,
                    'source_objectid' => $sourceObjectId,
                    'target_objectid' => $targetObjectId,
                    'attachment_id' => $attachmentInfo['id'] ?? null,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'uploaded' => $uploaded,
            'errors' => $errors,
        ];
    }

    private function attachmentInfos(int|string $layerId, int|string $objectId): array
    {
        return $this->withTokenRetry(function (string $token) use ($layerId, $objectId): array {
            $response = $this->http()->get($this->sourceLayerUrl($layerId).'/'.$objectId.'/attachments', [
                'f' => 'json',
                'token' => $token,
            ]);

            $this->throwIfArcgisError($response, 'ArcGIS attachmentInfos failed');

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS attachmentInfos failed: '.$response->body());
            }

            $attachmentInfos = $response->json('attachmentInfos') ?? [];

            return is_array($attachmentInfos) ? $attachmentInfos : [];
        });
    }

    private function targetAttachmentExists(
        int|string $layerId,
        int $objectId,
        string $name,
        ?int $size
    ): bool {
        return $this->withTokenRetry(function (string $token) use ($layerId, $objectId, $name, $size): bool {
            $response = $this->http()->get($this->targetLayerUrl($layerId).'/'.$objectId.'/attachments', [
                'f' => 'json',
                'token' => $token,
            ]);

            $this->throwIfArcgisError($response, 'ArcGIS target attachment lookup failed');

            if (! $response->successful()) {
                return false;
            }

            $attachmentInfos = $response->json('attachmentInfos') ?? [];

            if (! is_array($attachmentInfos)) {
                return false;
            }

            foreach ($attachmentInfos as $attachmentInfo) {
                if (($attachmentInfo['name'] ?? null) !== $name) {
                    continue;
                }

                if ($size === null || ! isset($attachmentInfo['size']) || (int) $attachmentInfo['size'] === $size) {
                    return true;
                }
            }

            return false;
        });
    }

    private function downloadAttachment(
        int|string $layerId,
        int|string $objectId,
        int|string $attachmentId
    ): string {
        return $this->withTokenRetry(function (string $token) use ($layerId, $objectId, $attachmentId): string {
            $response = $this->http()->get(
                $this->sourceLayerUrl($layerId).'/'.$objectId.'/attachments/'.$attachmentId,
                [
                    'token' => $token,
                ]
            );

            $this->throwIfArcgisError($response, 'ArcGIS attachment download failed');

            if (! $response->successful()) {
                throw new RuntimeException('ArcGIS attachment download failed: '.$response->body());
            }

            return $response->body();
        });
    }

    private function addAttachment(
        int|string $layerId,
        int $objectId,
        string $name,
        string $contents
    ): void {
        $this->withTokenRetry(function (string $token) use ($layerId, $objectId, $name, $contents): void {
            $url = $this->targetLayerUrl($layerId).'/'.$objectId.'/addAttachment';

            $response = Http::acceptJson()
                ->withHeaders(['Referer' => $this->requiredConfig('referer')])
                ->timeout(120)
                ->connectTimeout(30)
                ->retry(2, 1000, throw: false)
                ->withoutVerifying()
                ->attach('attachment', $contents, $name)
                ->post($url.'?'.http_build_query([
                    'f' => 'json',
                    'token' => $token,
                ]));

            if (! $response->successful() || ! $response->json('addAttachmentResult.success')) {
                throw new RuntimeException('ArcGIS addAttachment failed: '.$response->body());
            }

            echo "Attachment uploaded successfully: {$name}\n";
        });
    }

    private function whereValue(int|string $value): string
    {
        if (is_numeric($value)) {
            return (string) $value;
        }

        return "'".str_replace("'", "''", $value)."'";
    }

    private function targetLayerUrl(int|string $layerId): string
    {
        return $this->serviceUrl('target_service').'/'.$layerId;
    }

    private function sourceLayerUrl(int|string $layerId): string
    {
        return $this->serviceUrl('source_service').'/'.$layerId;
    }

    private function serviceUrl(string $key): string
    {
        return rtrim($this->requiredConfig($key), '/');
    }

    private function layerId(string $key): int|string
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
        return Http::asForm()
            ->acceptJson()
            ->withHeaders(['Referer' => $this->requiredConfig('referer')])
            ->timeout(120)
            ->connectTimeout(30)
            ->retry(2, 1000, throw: false)
            ->withoutVerifying();
    }

    private function withTokenRetry(Closure $callback): mixed
    {
        if ($this->token === '') {
            $this->refreshToken();
        }

        try {
            return $callback($this->token);
        } catch (RuntimeException $exception) {
            if (! $this->isInvalidTokenException($exception)) {
                throw $exception;
            }

            echo "ArcGIS token expired or invalid. Refreshing token and retrying...\n";
            $this->refreshToken();

            return $callback($this->token);
        }
    }

    private function isInvalidTokenException(RuntimeException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, '"code":498')
            || str_contains($message, '"code": 498')
            || str_contains($message, '"code":499')
            || str_contains($message, '"code": 499')
            || str_contains($message, 'Invalid token')
            || str_contains($message, 'Token Required');
    }

    private function throwIfArcgisError(Response $response, string $message): void
    {
        if ($response->json('error') === null) {
            return;
        }

        throw new RuntimeException($message.': '.$response->body());
    }

    private function emptySummary(): array
    {
        return [
            'buildings_uploaded' => 0,
            'buildings_updated' => 0,
            'units_uploaded' => 0,
            'units_updated' => 0,
            'attachments_uploaded' => 0,
            'errors' => 0,
        ];
    }
}
