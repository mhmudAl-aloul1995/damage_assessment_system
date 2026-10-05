<?php

use App\Modules\Heks\Models\HeksBoqCatalogItem;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

test('heks boq catalog import command imports approved catalog rows from excel', function () {
    $path = storage_path('framework/testing/heks-boq-catalog.xlsx');

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    $workbook = new Spreadsheet;
    $sheet = $workbook->getActiveSheet();
    $sheet->setTitle('جدول كميات معتمد');
    $sheet->fromArray([
        [null, null, null, null, null],
        [null, '#', 'وصف البند', 'الوحدة', 'تكلفة الوحدة ILS'],
        [null, 'اعمال البلوك', null, null, null],
        [null, 3.4, 'Catalog item 3.4', 'M2', 120],
        [null, 8.13, 'Catalog item 8.13', 'M2', 35],
    ]);

    IOFactory::createWriter($workbook, 'Xlsx')->save($path);
    $workbook->disconnectWorksheets();

    $this->artisan('heks:import-boq-catalog', [
        'file' => $path,
    ])
        ->expectsOutputToContain('Created: 2')
        ->assertSuccessful();

    expect(HeksBoqCatalogItem::query()->where('item_code', '3.4')->value('unit_price_ils'))->toBe('120.00')
        ->and(HeksBoqCatalogItem::query()->where('item_code', '8.13')->value('section'))->toBe('اعمال البلوك');
});
