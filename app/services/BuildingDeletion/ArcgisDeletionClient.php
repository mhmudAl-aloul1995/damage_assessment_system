<?php

namespace App\services\BuildingDeletion;

use App\services\ArcgisService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ArcgisDeletionClient
{
    public function __construct(private readonly ArcgisService $arcgis) {}

    /**
     * @return array{found: bool, feature: array<string, mixed>|null, attachments: array<int, array<string, mixed>>}
     */
    public function findBuilding(string $layerUrl, string $globalId): array
    {
        return $this->firstFeature($layerUrl, "globalid = '".$this->escapeWhere($globalId)."'");
    }

    /**
     * @return array<int, array{feature: array<string, mixed>, attachments: array<int, array<string, mixed>>}>
     */
    public function findHousingUnits(string $layerUrl, string $buildingGlobalId): array
    {
        $features = $this->queryFeatures($layerUrl, "parentglobalid = '".$this->escapeWhere($buildingGlobalId)."'");

        return array_map(fn (array $feature): array => [
            'feature' => $feature,
            'attachments' => $this->attachments($layerUrl, $feature['attributes']['objectid'] ?? null),
        ], $features);
    }

    /**
     * @param  array<int, int|string>  $objectIds
     * @return array<string, mixed>
     */
    public function deleteFeatures(string $layerUrl, array $objectIds): array
    {
        if ((bool) config('services.arcgis.building_deletion_dry_run', false)) {
            return [
                'success' => true,
                'dry_run' => true,
                'message' => 'Dry run enabled; deleteFeatures was not called.',
                'object_ids' => array_values($objectIds),
            ];
        }

        return $this->arcgis->deleteFeaturesFromLayerUrl($layerUrl, $objectIds, $this->arcgis->getToken());
    }

    public function existsByObjectId(string $layerUrl, int|string $objectId): bool
    {
        if (! filled($objectId)) {
            return false;
        }

        return $this->queryFeatures($layerUrl, 'objectid = '.$this->whereValue($objectId)) !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queryFeatures(string $layerUrl, string $where): array
    {
        $response = Http::timeout(60)
            ->withoutVerifying()
            ->get(rtrim($layerUrl, '/').'/query', [
                'f' => 'json',
                'token' => $this->arcgis->getToken(),
                'where' => $where,
                'outFields' => '*',
                'returnGeometry' => 'true',
                'outSR' => 4326,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('ArcGIS query failed: '.$response->body());
        }

        $data = $response->json();

        if (is_array($data['error'] ?? null)) {
            throw new RuntimeException('ArcGIS query error: '.json_encode($data['error'], JSON_UNESCAPED_UNICODE));
        }

        return $data['features'] ?? [];
    }

    /**
     * @return array{found: bool, feature: array<string, mixed>|null, attachments: array<int, array<string, mixed>>}
     */
    private function firstFeature(string $layerUrl, string $where): array
    {
        $feature = $this->queryFeatures($layerUrl, $where)[0] ?? null;

        return [
            'found' => $feature !== null,
            'feature' => $feature,
            'attachments' => $feature === null ? [] : $this->attachments($layerUrl, $feature['attributes']['objectid'] ?? null),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachments(string $layerUrl, mixed $objectId): array
    {
        if (! filled($objectId)) {
            return [];
        }

        return $this->arcgis->getAttachmentsFromLayerUrl($layerUrl, $objectId, $this->arcgis->getToken());
    }

    public function archiveAttachmentFile(string $layerUrl, int|string $objectId, int|string $attachmentId, string $path): ?string
    {
        $response = Http::timeout(60)
            ->withoutVerifying()
            ->get(rtrim($layerUrl, '/').'/'.$objectId.'/attachments/'.$attachmentId, [
                'token' => $this->arcgis->getToken(),
            ]);

        if (! $response->successful()) {
            return null;
        }

        Storage::disk('local')->put($path, $response->body());

        return $path;
    }

    private function escapeWhere(string $value): string
    {
        return str_replace("'", "''", trim($value, '{}'));
    }

    private function whereValue(int|string $value): string
    {
        return is_numeric($value) ? (string) $value : "'".$this->escapeWhere((string) $value)."'";
    }
}
