<?php

use App\Jobs\ExportAttachmentsJob;
use App\Jobs\ExportDataJob;
use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\EditAssessment;
use App\Models\Export;
use App\Models\HousingStatus;
use App\Models\User;
use App\services\ArcgisService;
use App\Support\Exports\ExportDataColumns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use OpenSpout\Reader\XLSX\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

uses(TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('it streams export rows to an xlsx file without internal columns', function () {
    $job = new class(1) extends ExportDataJob
    {
        public function write(string $path, iterable $rows, array $labels): int
        {
            return $this->writeExportFile($path, $rows, $labels, new Export);
        }
    };

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'export-data-streaming-test.xlsx';

    if (is_file($path)) {
        unlink($path);
    }

    try {
        $processed = $job->write($path, [
            [
                'export_row_id' => 10,
                'export_building_globalid' => 'building-1',
                'building_owner_name' => 'Ahmad',
                'building_housing_units_count' => 3,
                'family_members_total' => 7,
            ],
            [
                'export_row_id' => 11,
                'export_building_globalid' => 'building-2',
                'building_owner_name' => 'Mona',
                'building_housing_units_count' => 1,
                'family_members_total' => 4,
            ],
        ], [
            'owner_name' => 'Owner Name',
        ]);

        expect($processed)->toBe(2);
        expect(is_file($path))->toBeTrue();

        $reader = new Reader;
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        expect($rows)->toBe([
            ['Owner Name', 'عدد الوحدات للمبنى', 'عدد أفراد الأسرة'],
            ['Ahmad', 3, 7],
            ['Mona', 1, 4],
        ]);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->getColumnDimension('A')->getWidth())->toBe(14.0);
        expect($sheet->getStyle('A2')->getAlignment()->getWrapText())->toBeFalse();
        $spreadsheet->disconnectWorksheets();
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('it filters building exports by audited building end date range', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 2001,
        'globalid' => 'building-audited-into-range',
        'end' => '2026-04-30 10:00:00',
    ]);

    EditAssessment::query()->create([
        'global_id' => 'building-audited-into-range',
        'type' => 'building_table',
        'field_name' => 'end',
        'field_value' => '2026-05-10 10:00:00',
        'user_id' => $user->id,
    ]);

    Building::query()->create([
        'objectid' => 2002,
        'globalid' => 'building-before-range',
        'end' => '2026-04-29 10:00:00',
    ]);

    Building::query()->create([
        'objectid' => 2003,
        'globalid' => 'building-after-range',
        'end' => '2026-06-01 10:00:00',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'building_columns' => ['objectid', 'end'],
            'building_end_from' => '2026-05-01',
            'building_end_to' => '2026-05-31',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportDataJob($export->id))->handle();

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->processed)->toBe(1);
        expect($export->file_name)->not->toBeNull();

        $reader = new Reader;
        $reader->open(storage_path('app/public/'.$export->file_name));

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        expect($rows[1])->toBe([2001, '2026-05-10 10:00:00']);
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

test('it filters building exports by assessment obstacle', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 3001,
        'globalid' => 'building-with-obstacle',
        'assessment_obstacle' => 'yes',
    ]);

    Building::query()->create([
        'objectid' => 3002,
        'globalid' => 'building-without-obstacle',
        'assessment_obstacle' => 'no',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'building_columns' => ['objectid', 'assessment_obstacle'],
            'filters' => [
                'assessment_obstacle' => ['yes'],
            ],
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportDataJob($export->id))->handle();

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->processed)->toBe(1);
        expect($export->file_name)->not->toBeNull();

        $reader = new Reader;
        $reader->open(storage_path('app/public/'.$export->file_name));

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        expect($rows[1])->toBe([3001, 'yes']);
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

test('it exports requested audit notes and auditor names from data exports', function () {
    $user = User::factory()->create();
    $legalAuditor = User::factory()->create(['name' => 'Legal Auditor Name']);
    $engineeringAuditor = User::factory()->create(['name' => 'Engineering Auditor Name']);

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_for_export_notes',
        'label_en' => 'Accepted',
        'label_ar' => 'Accepted',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    Building::query()->create([
        'objectid' => 3101,
        'globalid' => 'building-export-notes-included',
    ]);

    Building::query()->create([
        'objectid' => 3102,
        'globalid' => 'building-export-notes-excluded',
    ]);

    BuildingStatus::query()->create([
        'building_id' => 3101,
        'status_id' => $status->id,
        'user_id' => $legalAuditor->id,
        'type' => 'Legal Auditor',
        'notes' => 'Legal note for data export',
    ]);

    BuildingStatus::query()->create([
        'building_id' => 3101,
        'status_id' => $status->id,
        'user_id' => $engineeringAuditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'Engineering note for data export',
    ]);

    BuildingStatus::query()->create([
        'building_id' => 3102,
        'status_id' => $status->id,
        'user_id' => $engineeringAuditor->id,
        'type' => 'QC/QA Engineer',
        'notes' => 'Engineering note only',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'building_columns' => ['objectid'],
            'include_legal_notes' => '1',
            'include_engineering_notes' => '1',
            'legal_notes_filter' => 'with_notes',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportDataJob($export->id))->handle();

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->processed)->toBe(1);

        $spreadsheet = IOFactory::load(storage_path('app/public/'.$export->file_name));
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->rangeToArray('A1:E2'))->toBe([
            [
                'Objectid',
                ExportDataColumns::auditNoteColumnLabel(ExportDataColumns::LEGAL_AUDITOR_COLUMN),
                ExportDataColumns::auditNoteColumnLabel(ExportDataColumns::LEGAL_NOTES_COLUMN),
                ExportDataColumns::auditNoteColumnLabel(ExportDataColumns::ENGINEERING_AUDITOR_COLUMN),
                ExportDataColumns::auditNoteColumnLabel(ExportDataColumns::ENGINEERING_NOTES_COLUMN),
            ],
            ['3101', 'Legal Auditor Name', 'Legal note for data export', 'Engineering Auditor Name', 'Engineering note for data export'],
        ]);

        $spreadsheet->disconnectWorksheets();
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

test('it includes audit notes inside data workbook when exporting data with attachments', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/3201/attachments' => Http::response([
            'attachmentInfos' => [],
        ]),
    ]);

    $user = User::factory()->create();
    $legalAuditor = User::factory()->create(['name' => 'Unit Legal Auditor']);

    $status = AssessmentStatus::query()->create([
        'name' => 'legal_notes_for_export_data_zip',
        'label_en' => 'Legal Notes',
        'label_ar' => 'Legal Notes',
        'stage' => 'lawyer',
        'order_step' => 1,
    ]);

    Building::query()->create([
        'objectid' => 3200,
        'globalid' => 'building-for-unit-notes',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 3201,
        'globalid' => 'housing-for-unit-notes',
        'parentglobalid' => 'building-for-unit-notes',
        'housing_unit_number' => 'A-1',
    ]);

    HousingStatus::query()->create([
        'housing_id' => 3201,
        'status_id' => $status->id,
        'user_id' => $legalAuditor->id,
        'type' => 'Legal Auditor',
        'notes' => 'Unit legal note',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'export_mode' => 'data_with_attachments',
            'export_type' => 'zip',
            'housing_columns' => ['objectid'],
            'attachment_sources' => ['housing_unit_arcgis'],
            'attachment_type_filters' => ['identity'],
            'include_attachment_excel_columns' => '1',
            'attachment_excel_display' => 'links',
            'include_legal_notes' => '1',
            'legal_notes_filter' => 'with_notes',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportAttachmentsJob($export->id))->handle(app(ArcgisService::class));

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->file_name)->not->toBeNull();

        $zip = new \ZipArchive;
        $zip->open(storage_path('app/public/'.$export->file_name));

        $dataPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'export-data-with-audit-notes.xlsx';
        file_put_contents($dataPath, $zip->getFromName('data.xlsx'));
        $zip->close();

        $spreadsheet = IOFactory::load($dataPath);
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->rangeToArray('A1:C2'))->toBe([
            [
                'Objectid',
                ExportDataColumns::auditNoteColumnLabel(ExportDataColumns::LEGAL_AUDITOR_COLUMN),
                ExportDataColumns::auditNoteColumnLabel(ExportDataColumns::LEGAL_NOTES_COLUMN),
            ],
            ['3201', 'Unit Legal Auditor', 'Unit legal note'],
        ]);

        $spreadsheet->disconnectWorksheets();
        unlink($dataPath);
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

