<?php

use App\Models\KoboRestSubmission;
use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqCatalogItem;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['services.kobotoolbox.rest_service_token' => 'test-kobo-token']);
});

test('kobo rest submission requires configured token header', function () {
    $this->postJson('/api/kobo/iqrad', [
        '_uuid' => 'missing-token-submission',
    ])->assertUnauthorized();

    expect(KoboRestSubmission::query()->count())->toBe(0);
});

test('kobo rest submission stores json payload', function () {
    $response = $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', [
            '_uuid' => 'uuid:iqrad-001',
            '_id' => 15,
            'borrower_name' => 'Mona Saleh',
            'borrower_id_number' => '900000001',
            'employment_status' => 'not_working',
            'loan_unit_damage_status' => 'partial',
            '_attachments' => [
                [
                    'filename' => 'damage.jpg',
                    'download_url' => 'https://kf.example.test/media/damage.jpg',
                ],
            ],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Kobo submission received.')
        ->assertJsonPath('sync_status', 'synced');

    $submission = KoboRestSubmission::query()->first();
    $borrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid:iqrad-001')->sole();

    expect($submission)
        ->service_name->toBe('iqrad')
        ->submission_uuid->toBe('uuid:iqrad-001')
        ->sync_status->toBe('synced')
        ->damage_assessment_borrower_id->toBe($borrower->id)
        ->payload->toMatchArray([
            'borrower_name' => 'Mona Saleh',
            'loan_unit_damage_status' => 'partial',
        ])
        ->and($borrower)
        ->source_submission_id->toBe(15)
        ->borrower_name->toBe('Mona Saleh')
        ->borrower_id_number->toBe('900000001')
        ->risk_level->toBe('low')
        ->attachments_count->toBe(1)
        ->and($borrower->attachments()->first()->url)->toBe('https://kf.example.test/media/damage.jpg');
});

test('kobo rest submission syncs HEKS main survey payload', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-main-001',
            'code' => 'GDQ3',
            'beneficiary_name' => 'Ashraf Hamdi',
            'identity_number' => '597106204',
            'phone' => '0592026269',
            'visit_date' => '2026-06-06',
            'damage_status' => 'أضرار جزئية متوسطة',
            'social_score' => 30,
            'technical_score' => 42,
            'total_score' => 72,
            'classification' => 'Very High',
            'roof_status' => 'بحاجة إلى صيانة بسيطة',
            '_attachments' => [
                [
                    'filename' => 'house.jpg',
                    'download_url' => 'https://kf.example.test/house.jpg',
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'GDQ3')->sole();
    $score = HeksScore::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect($beneficiary->name)->toBe('Ashraf Hamdi')
        ->and($beneficiary->identity_number)->toBe('597106204')
        ->and($beneficiary->damage_status)->toBe('أضرار جزئية متوسطة')
        ->and((float) $score->social_score)->toBe(30.0)
        ->and((float) $score->technical_score)->toBe(42.0)
        ->and((float) $score->total_score)->toBe(72.0)
        ->and($score->classification)->toBe('Very High')
        ->and(HeksAttachment::query()->where('filename', 'house.jpg')->where('attachment_type', 'shelter_photo')->exists())->toBeTrue()
        ->and(KoboRestSubmission::query()->where('submission_uuid', 'uuid:heks-main-001')->value('sync_status'))->toBe('synced');
});

