<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('downloads matching housing unit ownership and permit attachments from objectids file', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/10/attachments' => Http::response([
            'attachmentInfos' => [
                [
                    'id' => 500,
                    'name' => 'identity_image.jpg',
                    'contentType' => 'image/jpeg',
                ],
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
            'attachmentInfos' => [
                [
                    'id' => 601,
                    'name' => 'damage_photo.jpg',
                    'contentType' => 'image/jpeg',
                ],
                [
                    'id' => 602,
                    'name' => 'damage_photo_2.jpg',
                    'contentType' => 'image/jpeg',
                ],
                [
                    'id' => 336370,
                    'name' => 'احمد.jpeg',
                    'contentType' => 'image/jpeg',
                ],
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/10/attachments/500*' => Http::response('identity-file'),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/10/attachments/501*' => Http::response('ownership-file'),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/10/attachments/503*' => Http::response('permit-file'),
    ]);

    $inputPath = storage_path('app/testing-unit-objectids.xlsx');
    $outputPath = storage_path('app/public/exports/testing_unit_attachments');
    $zipPath = storage_path('app/public/exports/testing_unit_attachments.zip');

    $inputSpreadsheet = new Spreadsheet;
    $inputSheet = $inputSpreadsheet->getActiveSheet();
    $inputSheet->fromArray([
        ['ObjectID رقم الوحدة'],
        [10],
        [11],
        [10],
    ]);
    (new Xlsx($inputSpreadsheet))->save($inputPath);
    $inputSpreadsheet->disconnectWorksheets();

    File::deleteDirectory($outputPath);
    File::delete($zipPath);

    try {
        $this->artisan('arcgis:download-housing-unit-attachments', [
            'file' => $inputPath,
            '--output' => 'testing_unit_attachments',
            '--types' => 'identity,ownership,permit',
        ])->assertSuccessful();

        expect(File::get($outputPath.'/housing_units/10/10_unit_identity_500_identity_image.jpg'))->toBe('identity-file');
        expect(File::get($outputPath.'/housing_units/10/10_unit_ownership_501_ownership_image.jpg'))->toBe('ownership-file');
        expect(File::get($outputPath.'/housing_units/10/10_unit_permit_503_municipality_permit.pdf'))->toBe('permit-file');
        expect(File::exists($outputPath.'/attachments-index.csv'))->toBeTrue();
        expect(File::exists($outputPath.'/attachments-index.xlsx'))->toBeTrue();
        expect(File::get($outputPath.'/attachments-index.csv'))->toContain('online_only');
        expect(File::exists($outputPath.'/index.html'))->toBeTrue();
        expect(File::get($outputPath.'/index.html'))->toContain('فتح المرفق');
        expect(File::get($outputPath.'/index.html'))->toContain('/storage/exports/testing_unit_attachments/housing_units/10/10_unit_ownership_501_ownership_image.jpg');

        $spreadsheet = IOFactory::load($outputPath.'/attachments-index.xlsx');
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->getHighestColumn())->toBe('C');
        expect($sheet->getCell('A1')->getValue())->toBe('objectid');
        expect($sheet->getCell('B1')->getValue())->toBe('رابط المرفق المحلي');
        expect($sheet->getCell('C1')->getValue())->toBe('روابط مرفقات ArcGIS');
        expect($sheet->getCell('A2')->getValue())->toBe(10);
        expect($sheet->getCell('B2')->getValue())->toBe('فتح المرفق');
        expect($sheet->getCell('B2')->getHyperlink()->getUrl())->toBe('housing_units/10/10_unit_identity_500_identity_image.jpg');
        expect($sheet->getCell('A5')->getValue())->toBe(11);
        expect($sheet->getCell('C5')->getValue())->toBe("مرفق 1\nمرفق 2\nمرفق 3");
        expect($sheet->getCell('C5')->getHyperlink()->getUrl())->toContain('/FeatureServer/1/11/attachments/601?token=arcgis-token');
        expect($sheet->getRowDimension(5)->getRowHeight())->toBeGreaterThan(24);

        $spreadsheet->disconnectWorksheets();

        expect(File::exists($zipPath))->toBeTrue();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        expect($zip->getFromName('attachments-index.xlsx'))->not->toBeFalse();
        expect($zip->getFromName('housing_units/10/10_unit_identity_500_identity_image.jpg'))->toBe('identity-file');
        expect($zip->getFromName('housing_units/10/10_unit_ownership_501_ownership_image.jpg'))->toBe('ownership-file');

        $zip->close();

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/attachments/502'));
    } finally {
        File::delete($inputPath);
        File::deleteDirectory($outputPath);
        File::delete($zipPath);
    }
});
