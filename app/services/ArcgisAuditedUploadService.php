<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VBuildingAudited;
use App\Models\VHousingUnitAudited;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

class ArcgisAuditedUploadService
{
    public function upload(): array
    {
        $summary = $this->emptySummary();

        echo "Generating token...\n";
        $token = $this->generateToken();
        echo "Token generated successfully.\n";

        echo "Uploading buildings...\n";

        foreach (VBuildingAudited::query()->orderBy('objectid')->cursor() as $building) {
            try {
                echo "Building OBJECTID: " . $building->getAttribute('objectid') . "\n";

                $this->uploadBuilding($building, $token, $summary);

                echo "Building uploaded/copied successfully.\n";
            } catch (Throwable $exception) {
                $summary['errors']++;

                echo "Failed building OBJECTID: " . $building->getAttribute('objectid') . "\n";
                echo $exception->getMessage() . "\n";

                Log::error('Failed uploading audited building to ArcGIS.', [
                    'objectid' => $building->getAttribute('objectid'),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        echo "Uploading housing units...\n";

        foreach (VHousingUnitAudited::query()->orderBy('objectid')->cursor() as $unit) {
            try {
                echo "Unit OBJECTID: " . $unit->getAttribute('objectid') . "\n";

                $this->uploadUnit($unit, $token, $summary);

                echo "Unit uploaded/copied successfully.\n";
            } catch (Throwable $exception) {
                $summary['errors']++;

                echo "Failed unit OBJECTID: " . $unit->getAttribute('objectid') . "\n";
                echo $exception->getMessage() . "\n";

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

        if (!$response->successful() || !is_string($data['token'] ?? null)) {
            throw new RuntimeException('ArcGIS token failed: ' . $response->body());
        }

        return $data['token'];
    }

    public function buildingFeature(VBuildingAudited $building): array
    {
        $attributes = collect($building->getAttributes())
            ->except([
                'objectid',
                'OBJECTID',
                'shape',
                'created_at',
                'updated_at',
            ])
            ->toArray();

        $attributes['old_objectid'] = $building->objectid;
        $attributes['is_audited'] = 1;

        $feature = [
            'attributes' => $attributes,
        ];

        $geometry = $this->geometry($building);

        if ($geometry !== null) {
            $feature['geometry'] = $geometry;
        }

        return $feature;
    }

    public function unitFeature(VHousingUnitAudited $unit): array
    {
        return $this->feature($unit, [
            'old_objectid' => 'objectid',
            'globalid' => 'globalid',
            'parentglobalid' => 'parentglobalid',
            'unit_damage_status' => 'unit_damage_status',
            'is_audited' => fn(): int => 1,
        ]);
    }

    private function uploadBuilding(VBuildingAudited $building, string $token, array &$summary): void
    {
        $targetLayerId = $this->layerId('target_buildings_layer');
        $sourceLayerId = $this->layerId('source_buildings_layer');

        $oldObjectId = $building->getAttribute('objectid');

        echo "Checking target building exists...\n";

        $targetObjectId = $this->targetFeatureExistsWithToken($targetLayerId, $oldObjectId, $token);

        if ($targetObjectId === null) {
            echo "Adding building feature...\n";

            $targetObjectId = $this->addFeature($targetLayerId, $this->buildingFeature($building), $token);
            $summary['buildings_uploaded']++;
        } else {
            echo "Building already exists. Target OBJECTID: {$targetObjectId}\n";
        }

        echo "Copying building attachments...\n";

        $attachmentsSummary = $this->copyAttachments(
            $sourceLayerId,
            $targetLayerId,
            $oldObjectId,
            $targetObjectId,
            $token
        );

        $summary['attachments_uploaded'] += $attachmentsSummary['uploaded'];
        $summary['errors'] += $attachmentsSummary['errors'];
    }

    private function uploadUnit(VHousingUnitAudited $unit, string $token, array &$summary): void
    {
        $targetLayerId = $this->layerId('target_units_layer');
        $sourceLayerId = $this->layerId('source_units_layer');

        $oldObjectId = $unit->getAttribute('objectid');

        echo "Checking target unit exists...\n";

        $targetObjectId = $this->targetFeatureExistsWithToken($targetLayerId, $oldObjectId, $token);

        if ($targetObjectId === null) {
            echo "Adding unit feature...\n";

            $targetObjectId = $this->addFeature($targetLayerId, $this->unitFeature($unit), $token);
            $summary['units_uploaded']++;
        } else {
            echo "Unit already exists. Target OBJECTID: {$targetObjectId}\n";
        }

        echo "Copying unit attachments...\n";

        $attachmentsSummary = $this->copyAttachments(
            $sourceLayerId,
            $targetLayerId,
            $oldObjectId,
            $targetObjectId,
            $token
        );

        $summary['attachments_uploaded'] += $attachmentsSummary['uploaded'];
        $summary['errors'] += $attachmentsSummary['errors'];
    }

    private function addFeature(int|string $layerId, array $feature, string $token): int
    {
        $response = $this->http()->post($this->targetLayerUrl($layerId) . '/addFeatures', [
            'f' => 'json',
            'token' => $token,
            'features' => json_encode([$feature], JSON_THROW_ON_ERROR),
        ]);

        $data = $response->json();

        $objectId = data_get($data, 'addResults.0.objectId');

        if (!$response->successful() || !data_get($data, 'addResults.0.success') || !is_numeric($objectId)) {
            throw new RuntimeException('ArcGIS addFeatures failed: ' . $response->body());
        }

        echo "Feature added. Target OBJECTID: {$objectId}\n";

        return (int) $objectId;
    }

    private function targetFeatureExistsWithToken(int|string $layerId, int|string|null $oldObjectId, string $token): ?int
    {
        if ($oldObjectId === null || $oldObjectId === '') {
            return null;
        }

        $response = $this->http()->get($this->targetLayerUrl($layerId) . '/query', [
            'f' => 'json',
            'token' => $token,
            'where' => 'old_objectid = ' . $this->whereValue($oldObjectId),
            'outFields' => 'objectid,OBJECTID,old_objectid',
            'returnGeometry' => 'false',
            'resultRecordCount' => 1,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('ArcGIS target lookup failed: ' . $response->body());
        }

        $feature = $response->json('features.0.attributes');

        if (!is_array($feature)) {
            return null;
        }

        $objectId = $feature['objectid']
            ?? $feature['OBJECTID']
            ?? $feature['ObjectId']
            ?? null;

        return is_numeric($objectId) ? (int) $objectId : null;
    }

    private function copyAttachments(
        int|string $sourceLayerId,
        int|string $targetLayerId,
        int|string|null $sourceObjectId,
        int $targetObjectId,
        string $token
    ): array {
        if ($sourceObjectId === null || $sourceObjectId === '') {
            return ['uploaded' => 0, 'errors' => 0];
        }

        $uploaded = 0;
        $errors = 0;

        $attachments = $this->attachmentInfos($sourceLayerId, $sourceObjectId, $token);

        echo "Attachments found: " . count($attachments) . "\n";

        foreach ($attachments as $attachmentInfo) {
            try {
                $attachmentId = $attachmentInfo['id'] ?? null;
                $name = (string) ($attachmentInfo['name'] ?? 'attachment-' . $attachmentId);

                $size = isset($attachmentInfo['size']) && is_numeric($attachmentInfo['size'])
                    ? (int) $attachmentInfo['size']
                    : null;

                if ($attachmentId === null) {
                    continue;
                }

                if ($this->targetAttachmentExists($targetLayerId, $targetObjectId, $name, $size, $token)) {
                    echo "Attachment already exists: {$name}\n";
                    continue;
                }

                echo "Downloading attachment: {$name}\n";

                $contents = $this->downloadAttachment(
                    $sourceLayerId,
                    $sourceObjectId,
                    $attachmentId,
                    $token
                );

                echo "Uploading attachment: {$name}\n";

                $this->addAttachment(
                    $targetLayerId,
                    $targetObjectId,
                    $name,
                    $contents,
                    $token
                );

                $uploaded++;
            } catch (Throwable $exception) {
                $errors++;

                echo "Failed attachment: " . ($attachmentInfo['id'] ?? '-') . "\n";
                echo $exception->getMessage() . "\n";

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

    private function attachmentInfos(int|string $layerId, int|string $objectId, string $token): array
    {
        $response = $this->http()->get($this->sourceLayerUrl($layerId) . '/' . $objectId . '/attachments', [
            'f' => 'json',
            'token' => $token,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('ArcGIS attachmentInfos failed: ' . $response->body());
        }

        $attachmentInfos = $response->json('attachmentInfos') ?? [];

        return is_array($attachmentInfos) ? $attachmentInfos : [];
    }

    private function targetAttachmentExists(
        int|string $layerId,
        int $objectId,
        string $name,
        ?int $size,
        string $token
    ): bool {
        $response = $this->http()->get($this->targetLayerUrl($layerId) . '/' . $objectId . '/attachments', [
            'f' => 'json',
            'token' => $token,
        ]);

        if (!$response->successful()) {
            return false;
        }

        $attachmentInfos = $response->json('attachmentInfos') ?? [];

        if (!is_array($attachmentInfos)) {
            return false;
        }

        foreach ($attachmentInfos as $attachmentInfo) {
            if (($attachmentInfo['name'] ?? null) !== $name) {
                continue;
            }

            if ($size === null || !isset($attachmentInfo['size']) || (int) $attachmentInfo['size'] === $size) {
                return true;
            }
        }

        return false;
    }

    private function downloadAttachment(
        int|string $layerId,
        int|string $objectId,
        int|string $attachmentId,
        string $token
    ): string {
        $response = $this->http()->get(
            $this->sourceLayerUrl($layerId) . '/' . $objectId . '/attachments/' . $attachmentId,
            [
                'token' => $token,
            ]
        );

        if (!$response->successful()) {
            throw new RuntimeException('ArcGIS attachment download failed: ' . $response->body());
        }

        return $response->body();
    }

    private function addAttachment(
        int|string $layerId,
        int $objectId,
        string $name,
        string $contents,
        string $token
    ): void {
        $url = $this->targetLayerUrl($layerId) . '/' . $objectId . '/addAttachment';

        $response = Http::acceptJson()
            ->timeout(120)
            ->connectTimeout(30)
            ->retry(2, 1000, throw: false)
            ->withoutVerifying()
            ->attach('attachment', $contents, $name)
            ->post($url . '?' . http_build_query([
                'f' => 'json',
                'token' => $token,
            ]));

        if (!$response->successful() || !$response->json('addAttachmentResult.success')) {
            throw new RuntimeException('ArcGIS addAttachment failed: ' . $response->body());
        }

        echo "Attachment uploaded successfully: {$name}\n";
    }

    private function feature(Model $model, array $fieldMap): array
    {
        $attributes = [];

        foreach ($fieldMap as $targetField => $sourceField) {
            $attributes[$targetField] = $sourceField instanceof Closure
                ? $sourceField()
                : $model->getAttribute($sourceField);
        }

        $feature = [
            'attributes' => $attributes,
        ];

        $geometry = $this->geometry($model);

        if ($geometry !== null) {
            $feature['geometry'] = $geometry;
        }

        return $feature;
    }

    private function geometry(Model $model): ?array
    {
        $x = $model->getAttribute('x');
        $y = $model->getAttribute('y');

        if ($x === null || $y === null || $x === '' || $y === '') {
            $x = $model->getAttribute('longitude');
            $y = $model->getAttribute('latitude');
        }

        if ($x === null || $y === null || $x === '' || $y === '') {
            return null;
        }

        return [
            'x' => $x,
            'y' => $y,
            'spatialReference' => [
                'wkid' => 4326,
            ],
        ];
    }

    private function whereValue(int|string $value): string
    {
        if (is_numeric($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function targetLayerUrl(int|string $layerId): string
    {
        return $this->serviceUrl('target_service') . '/' . $layerId;
    }

    private function sourceLayerUrl(int|string $layerId): string
    {
        return $this->serviceUrl('source_service') . '/' . $layerId;
    }

    private function serviceUrl(string $key): string
    {
        return rtrim($this->requiredConfig($key), '/');
    }

    private function layerId(string $key): int|string
    {
        $value = config('services.arcgis.' . $key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Missing ArcGIS config services.arcgis.{$key}.");
        }

        return $value;
    }

    private function requiredConfig(string $key): string
    {
        $value = config('services.arcgis.' . $key);

        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Missing ArcGIS config services.arcgis.{$key}.");
        }

        return $value;
    }

    private function http(): PendingRequest
    {
        return Http::asForm()
            ->acceptJson()
            ->timeout(120)
            ->connectTimeout(30)
            ->retry(2, 1000, throw: false)
            ->withoutVerifying();
    }

    private function emptySummary(): array
    {
        return [
            'buildings_uploaded' => 0,
            'units_uploaded' => 0,
            'attachments_uploaded' => 0,
            'errors' => 0,
        ];
    }
}