test('kobo rest submission maps HEKS scoring workbook column names', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-score-workbook-columns',
            'رقم الطلب/الكود' => 'GDQ9',
            'اسم رب الأسرة' => 'Workbook Columns Beneficiary',
            'رقم هوية رب الأسرة' => '123456789',
            "Intervention \n(ILS)" => '12096.50',
            'تقييم الحالة الاجتماعية  (30)' => '30',
            "تقييم الحالة \nالفنية (70)" => '42',
            'التقييم الكلي' => '72',
            'التصنيف' => 'Very High',
            'ملاحظات إجتماعية' => 'Social note',
            'ملاحظات المهندسين' => 'Engineer note',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'GDQ9')->sole();
    $score = HeksScore::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect($beneficiary->name)->toBe('Workbook Columns Beneficiary')
        ->and($beneficiary->identity_number)->toBe('123456789')
        ->and((float) $beneficiary->grant_amount)->toBe(12096.5)
        ->and($beneficiary->social_notes)->toBe('Social note')
        ->and($beneficiary->engineer_notes)->toBe('Engineer note')
        ->and((float) $score->social_score)->toBe(30.0)
        ->and((float) $score->technical_score)->toBe(42.0)
        ->and((float) $score->total_score)->toBe(72.0)
        ->and($score->classification)->toBe('Very High');
});

test('kobo rest submission ignores computed HEKS display columns and invalid values', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-computed-display-columns',
            'رقم الطلب/الكود' => 'CLEAN1',
            'Name' => 'نفسه',
            'Name:${Name}' => 'نفسه',
            'اسم رب الأسرة' => 'الاسم الحقيقي للمستفيد',
            'Engineer Name' => 'N/A#',
            'اسم المهندس الميداني' => 'م. محمد الشيخ',
            'رقم هوية رب الأسرة' => '123123123',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'CLEAN1')->sole();

    expect($beneficiary->name)->toBe('الاسم الحقيقي للمستفيد')
        ->and($beneficiary->field_engineer)->toBe('م. محمد الشيخ')
        ->and($beneficiary->identity_number)->toBe('123123123');
});

test('kobo rest submission syncs HEKS path style field keys from api backfill payloads', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-followups', [
            '_uuid' => 'uuid:heks-path-style-fields',
            'group_sr3dl25/Code' => 'DGS20',
            'group_sr3dl25/Name' => 'Path Style Beneficiary',
            'group_sr3dl25/GRANT' => '10766',
            'group_bv71d05/Visit_Date' => '2026-06-28',
            'group_bv71d05/visit_' => '3.0',
            'group_bv71d05/Engineer_Name' => '_____3',
            'group_ab98d17/Working_condition' => 'work_has_been_finished_and_due_for_the_f',
            'group_ab98d17/integer_hv9hz51' => '2966',
            'group_ab98d17/Insert_BOQ' => 'Follow Up BOQ (DGS20)-1.xlsx',
            '_attachments' => [
                [
                    'filename' => 'owner/attachments/submission/Follow_Up_BOQ_DGS20-1.xlsx',
                    'download_url' => 'https://kf.kobotoolbox.org/api/v2/assets/demo/data/1/attachments/demo/',
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'DGS20')->sole();
    $followUp = HeksFollowUp::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect($beneficiary->name)->toBe('Path Style Beneficiary')
        ->and((float) $beneficiary->grant_amount)->toBe(10766.0)
        ->and($beneficiary->field_engineer)->toBeNull()
        ->and($followUp->visit_number)->toBe('3')
        ->and($followUp->visit_date->toDateString())->toBe('2026-06-28')
        ->and($followUp->working_condition)->toBe('work_has_been_finished_and_due_for_the_f')
        ->and($followUp->workingConditionLabel())->toBe('تم الانتهاء من العمل ويستحق الدفعة النهائية')
        ->and($followUp->boq_url)->toBe('https://kf.kobotoolbox.org/api/v2/assets/demo/data/1/attachments/demo/');
});

