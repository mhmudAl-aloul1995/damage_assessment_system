<?php

namespace App\services;

use App\Models\ArcgisAttachmentBackup;
use App\Models\Building;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ArcgisAttachmentBackupService
{
    public function __construct(private readonly ArcgisService $arcgis) {}

    public function backupBuildingAttachment(Building $building, int $attachmentId, string $operation, string $token): ArcgisAttachmentBackup
    {
        if (! filled($building->objectid)) {
            throw new RuntimeException('This building does not have an ArcGIS object id.');
        }

        $layerId = $this->arcgis->getLayerId(Building::class);
        $attachment = collect($this->arcgis->getAttachments($building->objectid, $layerId, $token))
            ->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $attachmentId);

        if (! is_array($attachment)) {
            throw new RuntimeException('The ArcGIS attachment was not found.');
        }

        $download = $this->arcgis->downloadAttachment($building->objectid, $layerId, $attachmentId, $token);

        if (! ($download['success'] ?? false) || ! is_string($download['body'])) {
            throw new RuntimeException('Unable to download the ArcGIS attachment for backup.');
        }

        $disk = 'local';
        $path = $this->backupPath($building, $attachmentId, (string) ($attachment['name'] ?? 'attachment'));

        Storage::disk($disk)->put($path, $download['body']);

        return ArcgisAttachmentBackup::query()->create([
            'operation' => $operation,
            'auditable_type' => 'building',
            'building_globalid' => $building->globalid,
            'building_objectid' => $building->objectid,
            'attachment_id' => $attachmentId,
            'attachment_name' => $attachment['name'] ?? null,
            'content_type' => $attachment['contentType'] ?? null,
            'size' => $attachment['size'] ?? null,
            'disk' => $disk,
            'path' => $path,
            'user_id' => Auth::id(),
        ]);
    }

    private function backupPath(Building $building, int $attachmentId, string $name): string
    {
        $safeGlobalId = Str::slug((string) $building->globalid) ?: 'building-'.$building->objectid;
        $safeName = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'attachment';
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $fileName = now()->format('Ymd_His').'_attachment_'.$attachmentId.'_'.$safeName;

        if (filled($extension)) {
            $fileName .= '.'.Str::lower($extension);
        }

        return 'arcgis-attachment-backups/buildings/'.$safeGlobalId.'/'.$fileName;
    }
}
