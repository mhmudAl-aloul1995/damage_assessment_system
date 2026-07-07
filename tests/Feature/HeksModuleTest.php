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
use App\Modules\Heks\Models\HeksSurveyValueHistory;
use App\Modules\Heks\Models\HeksWorkAssignment;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
        Http::fake([
            'https://example.test/boq.xlsx' => Http::response(file_get_contents($boqPath), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]),
        ]);

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

        expect($workbookSummary['sheets'])->toHaveCount(9)
            ->and($followUpSummary['updated_rows'])->toBe(1)
            ->and(HeksBeneficiary::query()->where('code', 'DGN1')->exists())->toBeTrue()
            ->and(HeksBeneficiary::query()->where('code', 'DGN1')->value('is_selected'))->toBeTrue()
            ->and(HeksScore::query()->count())->toBeGreaterThanOrEqual(2)
            ->and(HeksLabel::query()->where('label_key', 'damage_status')->exists())->toBeTrue()
            ->and(HeksPayment::query()->count())->toBe(1)
            ->and(HeksWorkAssignment::query()->count())->toBe(1)
            ->and(HeksAttachment::query()->count())->toBe(2)
            ->and(HeksAttachment::query()->where('attachment_type', 'follow_up_boq')->where('filename', 'boq.xlsx')->exists())->toBeTrue()
            ->and(HeksBoqItem::query()->where('source', 'boq.xlsx')->whereNotNull('heks_follow_up_id')->count())->toBe(1)
            ->and(HeksScoringWeight::query()->count())->toBeGreaterThan(0)
            ->and(HeksScoringWeight::query()->where('category', 'Sealing & Internal Privacy')->where('indicator', 'Damage assessment')->exists())->toBeTrue()
            ->and(HeksFollowUp::query()->count())->toBe(1);

        $beneficiary = HeksBeneficiary::query()->where('code', 'DGN1')->sole();
        $followUp = HeksFollowUp::query()->where('code', 'DGN1')->sole();

        expect($beneficiary->name)->toBe('Test Beneficiary')
            ->and((float) $beneficiary->grant_amount)->toBe(1200.0)
            ->and($beneficiary->field_engineer)->toBe('Engineer One')
            ->and($beneficiary->payment_status)->toBe('paid_100');

        expect(HeksScore::query()->where('classification', 'High')->exists())->toBeTrue();
        $v1Score = HeksScore::query()
            ->where('source', 'Scoring-Heks- V1')
            ->where('heks_beneficiary_id', $beneficiary->id)
            ->sole();

        expect((float) $v1Score->social_score)->toBe(20.0)
            ->and((float) $v1Score->technical_score)->toBe(60.0)
            ->and((float) $v1Score->total_score)->toBe(80.0)
            ->and($v1Score->classification)->toBe('High');

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
            ->assertSee('boq.xlsx')
            ->assertSee('https://example.test/boq.xlsx')
            ->assertSee('فتح BOQ الزيارة')
            ->assertSee('خيارات الملف')
            ->assertSee('تحميل Excel الأصلي')
            ->assertSee('فتح رابط KoBo')
            ->assertSee('تم استيراد البنود');

        $this->actingAs($user)
            ->get(route('heks.follow-ups.boq', $followUp))
            ->assertOk()
            ->assertSee('جدول كميات زيارة المتابعة')
            ->assertSee('BOQ الأساسي')
            ->assertSee('توريد و بناء بلوك اسمنتي')
            ->assertSee('1,220.00');

        $this->actingAs($user)
            ->get(route('heks.beneficiaries.edit', $beneficiary))
            ->assertOk()
            ->assertSee('جدول الكميات والتسعير BOQ')
            ->assertSee('استيراد BOQ')
            ->assertSee('data-control="select2"', false)
            ->assertSee('اختر أو ابحث عن بند')
            ->assertSee('فتح شاشة التسعير')
            ->assertSee('التقييم الاجتماعي')
            ->assertSee('التقييم الفني')
            ->assertSee('التقييم النهائي')
            ->assertSee('20.00')
            ->assertSee('60.00')
            ->assertSee('80.00')
            ->assertSee('Technical vulnerability')
            ->assertSee('Social vulnerability')
            ->assertSee('Extreme')
            ->assertSee('معايير التقييم الاجتماعي')
            ->assertSee('Female-headed household')
            ->assertSee('تقييم الحالة الاجتماعية')
            ->assertSee('Scoring matrix S -2')
            ->assertSee('التقييم الفني للمأوى')
            ->assertSee('البند')
            ->assertSee('القيمة')
            ->assertSee('النقاط')
            ->assertSee('Damage assessment')
            ->assertSee('تقييم حالة ضرر المأوى')
            ->assertSee('DGN1')
            ->assertSee('Test Beneficiary')
            ->assertSee('Scoring-Heks Final')
            ->assertSee('Partial damage')
            ->assertSee('استبيان KoBo للمستفيد')
            ->assertSee('تقييالإستبيانرار')
            ->assertSee('damage_status')
            ->assertSee('boq.xlsx')
            ->assertSee('High')
            ->assertSee('name="social_score"', false)
            ->assertSee('name="technical_score"', false)
            ->assertSee('name="total_score"', false);

        $this->actingAs($user)
            ->post(route('heks.beneficiaries.scores.store', $beneficiary), [
                'source' => 'manual',
                'social_score' => 22,
                'technical_score' => 55,
                'total_score' => 77,
                'classification' => 'Very High',
                'grant_amount' => 1500,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect(HeksScore::query()
            ->where('heks_beneficiary_id', $beneficiary->id)
            ->where('source', 'manual')
            ->where('classification', 'Very High')
            ->exists())->toBeTrue();

        $this->actingAs($user)
            ->post(route('heks.beneficiaries.boq-items.import', $beneficiary), [
                'file' => new UploadedFile($boqPath, 'beneficiary-boq.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect(HeksBoqItem::query()->where('source', 'beneficiary-boq.xlsx')->whereNull('heks_follow_up_id')->count())->toBe(1)
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

        expect((float) HeksBoqItem::query()->whereNull('heks_follow_up_id')->where('description', 'توريد و بناء بلوك اسمنتي')->value('total_price_ils'))->toBe(2440.0);

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

it('renders HEKS dashboard without loading bulky survey answers', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'HEAVY1',
        'name' => 'Heavy Survey Beneficiary',
        'damage_status' => 'Moderate',
        'raw_data' => [
            'heks-main' => collect(range(1, 400))
                ->mapWithKeys(fn (int $index): array => ["question_{$index}" => str_repeat('answer ', 40)])
                ->all(),
        ],
    ]);

    HeksLabel::query()->insert(collect(range(1, 400))
        ->map(fn (int $index): array => [
            'heks_beneficiary_id' => $beneficiary->id,
            'source' => 'heks-main',
            'label_key' => "survey:{$index}",
            'label_value' => str_repeat('answer ', 40),
            'created_at' => now(),
            'updated_at' => now(),
        ])
        ->all());

    $this->actingAs($user)
        ->get(route('heks.dashboard'))
        ->assertOk()
        ->assertSee('HEAVY1');
});

it('shows HEKS BOQ pricing as grouped catalog sections', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'BOQ1',
        'name' => 'BOQ Beneficiary',
        'identity_number' => '900004444',
    ]);

    $this->actingAs($user)
        ->get(route('heks.beneficiaries.pricing', $beneficiary))
        ->assertOk()
        ->assertSee('إضافة بند إلى BOQ الأساسي')
        ->assertSee('استيراد BOQ أساسي')
        ->assertSee('pricingSectionFilter', false)
        ->assertSee('pricing-section-row', false)
        ->assertSee('data-section-header', false)
        ->assertSee('items[0][notes]', false);

    $this->actingAs($user)
        ->put(route('heks.beneficiaries.pricing.update', $beneficiary), [
            'items' => [
                [
                    'source' => 'pricing',
                    'section' => 'اعمال البلوك',
                    'item_code' => '3.1',
                    'description' => 'توريد و بناء بلوك اسمنتي',
                    'unit' => 'M2',
                    'quantity' => 2,
                    'unit_price_ils' => 610,
                    'notes' => 'حسب نموذج BOQ KoBo',
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('heks.beneficiaries.pricing', $beneficiary));

    $item = HeksBoqItem::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect((float) $item->total_price_ils)->toBe(1220.0)
        ->and($item->notes)->toBe('حسب نموذج BOQ KoBo');

    $this->actingAs($user)
        ->get(route('heks.beneficiaries.pricing', $beneficiary))
        ->assertOk()
        ->assertSee("delete-pricing-row-{$item->id}", false)
        ->assertSee('حذف');

    $this->actingAs($user)
        ->delete(route('heks.boq-items.destroy', $item))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(HeksBoqItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

it('manages the HEKS BOQ pricing catalog', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('heks.pricing-catalog'))
        ->assertOk()
        ->assertSee('جدول التسعير')
        ->assertSee('إضافة بند تسعير');

    $this->actingAs($user)
        ->post(route('heks.pricing-catalog.store'), [
            'section' => 'أعمال اختبار',
            'item_code' => '99.1',
            'description' => 'بند تسعير قابل للتعديل',
            'unit' => 'M2',
            'unit_price_ils' => 125.5,
            'notes' => 'ملاحظة كتالوج',
            'is_active' => 1,
            'sort_order' => 99,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $catalogItem = \App\Modules\Heks\Models\HeksBoqCatalogItem::query()
        ->where('item_code', '99.1')
        ->sole();

    expect((float) $catalogItem->unit_price_ils)->toBe(125.5);

    $this->actingAs($user)
        ->put(route('heks.pricing-catalog.update', $catalogItem), [
            'section' => 'أعمال اختبار محدثة',
            'item_code' => '99.2',
            'description' => 'بند تسعير بعد التعديل',
            'unit' => 'عدد',
            'unit_price_ils' => 200,
            'notes' => 'تم التعديل',
            'sort_order' => 100,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($catalogItem->refresh()->is_active)->toBeFalse()
        ->and($catalogItem->item_code)->toBe('99.2')
        ->and((float) $catalogItem->unit_price_ils)->toBe(200.0);

    $this->actingAs($user)
        ->delete(route('heks.pricing-catalog.destroy', $catalogItem))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(\App\Modules\Heks\Models\HeksBoqCatalogItem::query()->whereKey($catalogItem->id)->exists())->toBeFalse();
});

it('updates HEKS survey values and stores previous values in history', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'SURVEY1',
        'name' => 'Survey Beneficiary',
        'identity_number' => '900001111',
        'raw_data' => [
            'KOBO_List' => [
                'حالة السقف' => 'بحاجة إلى صيانة بسيطة',
            ],
        ],
    ]);

    $this->actingAs($user)
        ->put(route('heks.beneficiaries.survey-values.update', $beneficiary), [
            'source' => 'KOBO_List',
            'field_key' => 'حالة السقف',
            'value' => 'أضرار متوسطة',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($beneficiary->refresh()->raw_data['KOBO_List']['حالة السقف'])->toBe('أضرار متوسطة');

    $this->assertDatabaseHas('heks_survey_value_histories', [
        'heks_beneficiary_id' => $beneficiary->id,
        'user_id' => $user->id,
        'source' => 'KOBO_List',
        'field_key' => 'حالة السقف',
        'old_value' => 'بحاجة إلى صيانة بسيطة',
        'new_value' => 'أضرار متوسطة',
    ]);

    expect(HeksSurveyValueHistory::query()->where('heks_beneficiary_id', $beneficiary->id)->count())->toBe(1);
});

it('shows follow-up BOQ items directly on the follow-ups page', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'BOQ-FU',
        'name' => 'Follow Up BOQ Beneficiary',
        'identity_number' => '900002222',
    ]);

    $followUp = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '2',
        'working_condition' => 'work_has_been_finished_and_due_for_the_f',
        'boq_filename' => 'visit-boq.xlsx',
        'boq_url' => 'https://example.test/visit-boq.xlsx',
    ]);

    HeksBoqItem::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'heks_follow_up_id' => $followUp->id,
        'source' => 'visit-boq.xlsx',
        'section' => 'Masonry',
        'item_code' => '3.1',
        'description' => 'Inline BOQ item',
        'unit' => 'M2',
        'quantity' => 2,
        'unit_price_ils' => 100,
        'total_price_ils' => 200,
    ]);

    $this->actingAs($user)
        ->get(route('heks.follow-ups'))
        ->assertOk()
        ->assertSee('عرض BOQ')
        ->assertSee('تم الانتهاء من العمل ويستحق الدفعة النهائية')
        ->assertDontSee('work_has_been_finished_and_due_for_the_f')
        ->assertSee('Inline BOQ item')
        ->assertSee('200.00')
        ->assertSee('ترحيل BOQ من Excel');
});