test('it exports data with selected arcgis building attachments to a zip with an index', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/4001/attachments' => Http::response([
            'attachmentInfos' => [
                [
                    'id' => 901,
                    'name' => 'damage photo.jpg',
                    'contentType' => 'image/jpeg',
                    'size' => 12,
                ],
                [
                    'id' => 902,
                    'name' => 'identity.pdf',
                    'contentType' => 'application/pdf',
                    'size' => 20,
                ],
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/4001/attachments/901*' => Http::response('image-bytes'),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/4001/attachments/902*' => Http::response('pdf-bytes'),
    ]);

    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 4001,
        'globalid' => 'building-with-attachment',
        'owner_name' => 'Attachment Owner',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'export_mode' => 'data_with_attachments',
            'export_type' => 'zip',
            'building_columns' => ['objectid', 'owner_name'],
            'attachment_sources' => ['building_arcgis'],
            'attachment_type_filters' => ['damage_photos'],
            'include_attachment_excel_columns' => '1',
            'attachment_excel_display' => 'links',
            'attachment_grouping' => 'by_building',
            'attachment_filename_strategy' => 'objectid_type',
            'include_attachment_index' => '1',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportAttachmentsJob($export->id))->handle(app(ArcgisService::class));

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->processed)->toBe(1);
        expect($export->file_name)->not->toBeNull();

        $zip = new \ZipArchive;
        $zip->open(storage_path('app/public/'.$export->file_name));

        expect($zip->locateName('data.xlsx'))->not->toBeFalse();
        expect($zip->getFromName('buildings/4001/4001_building_901_damage photo.jpg'))->toBe('image-bytes');
        expect($zip->getFromName('buildings/4001/4001_building_902_identity.pdf'))->toBeFalse();
        expect($zip->getFromName('attachments-index.csv'))->toContain('building-with-attachment');

        $dataPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'export-data-with-attachment-links.xlsx';
        file_put_contents($dataPath, $zip->getFromName('data.xlsx'));

        $zip->close();

        $spreadsheet = IOFactory::load($dataPath);
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->getCell('C2')->getValue())->toBe('فتح صور الضرر');
        expect($sheet->getCell('C2')->getHyperlink()->getUrl())->toBe('buildings/4001/4001_building_901_damage photo.jpg');

        $spreadsheet->disconnectWorksheets();
        unlink($dataPath);
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

