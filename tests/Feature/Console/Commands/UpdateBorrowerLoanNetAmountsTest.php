<?php

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('updates borrower loan net amounts by loan number', function () {
    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Existing Borrower',
        'borrower_id_number' => '900000001',
        'loan_number' => '0000900101',
        'loan_net_amount' => 100,
        'is_borrower_alive' => true,
    ]);

    DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Unchanged Borrower',
        'borrower_id_number' => '900000002',
        'loan_number' => '0000900102',
        'loan_net_amount' => 250,
        'is_borrower_alive' => true,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'loan-net-amounts-').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['#', 'رقم القرض', 'رقم الهوية', 'الجوال', 'الاسم', 'العنوان', 'صافي مبلغ القرض'],
        [1, '0000900101', '900000001', '0590000001', 'Existing Borrower', 'Gaza', '26,512.00'],
        [2, '0000900102', '900000002', '0590000002', 'Unchanged Borrower', 'Gaza', 250],
        [3, '0000900999', '900000999', '0590000999', 'Missing Borrower', 'Gaza', 300],
        [4, '0000900101', '900000001', '0590000001', 'Duplicate Borrower', 'Gaza', 500],
        [5, '0000900110', '900000110', '0590000110', 'Invalid Amount', 'Gaza', 'not-a-number'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    try {
        $this->artisan('borrowers:update-loan-net-amounts', [
            'path' => $path,
            '--dry-run' => true,
        ])
            ->assertSuccessful();

        expect((float) $borrower->refresh()->loan_net_amount)->toBe(100.0);

        $this->artisan('borrowers:update-loan-net-amounts', [
            'path' => $path,
        ])
            ->assertSuccessful();

        expect((float) $borrower->refresh()->loan_net_amount)->toBe(26512.0)
            ->and(DamageAssessmentBorrower::query()->where('loan_number', '0000900999')->exists())->toBeFalse();
    } finally {
        @unlink($path);
    }
});