it('shows beneficiary image attachments in a dedicated photos tab', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'IMG1',
        'name' => 'Image Beneficiary',
        'identity_number' => '900003333',
    ]);

    HeksAttachment::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'source' => 'group_lm1ok19',
        'filename' => 'shelter-photo.jpg',
        'url' => 'https://example.test/shelter-photo.jpg',
        'attachment_type' => 'صور الوحدة السكنية',
    ]);

    $this->actingAs($user)
        ->get(route('heks.beneficiaries.edit', $beneficiary))
        ->assertOk()
        ->assertSee('photo-card', false)
        ->assertSee('shelter-photo.jpg')
        ->assertSee(route('heks.beneficiaries.attachments.show', [$beneficiary, HeksAttachment::query()->where('filename', 'shelter-photo.jpg')->sole()]));
});

it('streams HEKS Kobo image attachments through the application with a token', function () {
    config(['services.kobotoolbox.token' => 'secret-token']);
    Http::fake([
        'https://kc.kobotoolbox.org/media/original?media_file=qais88%2Fattachments%2Fsubmission%2Fphoto.jpg' => Http::response('image-binary', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'IMG2',
        'name' => 'Relative Image Beneficiary',
    ]);

    $attachment = HeksAttachment::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'source' => 'heks-main',
        'filename' => 'qais88/attachments/submission/photo.jpg',
        'attachment_type' => 'shelter_photo',
    ]);

    $this->actingAs($user)
        ->get(route('heks.beneficiaries.attachments.show', [$beneficiary, $attachment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertSee('image-binary');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Token secret-token'));
});

it('imports image attachments from Arabic KoBo group sheets', function () {
    $path = heksWorkbookPath('arabic-attachments');
    $spreadsheet = new Spreadsheet;

    try {
        $assessment = $spreadsheet->getActiveSheet();
        $assessment->setTitle('Heks Final V1');
        $assessment->fromArray([
            ['رقم الطلب/الكود', 'اسم رب الأسرة'],
            ['DGN1', 'Test Beneficiary'],
        ]);

        $documents = $spreadsheet->createSheet();
        $documents->setTitle('group_un2xy00');
        $documents->fromArray([
            ['قم بتصوير المستندات المتوفرة', 'قم بتصوير المستندات المتوفرة_URL', 'قم بتصوير المستندات المتوفرة', 'قم بتصوير المستندات المتوفرة_URL', '_index', '_parent_table_name', '_parent_index'],
            ['document.jpg', 'https://example.test/document.jpg', '', '', 1, 'Heks Final V1', 1],
        ]);

        $photos = $spreadsheet->createSheet();
        $photos->setTitle('group_lm1ok19');
        $photos->fromArray([
            ['صور الوحدة السكنية', 'صور الوحدة السكنية_URL', '_index', '_parent_table_name', '_parent_index'],
            ['house.jpg', '', 1, 'Heks Final V1', 1],
        ]);

        (new Xlsx($spreadsheet))->save($path);

        app(HeksSpreadsheetImportService::class)->import(
            new UploadedFile($path, 'heks-arabic-attachments.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'scores'
        );

        $beneficiary = HeksBeneficiary::query()->where('code', 'DGN1')->sole();

        expect(HeksAttachment::query()->where('heks_beneficiary_id', $beneficiary->id)->count())->toBe(2)
            ->and(HeksAttachment::query()->where('filename', 'document.jpg')->value('url'))->toBe('https://example.test/document.jpg')
            ->and(HeksAttachment::query()->where('filename', 'house.jpg')->where('attachment_type', 'shelter_photo')->exists())->toBeTrue();
    } finally {
        $spreadsheet->disconnectWorksheets();

        if (file_exists($path)) {
            unlink($path);
        }
    }
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

    $scoringV1 = $spreadsheet->createSheet();
    $scoringV1->setTitle('Scoring-Heks- V1');
    $scoringV1->fromArray([
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
        ['Category', 'Indicator', '', 'Weight (from 100)', '', '', '', ''],
        ['', '', '', 'Max', 'AVG', 'Min', '', ''],
        ['Sealing & Internal Privacy', 'Damage assessment', 4, 4, 2, 0, 1, 'تقييم حالة ضرر المأوى:'],
    ]);

    $technicalValues = $spreadsheet->createSheet();
    $technicalValues->setTitle('T-V');
    $technicalValues->fromArray([
        ['تقييم حالة ضرر المأوى:', 'حالة السقف'],
        ['لا يوجد ضرر', 'لا حاجة للصيانة'],
    ]);

    $socialValues = $spreadsheet->createSheet();
    $socialValues->setTitle('S-V');
    $socialValues->fromArray([
        ['تقييم الحالة الاجتماعية  (30)', 'حالة رب الأسرة'],
        [20, 'ذكر'],
        [30, 'أنثى'],
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
        'تقييم الحالة الاجتماعية من 35',
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
        35,
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
            'boq.xlsx',
            'https://example.test/boq.xlsx',
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
