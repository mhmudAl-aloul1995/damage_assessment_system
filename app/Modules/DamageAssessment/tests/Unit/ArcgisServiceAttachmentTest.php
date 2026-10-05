<?php

use App\services\ArcgisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('adds and deletes arcgis attachments', function (): void {
    Http::fake([
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/321/addAttachment' => Http::response([
            'addAttachmentResult' => [
                'success' => true,
                'objectId' => 654,
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/321/deleteAttachments' => Http::response([
            'deleteAttachmentResults' => [
                [
                    'success' => true,
                    'objectId' => 654,
                ],
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/deleteFeatures' => Http::response([
            'deleteResults' => [
                [
                    'success' => true,
                    'objectId' => 991,
                ],
                [
                    'success' => true,
                    'objectId' => 992,
                ],
            ],
        ]),
    ]);

    $service = new ArcgisService;
    $file = UploadedFile::fake()->create('damage.jpg', 10, 'image/jpeg');

    $addResult = $service->addAttachment(321, 0, $file, 'arcgis-token');
    $deleteResult = $service->deleteAttachment(321, 0, 654, 'arcgis-token');
    $deleteFeaturesResult = $service->deleteFeatures([991, 992], 1, 'arcgis-token');

    expect($addResult['success'])->toBeTrue()
        ->and($addResult['attachment_id'])->toBe(654)
        ->and($deleteResult['success'])->toBeTrue()
        ->and($deleteFeaturesResult['success'])->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/321/addAttachment');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/321/deleteAttachments');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/deleteFeatures'
        && str_contains($request->body(), 'objectIds=991%2C992'));
});

it('creates and backfills the arcgis phase number field when it is missing', function (): void {
    Http::fake([
        'https://example.com/arcgis/rest/services/Layer/FeatureServer/0?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'globalid', 'type' => 'esriFieldTypeGlobalID'],
            ],
        ]),
        'https://example.com/arcgis/admin/services/Layer.FeatureServer/0/addToDefinition' => Http::response([
            'success' => true,
        ]),
        'https://example.com/arcgis/rest/services/Layer/FeatureServer/0/calculate' => Http::response([
            'success' => true,
        ]),
    ]);

    $result = (new ArcgisService)->ensurePhaseNumberField('https://example.com/arcgis/rest/services/Layer/FeatureServer/0', 'arcgis-token');

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('created');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/arcgis/admin/services/Layer.FeatureServer/0/addToDefinition'
        && str_contains(urldecode($request->body()), '"name":"phase_number"')
        && str_contains(urldecode($request->body()), '"defaultValue":1'));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/arcgis/rest/services/Layer/FeatureServer/0/calculate'
        && str_contains(urldecode($request->body()), 'phase_number IS NULL')
        && str_contains(urldecode($request->body()), '"value":1'));
});
