<?php

use App\Jobs\ExportDataJob;
use App\Models\Export;
use OpenSpout\Reader\XLSX\Reader;

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