test('it matches semantic attachment type filters using arabic names and arcgis keywords', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/5001/attachments' => Http::response([
            'attachmentInfos' => [
                [
                    'id' => 1001,
                    'name' => 'صورة الهوية.jpg',
                    'contentType' => 'image/jpeg',
                ],
                [
                    'id' => 1002,
                    'name' => 'document.pdf',
                    'contentType' => 'application/pdf',
                    'keywords' => 'land ownership deed',
                ],
                [
                    'id' => 1003,
                    'name' => 'general-photo.jpg',
                    'contentType' => 'image/jpeg',
                ],
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/5001/attachments/1001*' => Http::response('identity-image'),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/5001/attachments/1002*' => Http::response('ownership-pdf'),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/5001/attachments/1003*' => Http::response('general-image'),
    ]);

    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 5000,
        'globalid' => 'building-for-housing-attachment',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 5001,
        'globalid' => 'housing-with-semantic-attachments',
        'parentglobalid' => 'building-for-housing-attachment',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'export_mode' => 'attachments',
            'export_type' => 'zip',
            'attachment_sources' => ['housing_unit_arcgis'],
            'attachment_type_filters' => ['identity', 'ownership'],
            'attachment_grouping' => 'by_housing_unit',
            'attachment_filename_strategy' => 'objectid_type',
            'include_attachment_index' => '1',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportAttachmentsJob($export->id))->handle(app(ArcgisService::class));

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->processed)->toBe(2);

        $zip = new \ZipArchive;
        $zip->open(storage_path('app/public/'.$export->file_name));

        expect($zip->getFromName('housing_units/5001/5001_housing_unit_1001_صورة الهوية.jpg'))->toBe('identity-image');
        expect($zip->getFromName('housing_units/5001/5001_housing_unit_1002_document.pdf'))->toBe('ownership-pdf');
        expect($zip->getFromName('housing_units/5001/5001_housing_unit_1003_general-photo.jpg'))->toBeFalse();

        $zip->close();
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

