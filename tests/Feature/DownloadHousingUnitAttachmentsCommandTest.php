<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('downloads matching housing unit ownership and permit attachments from objectids file', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/10/attachments' => Http::response([
            'attachmentInfos' => [
                [
                    'id' => 501,
                    'name' => 'ownership_image.jpg',
                    'contentType' => 'image/jpeg',
                ],
                [
                    'id' => 502,
                    'name' => 'damage_photo.jpg',
                    'contentType' => 'image/jpeg',
                ],
                [
                    'id' => 503,
                    'name' => 'municipality_permit.pdf',
                    'contentType' => 'application/pdf',
                ],
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/11/attachments' => Http::response([
            'attachmentInfos' => [],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/10/attachments/501*' => Http::response('ownership-file'),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/10/attachments/503*' => Http::response('permit-file'),
    ]);

    $inputPath = storage_path('app/testing-unit-objectids.txt');
    $outputPath = storage_path('app/public/exports/testing_unit_attachments');

    File::put($inputPath, "ObjectID رقم الوحدة\n10\n11\n10\n");
    File::deleteDirectory($outputPath);

    try {
        $this->artisan('arcgis:download-housing-unit-attachments', [
            'file' => $inputPath,
            '--output' => 'testing_unit_attachments',
        ])->assertSuccessful();

        expect(File::get($outputPath.'/housing_units/10/10_unit_ownership_501_ownership_image.jpg'))->toBe('ownership-file');
        expect(File::get($outputPath.'/housing_units/10/10_unit_permit_503_municipality_permit.pdf'))->toBe('permit-file');
        expect(File::exists($outputPath.'/attachments-index.csv'))->toBeTrue();
        expect(File::exists($outputPath.'/attachments-index.xlsx'))->toBeTrue();
        expect(File::get($outputPath.'/attachments-index.csv'))->toContain('not_found');
        expect(File::exists($outputPath.'/index.html'))->toBeTrue();
        expect(File::get($outputPath.'/index.html'))->toContain('فتح المرفق');
        expect(File::get($outputPath.'/index.html'))->toContain('/storage/exports/testing_unit_attachments/housing_units/10/10_unit_ownership_501_ownership_image.jpg');

        $spreadsheet = IOFactory::load($outputPath.'/attachments-index.xlsx');
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->getCell('H2')->getValue())->toBe('فتح المرفق');
        expect($sheet->getCell('H2')->getHyperlink()->getUrl())->toContain('/storage/exports/testing_unit_attachments/housing_units/10/10_unit_ownership_501_ownership_image.jpg');

        $spreadsheet->disconnectWorksheets();

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/attachments/502'));
    } finally {
        File::delete($inputPath);
        File::deleteDirectory($outputPath);
    }
});
