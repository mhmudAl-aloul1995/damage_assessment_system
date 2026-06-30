<?php

use App\Models\User;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksScore;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

it('imports and manages the HEKS shelter repair module data', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $importer = app(HeksSpreadsheetImportService::class);
    $labelsPath = heksWorkbookPath('labels');
    $scorePath = heksWorkbookPath('scores');
    $followUpPath = heksWorkbookPath('followups');

    try {
        heksWriteLabelsWorkbook($labelsPath);
        heksWriteScoresWorkbook($scorePath);
        heksWriteFollowUpsWorkbook($followUpPath);

        $labelsSummary = $importer->import(
            new UploadedFile($labelsPath, 'heks-labels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'labels',
            $user->id
        )['summary'];
        $scoreSummary = $importer->import(
            new UploadedFile($scorePath, 'heks-scores.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'scores',
            $user->id
        )['summary'];
        $followUpSummary = $importer->import(
            new UploadedFile($followUpPath, 'heks-followups.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'followups',
            $user->id
        )['summary'];

        expect($labelsSummary['created_rows'])->toBe(1)
            ->and($scoreSummary['updated_rows'])->toBe(1)
            ->and($followUpSummary['updated_rows'])->toBe(1)
            ->and(HeksBeneficiary::query()->where('code', 'DGN1')->exists())->toBeTrue()
            ->and(HeksScore::query()->count())->toBe(1)
            ->and(HeksLabel::query()->where('label_key', 'damage_status')->exists())->toBeTrue()
            ->and(HeksFollowUp::query()->count())->toBe(1);

        $beneficiary = HeksBeneficiary::query()->where('code', 'DGN1')->sole();

        expect($beneficiary->name)->toBe('Test Beneficiary')
            ->and((float) $beneficiary->grant_amount)->toBe(1200.0)
            ->and($beneficiary->field_engineer)->toBe('Engineer One');

        $this->actingAs($user)
            ->get(route('heks.dashboard'))
            ->assertOk()
            ->assertSee('HEKS Dashboard')
            ->assertSee('Beneficiaries');

        $this->actingAs($user)
            ->get(route('heks.beneficiaries', ['q' => 'DGN1']))
            ->assertOk()
            ->assertSee('DGN1')
            ->assertSee('Test Beneficiary');

        $this->actingAs($user)
            ->put(route('heks.beneficiaries.update', $beneficiary), [
                'name' => 'Updated Beneficiary',
                'identity_number' => '900000001',
                'phone' => '0599000000',
                'grant_amount' => 1300,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($beneficiary->refresh()->name)->toBe('Updated Beneficiary')
            ->and((float) $beneficiary->grant_amount)->toBe(1300.0);
    } finally {
        @unlink($labelsPath);
        @unlink($scorePath);
        @unlink($followUpPath);
    }
});

it('adds HEKS to the sidebar for database officers', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $module = Sidebar::forUser($user)->firstWhere('key', 'heks');

    expect($module)->not->toBeNull()
        ->and($module['sections']->first()['url'])->toBe('heks');
});

function heksWorkbookPath(string $name): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.$name.'-'.Str::random(8).'.xlsx';
}

function heksWriteScoresWorkbook(string $path): void
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Scoring-Heks Final');
    $sheet->fromArray([
        [
            'رقم الطلب/الكود',
            'اسم رب الأسرة',
            'رقم هوية رب الأسرة',
            'رقم التواصل',
            'اسم المهندس الميداني',
            'تاريخ الزيارة',
            'المحافظة',
            'المنطقة/التجمع',
            'تقييم حالة ضرر المأوى:',
            'حالة السقف',
            'توصيات نهائية',
            'GRANT',
            'Payment_1',
            'Payment_2',
            'Payment_3',
            'تقييم الحالة الاجتماعية  (30)',
            'تقييم الحالة الفنية (70)',
            'Total Score',
            '__version__',
        ],
        [
            'DGN1',
            'Test Beneficiary',
            '900000001',
            '0599000000',
            'Engineer One',
            '2026-06-01',
            'Gaza',
            'Area A',
            'Partial damage',
            'Needs repair',
            'Eligible',
            1200,
            360,
            600,
            240,
            20,
            60,
            80,
            'v1',
        ],
    ]);

    (new Xlsx($spreadsheet))->save($path);
}

function heksWriteLabelsWorkbook(string $path): void
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Heks Final V1');
    $sheet->fromArray([
        [
            'رقم الطلب/الكود',
            'اسم رب الأسرة',
            'رقم هوية رب الأسرة',
            'رقم التواصل',
            'اسم المهندس الميداني',
            'تاريخ الزيارة',
            'تقييم حالة ضرر المأوى:',
            'حالة السقف',
            'حالة الإشغال الحالي للوحدة السكنية',
            'توصيات نهائية',
            '__version__',
        ],
        [
            'DGN1',
            'Test Beneficiary',
            '900000001',
            '0599000000',
            'Engineer One',
            '2026-04-23',
            'Initial partial damage',
            'Initial roof repair',
            'Occupied',
            'Initial eligible',
            'labels-v1',
        ],
    ]);

    (new Xlsx($spreadsheet))->save($path);
}

function heksWriteFollowUpsWorkbook(string $path): void
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('تقرير المتابعة -هيكس 125');
    $sheet->fromArray([
        [
            'Code',
            'Name',
            'GRANT',
            'Payment_1',
            'Payment_2',
            'Payment_3',
            'Visit Date',
            'visit #',
            'Engineer Name',
            'Working condition',
            'Other condition:',
            'Insert BOQ',
            'Insert BOQ_URL',
            'إجمالي ما تم انجازة حتى الآن ILS',
            'نسبة الإنجاز بالأعمال %',
            'توصيات المهندس للزيارة',
        ],
        [
            'DGN1',
            'Test Beneficiary',
            1200,
            360,
            600,
            240,
            '2026-06-10',
            '1',
            'Engineer One',
            'In progress',
            '',
            'boq.pdf',
            'https://example.test/boq.pdf',
            500,
            40,
            'Continue',
        ],
    ]);

    (new Xlsx($spreadsheet))->save($path);
}
