<?php

use App\Models\User;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksPayment;
use App\Modules\Heks\Models\HeksScore;
use App\Modules\Heks\Models\HeksScoringWeight;
use App\Modules\Heks\Models\HeksWorkAssignment;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

it('imports and manages the HEKS operational workbook', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $importer = app(HeksSpreadsheetImportService::class);
    $workbookPath = heksWorkbookPath('full');
    $followUpPath = heksWorkbookPath('followups');
    $boqPath = heksWorkbookPath('boq');
    $mismatchedBoqPath = heksWorkbookPath('boq-mismatch');

    try {
        heksWriteFullWorkbook($workbookPath);
        heksWriteFollowUpsWorkbook($followUpPath);
        heksWriteBoqWorkbook($boqPath);
        heksWriteBoqWorkbook($mismatchedBoqPath, 'DGN2', 'Other Beneficiary');

        $workbookSummary = $importer->import(
            new UploadedFile($workbookPath, 'heks-full.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'scores',
            $user->id
        )['summary'];
        $followUpSummary = $importer->import(
            new UploadedFile($followUpPath, 'heks-followups.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'followups',
            $user->id
        )['summary'];

        expect($workbookSummary['sheets'])->toHaveCount(7)
            ->and($followUpSummary['updated_rows'])->toBe(1)
            ->and(HeksBeneficiary::query()->where('code', 'DGN1')->exists())->toBeTrue()
            ->and(HeksBeneficiary::query()->where('code', 'DGN1')->value('is_selected'))->toBeTrue()
            ->and(HeksScore::query()->count())->toBeGreaterThanOrEqual(2)
            ->and(HeksLabel::query()->where('label_key', 'damage_status')->exists())->toBeTrue()
            ->and(HeksPayment::query()->count())->toBe(1)
            ->and(HeksWorkAssignment::query()->count())->toBe(1)
            ->and(HeksAttachment::query()->count())->toBe(1)
            ->and(HeksScoringWeight::query()->count())->toBeGreaterThan(0)
            ->and(HeksFollowUp::query()->count())->toBe(1);

        $beneficiary = HeksBeneficiary::query()->where('code', 'DGN1')->sole();

        expect($beneficiary->name)->toBe('Test Beneficiary')
            ->and((float) $beneficiary->grant_amount)->toBe(1200.0)
            ->and($beneficiary->field_engineer)->toBe('Engineer One')
            ->and($beneficiary->payment_status)->toBe('paid_100');

        expect(HeksScore::query()->where('classification', 'High')->exists())->toBeTrue();

        HeksBeneficiary::query()->create([
            'code' => 'DGN2',
            'name' => 'Other Beneficiary',
            'governorate' => 'North',
            'area' => 'Area B',
            'damage_status' => 'No damage',
            'raw_data' => [
                'Scoring-Heks Final' => [
                    'نوع الوحدة السكنية:' => 'Apartment',
                    'إجمالي عدد أفراد الأسرة الأساسية' => 4,
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('heks.dashboard'))
            ->assertOk()
            ->assertSee('فلاتر المستفيدين')
            ->assertSee('المستفيدون المطابقون')
            ->assertSee('جداول الكميات BOQ')
            ->assertSee('DGN1')
            ->assertSee('DGN2');

        $this->actingAs($user)
            ->get(route('heks.dashboard', ['governorate' => 'Gaza']))
            ->assertOk()
            ->assertSee('DGN1')
            ->assertDontSee('DGN2')
            ->assertSee('تقييم ضرر المأوى');

        $this->actingAs($user)
            ->get(route('heks.beneficiaries', ['q' => 'DGN1', 'selected' => 1]))
            ->assertOk()
            ->assertSee('DGN1')
            ->assertSee('مختار')
            ->assertSee('مدفوع كامل');

        $this->actingAs($user)
            ->get(route('heks.imports'))
            ->assertOk()
            ->assertSee('خريطة شيتات ملف HEKS')
            ->assertSee('Scoring-Heks Final')
            ->assertSee('KOBO_List')
            ->assertSee('125 BNFs -Data')
            ->assertSee('group_un2xy00')
            ->assertSee('مجموعات العمل');

        $this->actingAs($user)
            ->get(route('heks.scores'))
            ->assertOk()
            ->assertSee('التقييم والدرجات')
            ->assertSee('Intervention (ILS)')
            ->assertSee('High');

        $this->actingAs($user)
            ->get(route('heks.follow-ups'))
            ->assertOk()
            ->assertSee('جدول الكميات BOQ')
            ->assertSee('boq.pdf')
            ->assertSee('https://example.test/boq.pdf');

        $this->actingAs($user)
            ->get(route('heks.beneficiaries.edit', $beneficiary))
            ->assertOk()
            ->assertSee('جدول الكميات والتسعير BOQ')
            ->assertSee('استيراد BOQ')
            ->assertSee('data-control="select2"', false)
            ->assertSee('اختر أو ابحث عن بند')
            ->assertSee('فتح شاشة التسعير')
            ->assertSee('DGN1')
            ->assertSee('Test Beneficiary')
            ->assertSee('Scoring-Heks Final')
            ->assertSee('Partial damage')
            ->assertSee('damage_status')
            ->assertSee('boq.pdf')
            ->assertSee('High');

        $this->actingAs($user)
            ->post(route('heks.beneficiaries.boq-items.import', $beneficiary), [
                'file' => new UploadedFile($boqPath, 'beneficiary-boq.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect(HeksBoqItem::query()->where('source', 'beneficiary-boq.xlsx')->count())->toBe(1)
            ->and((float) HeksBoqItem::query()->where('source', 'beneficiary-boq.xlsx')->value('total_price_ils'))->toBe(1220.0);

        $this->actingAs($user)
            ->post(route('heks.beneficiaries.boq-items.import', $beneficiary), [
                'file' => new UploadedFile($mismatchedBoqPath, 'wrong-beneficiary-boq.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertSessionHasErrors('file')
            ->assertRedirect();

        expect(HeksBoqItem::query()->where('source', 'wrong-beneficiary-boq.xlsx')->count())->toBe(0);

        $this->actingAs($user)
            ->get(route('heks.beneficiaries.pricing', $beneficiary))
            ->assertOk()
            ->assertSee('heksPricingForm', false)
            ->assertSee('pricing-table', false)
            ->assertSee('pricingSearchInput', false)
            ->assertSee('عرض البنود المسعّرة فقط')
            ->assertSee('توريد و بناء بلوك اسمنتي');

        $this->actingAs($user)
            ->put(route('heks.beneficiaries.pricing.update', $beneficiary), [
                'items' => [
                    [
                        'source' => 'pricing',
                        'section' => 'اعمال البلوك',
                        'item_code' => '3.1',
                        'description' => 'توريد و بناء بلوك اسمنتي',
                        'unit' => 'M2',
                        'quantity' => 4,
                        'unit_price_ils' => 610,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('heks.beneficiaries.pricing', $beneficiary));

        expect((float) HeksBoqItem::query()->where('description', 'توريد و بناء بلوك اسمنتي')->value('total_price_ils'))->toBe(2440.0);

        $this->actingAs($user)
            ->post(route('heks.beneficiaries.boq-items.store', $beneficiary), [
                'source' => 'manual',
                'section' => 'اعمال البلوك',
                'item_code' => '3.1',
                'description' => 'توريد و بناء بلوك اسمنتي',
                'unit' => 'M2',
                'quantity' => 2,
                'unit_price_ils' => 610,
                'notes' => 'حسب جدول الكميات المعتمد',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $boqItem = HeksBoqItem::query()
            ->where('heks_beneficiary_id', $beneficiary->id)
            ->where('source', 'manual')
            ->sole();

        expect((float) $boqItem->total_price_ils)->toBe(1220.0);

        $this->actingAs($user)
            ->put(route('heks.boq-items.update', $boqItem), [
                'source' => 'manual',
                'section' => 'اعمال البلوك',
                'item_code' => '3.1',
                'description' => 'توريد و بناء بلوك اسمنتي',
                'unit' => 'M2',
                'quantity' => 3,
                'unit_price_ils' => 610,
                'notes' => 'تم تعديل الكمية',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect((float) $boqItem->refresh()->total_price_ils)->toBe(1830.0);

        $this->actingAs($user)
            ->get(route('heks.beneficiaries.edit', $beneficiary))
            ->assertOk()
            ->assertSee('توريد و بناء بلوك اسمنتي')
            ->assertSee('1,830.00');

        $this->actingAs($user)
            ->put(route('heks.beneficiaries.update', $beneficiary), [
                'name' => 'Updated Beneficiary',
                'identity_number' => '900000001',
                'phone' => '0599000000',
                'grant_amount' => 1300,
                'is_selected' => true,
                'payment_status' => 'paid_100',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($beneficiary->refresh()->name)->toBe('Updated Beneficiary')
            ->and((float) $beneficiary->grant_amount)->toBe(1300.0);
    } finally {
        @unlink($workbookPath);
        @unlink($followUpPath);
        @unlink($boqPath);
        @unlink($mismatchedBoqPath);
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

it('hides and blocks HEKS for non database officers', function () {
    $role = Role::findOrCreate('Project Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(Sidebar::forUser($user)->firstWhere('key', 'heks'))->toBeNull();

    $this->actingAs($user)
        ->get(route('heks.dashboard'))
        ->assertForbidden();
});

it('renders HEKS pagination with compact bootstrap controls', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    foreach (range(1, 25) as $index) {
        HeksBeneficiary::query()->create([
            'code' => "DGN{$index}",
            'name' => "Beneficiary {$index}",
            'identity_number' => "9000000{$index}",
        ]);
    }

    $this->actingAs($user)
        ->get(route('heks.beneficiaries'))
        ->assertOk()
        ->assertSee('heks-pagination', false)
        ->assertSee('page-link', false)
        ->assertDontSee('<svg', false);
});

function heksWorkbookPath(string $name): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.$name.'-'.Str::random(8).'.xlsx';
}

function heksWriteFullWorkbook(string $path): void
{
    $spreadsheet = new Spreadsheet;

    $assessment = $spreadsheet->getActiveSheet();
    $assessment->setTitle('Scoring-Heks Final');
    $assessment->fromArray([
        heksAssessmentHeaders(),
        heksAssessmentRow('DGN1'),
    ]);

    $selected = $spreadsheet->createSheet();
    $selected->setTitle('125 BNFs -Data');
    $selected->fromArray([
        heksAssessmentHeaders(),
        heksAssessmentRow('DGN1'),
    ]);

    $payments = $spreadsheet->createSheet();
    $payments->setTitle('3دفعات');
    $payments->fromArray([
        ['#', 'الكود', 'المستفيد', 'الهوية', 'المنحة', 'المبلغ بالحروف', 'تاريخ دفعة 1', '30%', 'الدفعة 30% بالحروف', 'تاريخ دفعة 2', '50%', 'الدفعة 50% بالحروف', 'تاريخ دفعة 3', '20%', 'الدفعة 20% بالحروف'],
        [1, 'DGN1', 'Test Beneficiary', '900000001', 1200, 'one thousand two hundred', 360, 360, 'three sixty', 600, 600, 'six hundred', 240, 240, 'two forty'],
    ]);

    $weights = $spreadsheet->createSheet();
    $weights->setTitle('Shelter Technical Weights');
    $weights->fromArray([
        ['Category', 'Indicator', 'Weight (from 100)', 'Question'],
        ['Sealing', 'Damage assessment', 4, 'تقييم حالة ضرر المأوى:'],
    ]);

    $technicalValues = $spreadsheet->createSheet();
    $technicalValues->setTitle('T-V');
    $technicalValues->fromArray([
        ['تقييم حالة ضرر المأوى:', 'حالة السقف'],
        ['لا يوجد ضرر', 'لا حاجة للصيانة'],
    ]);

    $attachments = $spreadsheet->createSheet();
    $attachments->setTitle('group_lm1ok19');
    $attachments->fromArray([
        ['صور الوحدة السكنية', 'صور الوحدة السكنية_URL', '_index', '_parent_table_name', '_parent_index'],
        ['house.jpg', 'https://example.test/house.jpg', 1, 'Scoring-Heks Final', 1],
    ]);

    $workGroups = $spreadsheet->createSheet();
    $workGroups->setTitle('مجموعات العمل');
    $workGroups->fromArray([
        [],
        ['#', 'الكود', 'اسم المستفيد', 'هوية المستفيد', 'قيمة العقد ILS', 'الدفعة الأولى  30% ILS', 'رقم التواصل', 'المهندس المتابع'],
        [1, 'DGN1', 'Test Beneficiary', '900000001', 1200, 360, '0599000000', 'Engineer One'],
    ]);

    (new Xlsx($spreadsheet))->save($path);
}

function heksAssessmentHeaders(): array
{
    return [
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
        'التقييم الكلي',
        'التصنيف',
        'Intervention (ILS)',
        'الدفعة  1',
        'الدفعة 2',
        'الدفعة 3',
        '__version__',
    ];
}

function heksAssessmentRow(string $code): array
{
    return [
        $code,
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
        'High',
        1200,
        360,
        600,
        240,
        'v1',
    ];
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

function heksWriteBoqWorkbook(string $path, string $code = 'DGN1', string $name = 'Test Beneficiary'): void
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($code);
    $sheet->fromArray([
        ['', '', '', '', '', '', ''],
        ['', $code, $name, '', '', '', ''],
        ['', '', '', '', '', '', ''],
        ['', '', '', '', '', '', ''],
        ['', '#', 'وصف البند', 'الوحدة', 'تكلفة الوحدة ILS', 'الكمية', 'الإجماليILS'],
        ['', 'اعمال البلوك', '', '', '', '', ''],
        ['', '3.1', 'توريد و بناء بلوك اسمنتي', 'M2', 610, 2, '=E7*F7'],
        ['', '3.2', 'توريد و بناء بلوك اسمنتي صفر', 'M2', 585, 0, '=E8*F8'],
    ]);

    (new Xlsx($spreadsheet))->save($path);
}
