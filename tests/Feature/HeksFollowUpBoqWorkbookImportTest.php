<?php

use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

it('retries a previously failed follow up BOQ import after header detection is expanded', function () {
    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'F34',
        'name' => 'Hesham Beneficiary',
    ]);

    $followUp = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '5',
        'boq_filename' => 'hesham-8_35_1.xlsx',
        'boq_url' => 'https://example.test/hesham-8_35_1.xlsx',
    ]);

    HeksAttachment::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'source' => "follow-up:{$followUp->id}",
        'attachment_type' => 'follow_up_boq',
        'filename' => $followUp->boq_filename,
        'url' => $followUp->boq_url,
        'raw_data' => [
            'boq_import_summary' => [
                'imported' => false,
                'error' => 'لم يتم العثور على صف عناوين جدول الكميات في الملف.',
            ],
        ],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'heks-boq-retry-');
    $spreadsheet = new Spreadsheet;

    try {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('F34');
        $sheet->fromArray([
            ['', '', '', '', '', '', ''],
            ['', 'F34', 'Hesham Beneficiary', '', '', '', ''],
            ['', '', '', '', '', '', ''],
            ['', 'رقم البند', 'بيان البند', 'الوحدة', 'سعر الوحدة', 'Qty', 'Total'],
            ['', '4.1', 'تركيب عوارض معدنية', 'LS', 3430, 1, '=E5*F5'],
        ]);

        (new Xlsx($spreadsheet))->save($path);

        Http::fake([
            'https://example.test/hesham-8_35_1.xlsx' => Http::response(file_get_contents($path), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]),
        ]);

        $summary = app(HeksSpreadsheetImportService::class)->importFollowUpBoq($followUp);
        $attachment = HeksAttachment::query()->where('source', "follow-up:{$followUp->id}")->sole();

        expect($summary['imported'])->toBeTrue()
            ->and($summary['imported_rows'])->toBe(1)
            ->and(data_get($attachment->raw_data, 'boq_import_summary.imported'))->toBeTrue()
            ->and(HeksBoqItem::query()->where('heks_follow_up_id', $followUp->id)->count())->toBe(1);
    } finally {
        $spreadsheet->disconnectWorksheets();

        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('imports follow up BOQ workbooks when the BOQ table is not on the first sheet', function () {
    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'GDM6',
        'name' => 'Fawzi Beneficiary',
    ]);

    $followUp = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '3',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'heks-boq-second-sheet-');
    $spreadsheet = new Spreadsheet;

    try {
        $coverSheet = $spreadsheet->getActiveSheet();
        $coverSheet->setTitle('Done');
        $coverSheet->fromArray([
            ['Beneficiary', 'Fawzi Beneficiary'],
            ['Code', 'GDM6'],
            ['Status', 'Done'],
        ]);

        $boqSheet = $spreadsheet->createSheet();
        $boqSheet->setTitle('BOQ');
        $boqSheet->fromArray([
            ['', '', '', '', '', '', ''],
            ['', 'GDM6', 'Fawzi Beneficiary', '', '', '', ''],
            ['', '', '', '', '', '', ''],
            ['', 'Item No', 'Description', 'Unit', 'Unit Price', 'Quantity', 'Total'],
            ['', '5.1', 'Install bathroom accessories', 'LS', 5859, 1, '=E5*F5'],
        ]);

        (new Xlsx($spreadsheet))->save($path);

        $summary = app(HeksSpreadsheetImportService::class)->importBeneficiaryBoq(
            new UploadedFile($path, 'Done Fawzi.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            $beneficiary,
            $followUp
        );

        $item = HeksBoqItem::query()->where('heks_follow_up_id', $followUp->id)->sole();

        expect($summary['imported_rows'])->toBe(1)
            ->and($item->item_code)->toBe('5.1')
            ->and($item->raw_data['sheet'])->toBe('BOQ')
            ->and((float) $item->total_price_ils)->toBe(5859.0);
    } finally {
        $spreadsheet->disconnectWorksheets();

        if (file_exists($path)) {
            unlink($path);
        }
    }
});
