<?php

use App\Models\ArcgisAttachmentBackup;
use App\Models\Building;
use App\services\ArcgisAttachmentBackupService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('backs up an arcgis building attachment before destructive changes', function (): void {
    Storage::fake('local');

    $building = Building::query()->create([
        'objectid' => 321,
        'globalid' => 'building-global-id',
        'building_name' => 'Backup Test Building',
    ]);

    Http::fake([
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/321/attachments' => Http::response([
            'attachmentInfos' => [
                [
                    'id' => 654,
                    'name' => 'damage photo.jpg',
                    'contentType' => 'image/jpeg',
                    'size' => 12,
                ],
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/321/attachments/654*' => Http::response('backup-binary'),
    ]);

    $backup = app(ArcgisAttachmentBackupService::class)
        ->backupBuildingAttachment($building, 654, 'delete', 'arcgis-token');

    expect($backup)->toBeInstanceOf(ArcgisAttachmentBackup::class)
        ->and($backup->operation)->toBe('delete')
        ->and($backup->building_globalid)->toBe('building-global-id')
        ->and($backup->building_objectid)->toBe(321)
        ->and($backup->attachment_id)->toBe(654)
        ->and($backup->attachment_name)->toBe('damage photo.jpg')
        ->and($backup->content_type)->toBe('image/jpeg')
        ->and($backup->size)->toBe(12);

    Storage::disk('local')->assertExists($backup->path);
    expect(Storage::disk('local')->get($backup->path))->toBe('backup-binary');
});
