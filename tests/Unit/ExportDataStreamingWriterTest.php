<?php

use App\Jobs\ExportDataJob;
use App\Models\Building;
use App\Models\Export;
use App\Models\User;
use OpenSpout\Reader\XLSX\Reader;
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
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('it filters building exports by the building end date range', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 2001,
        'globalid' => 'building-before-range',
        'end' => '2026-04-30 10:00:00',
    ]);

    Building::query()->create([
        'objectid' => 2002,
        'globalid' => 'building-in-range',
        'end' => '2026-05-10 10:00:00',
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

        expect($rows[1])->toBe([2002, '2026-05-10 10:00:00']);
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});
