<?php

use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('imports follow up BOQ workbooks with shifted Arabic headers', function () {
    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'DGE1',
        'name' => 'Manal Sameer',
    ]);

    $followUp = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '4',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'heks-boq-shifted-');
    $spreadsheet = new Spreadsheet;

    try {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DGE1');
        $sheet->fromArray([
            ['', '', '', '', '', '', '', '', ''],
            ['', 'DGE1', 'Manal Sameer', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['', 'م', 'رقم البند', 'وصف البند', 'الوحدة', 'تكلفة الوحدة ILS', 'الكمية', 'الإجمالي ILS', 'ملاحظات'],
            ['', '', '', 'اعمال البلوك', '', '', '', '', ''],
            ['', '', '3.1', 'توريد و بناء بلوك اسمنتي', 'M2', 610, 2, '=F7*G7', ''],
        ]);

        (new Xlsx($spreadsheet))->save($path);

        $summary = app(HeksSpreadsheetImportService::class)->importBeneficiaryBoq(
            new UploadedFile($path, 'shifted-arabic-boq.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            $beneficiary,
            $followUp
        );

        $item = HeksBoqItem::query()->where('heks_follow_up_id', $followUp->id)->sole();

        expect($summary['imported_rows'])->toBe(1)
            ->and($item->item_code)->toBe('3.1')
            ->and($item->section)->toBe('اعمال البلوك')
            ->and((float) $item->total_price_ils)->toBe(1220.0);
    } finally {
        $spreadsheet->disconnectWorksheets();

        if (file_exists($path)) {
            unlink($path);
        }
    }
});
