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
