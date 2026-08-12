<?php

use App\Jobs\ExportAttachmentsJob;
use App\Jobs\ExportDataJob;
use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\Export;
use App\Models\User;
use App\services\ArcgisService;
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

test('it exports selected arcgis building attachments to a zip with an index', function () {
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
            ],
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/4001/attachments/901*' => Http::response('image-bytes'),
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
            'export_mode' => 'attachments',
            'export_type' => 'zip',
            'attachment_sources' => ['building_arcgis'],
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

        expect($zip->getFromName('buildings/4001/4001_building_901_damage photo.jpg'))->toBe('image-bytes');
        expect($zip->getFromName('attachments-index.csv'))->toContain('building-with-attachment');

        $zip->close();
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
