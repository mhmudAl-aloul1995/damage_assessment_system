<?php

use App\Exports\BorrowerReportExport;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('imports branch address and target housing unit from the new census worksheet by form number', function () {
    $borrower = DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB4',
        'borrower_name' => 'Existing Borrower',
        'borrower_id_number' => '801180407',
        'loan_branch_address' => 'Old Branch',
        'loan_target_housing_unit' => 'Old Unit',
        'is_borrower_alive' => true,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'iqrad-new-census-').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('القائمة الاولية');
    $spreadsheet->getActiveSheet()->fromArray([
        ['id', 'رقم الاستمارة', 'العنوان (الفرع)', 'عنوان الوحدة السكنية المستهدفة بالقرض( المحافظة- المدينة- أقرب معلم)'],
        [1, 'IDB4', 'Wrong Branch', 'Wrong Unit'],
    ]);
    $newCensusSheet = $spreadsheet->createSheet();
    $newCensusSheet->setTitle('الحصر الجديد');
    $newCensusSheet->fromArray([
        ['id', 'رقم الاستمارة', 'رقم هوية المقترض', 'الاسم', 'الجوال', 'العنوان (الفرع)', 'عنوان الوحدة السكنية المستهدفة بالقرض( المحافظة- المدينة- أقرب معلم)'],
        [5, 'IDB4', '801180407', 'Existing Borrower', '0592896235', 'النصر', 'بيت لاهيا السلاطين القرعة الخامسة'],
        [6, 'IDB404', '999999999', 'Missing Borrower', '0590000000', 'غزة', 'غزة'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    try {
        $this->artisan('borrowers:import-iqrad-new-census', [
            'path' => $path,
            '--dry-run' => true,
        ])
            ->assertSuccessful();

        expect($borrower->refresh()->loan_branch_address)->toBe('Old Branch')
            ->and($borrower->loan_target_housing_unit)->toBe('Old Unit');

        $this->artisan('borrowers:import-iqrad-new-census', [
            'path' => $path,
        ])
            ->assertSuccessful();

        expect($borrower->refresh()->loan_branch_address)->toBe('النصر')
            ->and($borrower->loan_target_housing_unit)->toBe('بيت لاهيا السلاطين القرعة الخامسة')
            ->and(DamageAssessmentBorrower::query()->where('form_number', 'IDB404')->exists())->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('includes the target housing unit in custom borrower report columns', function () {
    $borrower = DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB-CUSTOM',
        'borrower_name' => 'Custom Export Borrower',
        'borrower_id_number' => '820000777',
        'loan_target_housing_unit' => 'غزة المخابرات',
        'is_borrower_alive' => true,
    ]);

    $export = new BorrowerReportExport(collect([$borrower]), 'custom', [
        'loan_target_housing_unit',
    ]);

    expect(BorrowerReportExport::availableColumns())->toHaveKey('loan_target_housing_unit')
        ->and(BorrowerReportExport::availableColumnGroups()['بيانات القرض'])->toContain('loan_target_housing_unit')
        ->and($export->headings())->toBe(['الوحدة السكنية المستهدفة في القرض'])
        ->and($export->map($borrower))->toBe(['غزة المخابرات']);
});