test('kobo rest submission links HEKS engineer fields to matching users', function () {
    $engineer = User::factory()->create([
        'name' => 'م مصطفى رضوان',
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-followups', [
            '_uuid' => 'uuid:heks-engineer-user-link',
            'code' => 'ENG1',
            'beneficiary_name' => 'Engineer Link Beneficiary',
            'visit_number' => '1',
            'visit_date' => '2026-06-28',
            'engineer_name' => 'م. مصطفى رضوان',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'ENG1')->sole();
    $followUp = HeksFollowUp::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect($beneficiary->field_engineer_user_id)->toBe($engineer->id)
        ->and($followUp->engineer_user_id)->toBe($engineer->id);
});

test('kobo rest submission syncs HEKS main KoBo field names from api backfill payloads', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-main-kobo-field-names',
            'identification/application_code' => 'DGS4+B',
            'identification/respondent_name' => 'Respondent Name',
            'family_info/head_name' => 'Household Head Name',
            'family_info/_003' => '405429788',
            'family_info/area_001' => 'الدرج النفق',
            'family_info/address_001' => 'شارع النفق',
            'housing_info/occupancy_type' => '56',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'DGS4+B')->sole();

    expect($beneficiary->name)->toBe('Household Head Name');
    expect($beneficiary->identity_number)->toBe('405429788')
        ->and($beneficiary->area)->toBe('الدرج النفق')
        ->and($beneficiary->address)->toBe('شارع النفق')
        ->and($beneficiary->occupancy_status)->toBe('56');
});

test('kobo rest submission stores every HEKS KoBo field in service record columns', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-main-wide-record',
            'identification/application_code' => 'WIDE1',
            'family_info/head_name' => 'Wide Record Beneficiary',
            'family_info/area_001' => 'الدرج',
            'custom_group/custom_question_two' => 'answer two',
            'very/long/kobo/field/name/that/should/be/shortened/because/mysql/column/names/have/a/limit' => 'long answer',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    expect(Schema::hasColumn('heks_main_kobo_records', 'identification_application_code'))->toBeTrue()
        ->and(Schema::hasColumn('heks_main_kobo_records', 'family_info_head_name'))->toBeTrue()
        ->and(Schema::hasColumn('heks_main_kobo_records', 'custom_group_custom_question_two'))->toBeTrue();

    $record = DB::table('heks_main_kobo_records')
        ->where('submission_uuid', 'uuid:heks-main-wide-record')
        ->first();

    expect($record->identification_application_code)->toBe('WIDE1')
        ->and($record->family_info_head_name)->toBe('Wide Record Beneficiary')
        ->and($record->custom_group_custom_question_two)->toBe('answer two')
        ->and(DB::table('heks_kobo_field_mappings')
            ->where('service_name', 'heks-main')
            ->where('kobo_field', 'very/long/kobo/field/name/that/should/be/shortened/because/mysql/column/names/have/a/limit')
            ->exists())->toBeTrue();
});

test('kobo rest submission preserves every HEKS survey answer as searchable labels', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-all-survey-answers',
            'رقم الطلب/الكود' => 'ALL1',
            'اسم رب الأسرة' => 'All Fields Beneficiary',
            'custom_group/custom_question_one' => 'answer one',
            'custom_group/custom_question_two' => 'answer two',
            'توصيات نهائية/بحاجة لاعمال تشطيبات فقط137' => 'Yes',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'ALL1')->sole();

    expect(HeksLabel::query()
        ->where('heks_beneficiary_id', $beneficiary->id)
        ->where('label_key', 'like', 'survey:%')
        ->where('label_value', 'answer one')
        ->exists())->toBeTrue()
        ->and(HeksLabel::query()
            ->where('heks_beneficiary_id', $beneficiary->id)
            ->where('label_key', 'like', 'survey:%')
            ->where('label_value', 'Yes')
            ->exists())->toBeTrue()
        ->and(data_get($beneficiary->raw_data, 'heks-main.custom_group/custom_question_two'))->toBe('answer two');
});