test('it can export all image attachments inside xlsx attachment columns', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/6001/attachments' => Http::response([
            'attachmentInfos' => [
                [
                    'id' => 1101,
                    'name' => 'id-card-front.png',
                    'contentType' => 'image/png',
                ],
                [
                    'id' => 1102,
                    'name' => 'id-card-back.png',
                    'contentType' => 'image/png',
                ],
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/6001/attachments/1101*' => Http::response($png, 200, [
            'Content-Type' => 'image/png',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/6001/attachments/1102*' => Http::response($png, 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 6001,
        'globalid' => 'building-with-name-column',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'export_mode' => 'data',
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
            'attachment_sources' => ['building_arcgis'],
            'attachment_type_filters' => ['identity'],
            'include_attachment_excel_columns' => '1',
            'attachment_excel_display' => 'images',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportAttachmentsJob($export->id))->handle(app(ArcgisService::class));

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->processed)->toBe(1);
        expect($export->file_name)->toEndWith('.xlsx');

        $spreadsheet = IOFactory::load(storage_path('app/public/'.$export->file_name));
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->getCell('A2')->getValue())->toBe(6001);
        expect($sheet->getDrawingCollection()->count())->toBe(2);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/attachments/1101'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/attachments/1102'));

        $spreadsheet->disconnectWorksheets();
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

test('it shows a clear note when image attachment columns have no matching attachments', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/6002/attachments' => Http::response([
            'attachmentInfos' => [],
        ]),
    ]);

    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 6002,
        'globalid' => 'building-without-matching-attachments',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'export_mode' => 'data',
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
            'attachment_sources' => ['building_arcgis'],
            'attachment_type_filters' => ['identity'],
            'include_attachment_excel_columns' => '1',
            'attachment_excel_display' => 'images',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportAttachmentsJob($export->id))->handle(app(ArcgisService::class));

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->file_name)->toEndWith('.xlsx');

        $spreadsheet = IOFactory::load(storage_path('app/public/'.$export->file_name));
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->getCell('B2')->getValue())->toBe('لا توجد مرفقات مطابقة');
        expect($sheet->getDrawingCollection()->count())->toBe(0);

        $spreadsheet->disconnectWorksheets();
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

test('it skips exports that are already claimed by another worker', function () {
    $user = User::factory()->create();

    $export = Export::query()->create([
        'status' => 'processing',
        'filters' => json_encode([
            'building_columns' => ['objectid'],
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 1,
        'processed' => 0,
        'file_name' => null,
    ]);

    (new ExportDataJob($export->id))->handle();

    $export->refresh();

    expect($export->status)->toBe('processing');
    expect($export->file_name)->toBeNull();
});
