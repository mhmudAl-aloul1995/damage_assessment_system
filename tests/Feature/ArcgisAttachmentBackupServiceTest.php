<?php

use App\Models\ArcgisAttachmentBackup;
use App\Models\Building;
use App\Models\HousingUnit;
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

it('backs up an arcgis housing unit attachment before destructive changes', function (): void {
    Storage::fake('local');

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 421,
        'globalid' => 'housing-unit-global-id',
        'parentglobalid' => 'building-global-id',
        'unit_owner' => 'Unit Owner',
    ]);

    Http::fake([
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/421/attachments' => Http::response([
            'attachmentInfos' => [
                [
                    'id' => 765,
                    'name' => 'unit damage photo.jpg',
                    'contentType' => 'image/jpeg',
                    'size' => 18,
                ],
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/421/attachments/765*' => Http::response('unit-backup-binary'),
    ]);

    $backup = app(ArcgisAttachmentBackupService::class)
        ->backupHousingUnitAttachment($housingUnit, 765, 'replace', 'arcgis-token');

    expect($backup)->toBeInstanceOf(ArcgisAttachmentBackup::class)
        ->and($backup->operation)->toBe('replace')
        ->and($backup->auditable_type)->toBe('housing_unit')
        ->and($backup->building_globalid)->toBe('building-global-id')
        ->and($backup->housing_unit_globalid)->toBe('housing-unit-global-id')
        ->and($backup->housing_unit_objectid)->toBe(421)
        ->and($backup->attachment_id)->toBe(765)
        ->and($backup->attachment_name)->toBe('unit damage photo.jpg')
        ->and($backup->content_type)->toBe('image/jpeg')
        ->and($backup->size)->toBe(18);

    Storage::disk('local')->assertExists($backup->path);
    expect(Storage::disk('local')->get($backup->path))->toBe('unit-backup-binary');
});