test('kobo rest submission syncs HEKS follow up BOQ payload', function () {
    HeksBeneficiary::query()->create([
        'code' => 'F35',
        'name' => 'Hazem Suhail',
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-followup-boq', [
            '_uuid' => 'uuid:heks-followup-boq-001',
            'code' => 'F35',
            'visit_number' => '2',
            'visit_date' => '2026-06-07',
            'engineer_name' => 'م مصطفى رضوان',
            'working_condition' => 'العمل قيد التنفيذ',
            'completed_amount_ils' => '850',
            'completion_percentage' => '8',
            'boq_items' => [
                [
                    'section' => 'اعمال البلوك',
                    'item_code' => '3.1',
                    'description' => 'توريد وبناء بلوك اسمنتي',
                    'unit' => 'M2',
                    'quantity' => '10',
                    'unit_price_ils' => '85',
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'F35')->sole();
    $followUp = HeksFollowUp::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();
    $item = HeksBoqItem::query()->where('heks_follow_up_id', $followUp->id)->sole();

    expect($followUp->visit_number)->toBe('2')
        ->and($followUp->working_condition)->toBe('العمل قيد التنفيذ')
        ->and((float) $followUp->completed_amount_ils)->toBe(850.0)
        ->and($item->description)->toBe('توريد وبناء بلوك اسمنتي')
        ->and((float) $item->quantity)->toBe(10.0)
        ->and((float) $item->unit_price_ils)->toBe(85.0)
        ->and((float) $item->total_price_ils)->toBe(850.0);
});

test('kobo rest submission builds HEKS BOQ rows from catalog quantity fields', function () {
    HeksBoqCatalogItem::query()->create([
        'section' => 'اعمال البلوك',
        'item_code' => '3.1',
        'description' => 'توريد وبناء بلوك اسمنتي',
        'unit' => 'M2',
        'unit_price_ils' => 85,
        'is_active' => true,
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-boq', [
            '_uuid' => 'uuid:heks-boq-catalog-quantity',
            'code' => 'GDQ4',
            'beneficiary_name' => 'Catalog Quantity Beneficiary',
            'boq_qty_3_1' => '12',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'GDQ4')->sole();
    $item = HeksBoqItem::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect($item->item_code)->toBe('3.1')
        ->and($item->description)->toBe('توريد وبناء بلوك اسمنتي')
        ->and((float) $item->quantity)->toBe(12.0)
        ->and((float) $item->unit_price_ils)->toBe(85.0)
        ->and((float) $item->total_price_ils)->toBe(1020.0);
});

test('kobo rest submission keeps unmapped HEKS BOQ quantity fields visible', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-boq', [
            '_uuid' => 'uuid:heks-boq-unmapped-quantity',
            'code' => 'GDQ5',
            'beneficiary_name' => 'Unmapped Quantity Beneficiary',
            'custom_boq_quantity_roof_repair' => '3',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'GDQ5')->sole();
    $item = HeksBoqItem::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect($item->description)->toContain('custom boq quantity roof repair')
        ->and((float) $item->quantity)->toBe(3.0)
        ->and((float) $item->unit_price_ils)->toBe(0.0);
});

test('heks kobo backfill imports old submissions from Kobo API', function () {
    config(['services.kobotoolbox.token' => 'api-token']);

    Http::fake([
        'https://kf.kobotoolbox.org/api/v2/assets/asset123/data/?format=json' => Http::response([
            'results' => [
                [
                    '_uuid' => 'uuid:old-heks-main',
                    '_submission_time' => '2026-06-30T08:00:00',
                    'code' => 'OLD1',
                    'beneficiary_name' => 'Old HEKS Beneficiary',
                    'social_score' => 25,
                    'technical_score' => 40,
                ],
            ],
            'next' => null,
        ]),
    ]);

    $this->artisan('heks:kobo-backfill heks-main asset123')
        ->expectsOutputToContain('HEKS Kobo backfill finished. Imported: 1, synced: 1, skipped: 0, failed: 0.')
        ->assertSuccessful();

    $beneficiary = HeksBeneficiary::query()->where('code', 'OLD1')->sole();

    expect($beneficiary->name)->toBe('Old HEKS Beneficiary')
        ->and(KoboRestSubmission::query()->where('submission_uuid', 'uuid:old-heks-main')->value('service_name'))->toBe('heks-main')
        ->and((float) HeksScore::query()->where('heks_beneficiary_id', $beneficiary->id)->value('total_score'))->toBe(65.0);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Token api-token'));
});

test('kobo rest submission updates existing uuid instead of duplicating it', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', [
            '_uuid' => 'uuid:iqrad-001',
            'borrower_name' => 'Old Name',
            'borrower_id_number' => '900000001',
        ])
        ->assertCreated();

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', [
            '_uuid' => 'uuid:iqrad-001',
            'borrower_name' => 'Updated Name',
            'borrower_id_number' => '900000002',
        ])
        ->assertOk()
        ->assertJsonPath('sync_status', 'synced');

    expect(KoboRestSubmission::query()->count())->toBe(1)
        ->and(KoboRestSubmission::query()->first()->payload['borrower_name'])->toBe('Updated Name')
        ->and(DamageAssessmentBorrower::query()->count())->toBe(1)
        ->and(DamageAssessmentBorrower::query()->first()->borrower_name)->toBe('Updated Name')
        ->and(DamageAssessmentBorrower::query()->first()->borrower_id_number)->toBe('900000002');
});

test('kobo rest submission infers borrower name from grouped kobo fields', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', [
            '_uuid' => 'uuid:grouped-name',
            'group_ak123' => [
                'name_beneficiary' => 'Grouped Borrower',
                'beneficiary_id_number' => '900000004',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $borrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid:grouped-name')->sole();

    expect($borrower->borrower_name)->toBe('Grouped Borrower')
        ->and($borrower->borrower_id_number)->toBe('900000004');
});

test('kobo rest submission uses configured borrower name field automatically', function () {
    config(['services.kobotoolbox.borrower_name_field' => 'group_ss79a79/_1']);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', [
            '_uuid' => 'uuid:configured-name-field',
            'group_ss79a79' => [
                '_1' => 'Configured Field Borrower',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    expect(DamageAssessmentBorrower::query()->where('borrower_name', 'Configured Field Borrower')->exists())->toBeTrue();
});

test('kobo rest submission uses configured borrower field map automatically', function () {
    config([
        'services.kobotoolbox.borrower_name_field' => 'group_ss79a79/_1',
        'services.kobotoolbox.borrower_field_map' => [
            'borrower_id_number' => 'group_ss79a79/_',
            'phone_primary' => 'group_ib0uw22/__007',
            'loan_total_amount' => 'group_ss9qm62/__024',
            'loan_unit_damage_status' => 'group_ss79a79/__016',
            'notes' => 'group_ib0uw22/__008',
        ],
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', [
            '_uuid' => 'uuid:configured-field-map',
            'group_ss79a79' => [
                '_1' => 'Mapped Borrower',
                '_' => '400735199',
                '__016' => 'destroyed',
            ],
            'group_ss9qm62' => [
                '__024' => '1234.50',
            ],
            'group_ib0uw22' => [
                '__007' => '0599999999',
                '__008' => 'Mapped notes',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $borrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid:configured-field-map')->sole();

    expect($borrower->borrower_name)->toBe('Mapped Borrower')
        ->and($borrower->borrower_id_number)->toBe('400735199')
        ->and($borrower->phone_primary)->toBe('0599999999')
        ->and((float) $borrower->loan_total_amount)->toBe(1234.5)
        ->and($borrower->loan_unit_damage_status)->toBe('destroyed')
        ->and($borrower->notes)->toBe('Mapped notes');
});

test('kobo rest submission stores raw payload and skips borrower sync when borrower name is missing', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', [
            '_uuid' => 'uuid:missing-name',
            'borrower_id_number' => '900000003',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'skipped');

    expect(KoboRestSubmission::query()->first()->sync_error)->toBe('Kobo submission does not include borrower_name.')
        ->and(DamageAssessmentBorrower::query()->count())->toBe(0);
});

test('kobo sync command retries skipped submissions', function () {
    KoboRestSubmission::query()->create([
        'service_name' => 'iqrad',
        'submission_uuid' => 'uuid:retry-skipped',
        'payload' => [
            '_uuid' => 'uuid:retry-skipped',
            'group_ak123' => [
                'beneficiary_full_name' => 'Retried Borrower',
            ],
        ],
        'sync_status' => 'skipped',
        'sync_error' => 'Kobo submission does not include borrower_name.',
        'received_at' => now(),
    ]);

    $this->artisan('kobo:sync-rest-submissions')
        ->expectsOutputToContain('Synced: 1')
        ->assertSuccessful();

    expect(KoboRestSubmission::query()->first()->sync_status)->toBe('synced')
        ->and(DamageAssessmentBorrower::query()->where('borrower_name', 'Retried Borrower')->exists())->toBeTrue();
});

test('kobo sync command replays synced HEKS submissions into wide record columns', function () {
    KoboRestSubmission::query()->create([
        'service_name' => 'heks-main',
        'submission_uuid' => 'uuid:replay-heks-wide-record',
        'payload' => [
            '_uuid' => 'uuid:replay-heks-wide-record',
            'identification/application_code' => 'REPLAY1',
            'family_info/head_name' => 'Replay Heks Beneficiary',
            'custom_group/custom_question' => 'replayed answer',
        ],
        'sync_status' => 'synced',
        'received_at' => now(),
    ]);

    $this->artisan('kobo:sync-rest-submissions --all')
        ->expectsOutputToContain('Synced: 1')
        ->assertSuccessful();

    $record = DB::table('heks_main_kobo_records')
        ->where('submission_uuid', 'uuid:replay-heks-wide-record')
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->identification_application_code)->toBe('REPLAY1')
        ->and($record->custom_group_custom_question)->toBe('replayed answer');
});

test('kobo sync command can use an explicit borrower name field', function () {
    KoboRestSubmission::query()->create([
        'service_name' => 'iqrad',
        'submission_uuid' => 'uuid:explicit-field',
        'payload' => [
            '_uuid' => 'uuid:explicit-field',
            'group_ak123' => [
                'q1' => 'Explicit Field Borrower',
            ],
        ],
        'sync_status' => 'skipped',
        'sync_error' => 'Kobo submission does not include borrower_name.',
        'received_at' => now(),
    ]);

    $this->artisan('kobo:sync-rest-submissions --borrower-name-field=group_ak123/q1')
        ->expectsOutputToContain('Synced: 1')
        ->assertSuccessful();

    expect(KoboRestSubmission::query()->first()->sync_status)->toBe('synced')
        ->and(DamageAssessmentBorrower::query()->where('borrower_name', 'Explicit Field Borrower')->exists())->toBeTrue();
});

test('kobo sync command can use an explicit field map json', function () {
    KoboRestSubmission::query()->create([
        'service_name' => 'iqrad',
        'submission_uuid' => 'uuid:explicit-map',
        'payload' => [
            '_uuid' => 'uuid:explicit-map',
            'group_ak123' => [
                'q1' => 'Explicit Map Borrower',
                'q2' => '0555555555',
            ],
        ],
        'sync_status' => 'skipped',
        'sync_error' => 'Kobo submission does not include borrower_name.',
        'received_at' => now(),
    ]);

    $fieldMap = json_encode(['phone_primary' => 'group_ak123/q2'], JSON_THROW_ON_ERROR);

    $this->artisan("kobo:sync-rest-submissions --borrower-name-field=group_ak123/q1 --field-map='{$fieldMap}'")
        ->expectsOutputToContain('Synced: 1')
        ->assertSuccessful();

    $borrower = DamageAssessmentBorrower::query()->where('borrower_name', 'Explicit Map Borrower')->sole();

    expect($borrower->phone_primary)->toBe('0555555555');
});
