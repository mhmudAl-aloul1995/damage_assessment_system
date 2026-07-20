<?php

use App\Models\KoboRestSubmission;
use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerBoqCatalogItem;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqCatalogItem;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksKoboChoice;
use App\Modules\Heks\Models\HeksKoboFieldMapping;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksScore;
use App\Modules\Heks\Models\HeksScoringWeight;
use App\Modules\Heks\Services\HeksKoboSubmissionSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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
        ->and($borrower->koboAnswers()->where('field_key', 'borrower_name')->value('value'))->toBe('Mona Saleh')
        ->and($borrower->koboAnswers()->where('field_key', 'employment_status')->value('value'))->toBe('not_working')
        ->and($borrower->koboAnswers()->where('field_key', '_uuid')->exists())->toBeFalse()
        ->and($borrower->attachments()->first()->url)->toBe('https://kf.example.test/media/damage.jpg');
});

test('kobo rest submission syncs borrower boq quantities from payload', function () {
    BorrowerBoqCatalogItem::query()->create([
        'item_code' => '1.1',
        'description' => 'Repair concrete item',
        'normalized_description' => 'repair concrete item',
        'unit' => 'M2',
        'unit_price' => 10,
        'unit_price_ils' => 35,
        'sort_order' => 1,
    ]);

    $payload = [
        '_uuid' => 'uuid:iqrad-boq-001',
        'borrower_name' => 'BOQ Borrower',
        'borrower_id_number' => '900000099',
        'boq_quantities' => [
            ['source_column' => 'Repair concrete item (M2)', 'quantity' => 2, 'sort_order' => 1],
        ],
    ];

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', $payload)
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $borrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid:iqrad-boq-001')->sole();

    expect($borrower->boqItems()->count())->toBe(1)
        ->and((float) $borrower->boqItems()->first()->quantity)->toBe(2.0)
        ->and((float) $borrower->refresh()->boq_total_usd)->toBe(20.0)
        ->and((float) $borrower->boq_total_ils)->toBe(70.0);

    $payload['boq_quantities'][0]['quantity'] = 3;

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', $payload)
        ->assertOk()
        ->assertJsonPath('sync_status', 'synced');

    expect($borrower->boqItems()->count())->toBe(1)
        ->and((float) $borrower->boqItems()->first()->refresh()->quantity)->toBe(3.0)
        ->and((float) $borrower->refresh()->boq_total_usd)->toBe(30.0)
        ->and((float) $borrower->boq_total_ils)->toBe(105.0);
});

test('kobo rest submission syncs borrower boq quantities from configured kobo group', function () {
    BorrowerBoqCatalogItem::query()->create([
        'item_code' => '1.1',
        'description' => 'First catalog item',
        'normalized_description' => 'first catalog item',
        'unit' => 'M2',
        'unit_price' => 10,
        'unit_price_ils' => 35,
        'sort_order' => 1,
    ]);
    BorrowerBoqCatalogItem::query()->create([
        'item_code' => '5',
        'description' => '0000900107',
        'normalized_description' => '0000900107',
        'unit' => '904691094',
        'unit_price' => 21024,
        'unit_price_ils' => 731635.20,
        'sort_order' => 2,
    ]);
    BorrowerBoqCatalogItem::query()->create([
        'item_code' => '1.2',
        'description' => 'Second catalog item',
        'normalized_description' => 'second catalog item',
        'unit' => 'M3',
        'unit_price' => 20,
        'unit_price_ils' => 70,
        'sort_order' => 2,
    ]);
    BorrowerBoqCatalogItem::query()->create([
        'item_code' => '1.3',
        'description' => 'Third catalog item',
        'normalized_description' => 'third catalog item',
        'unit' => 'M2',
        'unit_price' => 30,
        'unit_price_ils' => 105,
        'sort_order' => 3,
    ]);

    $payload = [
        '_uuid' => 'uuid:iqrad-group-boq-001',
        'borrower_name' => 'Grouped BOQ Borrower',
        'group_fj89d65' => [
            '_2_002' => 2,
            '_B250_2' => 5,
        ],
    ];

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', $payload)
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $borrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid:iqrad-group-boq-001')->sole();
    $borrower->boqItems()->create([
        'catalog_item_id' => null,
        'source_column' => 'group_fj89d65/_old_bad_field',
        'source_key' => sha1('group_fj89d65/_old_bad_field'),
        'item_code' => '5',
        'description' => '0000900107',
        'unit' => '904691094',
        'unit_price' => 21024,
        'exchange_rate' => 3.5,
        'unit_price_ils' => 73584,
        'quantity' => 15,
        'total_price' => 315360,
        'total_price_ils' => 1103760,
        'sort_order' => 5,
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', $payload)
        ->assertOk()
        ->assertJsonPath('sync_status', 'synced');

    $items = $borrower->boqItems()->orderBy('sort_order')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->source_column)->toBe('group_fj89d65/_2_002')
        ->and($items[0]->source_key)->toBe(sha1('First catalog item'))
        ->and($items[0]->description)->toBe('First catalog item')
        ->and((float) $items[0]->quantity)->toBe(2.0)
        ->and($items[1]->source_column)->toBe('group_fj89d65/_B250_2')
        ->and($items[1]->source_key)->toBe(sha1('Third catalog item'))
        ->and($items[1]->description)->toBe('Third catalog item')
        ->and((float) $items[1]->quantity)->toBe(5.0)
        ->and((float) $borrower->refresh()->boq_total_usd)->toBe(170.0)
        ->and((float) $borrower->boq_total_ils)->toBe(595.0);
});

test('kobo asset submissions can be fetched into stored rest submissions', function () {
    config(['services.kobotoolbox.token' => 'api-token']);

    Http::fake([
        'https://kf.example.test/api/v2/assets/asset123/data/*' => Http::sequence()
            ->push([
                'results' => [
                    ['_uuid' => 'uuid:iqrad-fetch-001', '_id' => 1, 'borrower_name' => 'First Borrower'],
                ],
                'next' => 'https://kf.example.test/api/v2/assets/asset123/data/?page=2',
            ])
            ->push([
                'results' => [
                    ['_uuid' => 'uuid:iqrad-fetch-002', '_id' => 2, 'borrower_name' => 'Second Borrower'],
                ],
                'next' => null,
            ]),
    ]);

    $this->artisan('kobo:fetch-asset-submissions', [
        'asset_uid' => 'asset123',
        '--service' => 'iqrad',
        '--base-url' => 'https://kf.example.test',
    ])->assertSuccessful();

    expect(KoboRestSubmission::query()->where('service_name', 'iqrad')->count())->toBe(2)
        ->and(KoboRestSubmission::query()->where('submission_uuid', 'uuid:iqrad-fetch-001')->value('sync_status'))->toBe('pending');
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

test('kobo rest submission calculates HEKS scores from scoring weights when totals are missing', function () {
    HeksScoringWeight::query()->create([
        'source' => 'S-V',
        'question_key' => 'social_need',
        'option_value' => 'vulnerable',
        'option_score' => 10,
    ]);
    HeksScoringWeight::query()->create([
        'source' => 'T-V',
        'question_key' => 'damage_level',
        'option_value' => 'moderate',
        'option_score' => 42,
    ]);

    $submission = KoboRestSubmission::query()->create([
        'service_name' => 'heks-main',
        'submission_uuid' => 'uuid:heks-calculated-score',
        'payload' => [
            '_uuid' => 'uuid:heks-calculated-score',
            'code' => 'CALC1',
            'beneficiary_name' => 'Calculated Score Beneficiary',
            'social_need' => 'vulnerable',
            'damage_level' => 'moderate',
        ],
        'received_at' => now(),
    ]);

    $sync = app(HeksKoboSubmissionSyncService::class)->sync($submission);

    expect($sync['status'])->toBe('synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'CALC1')->sole();
    $score = HeksScore::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect((float) $score->social_score)->toBe(10.0)
        ->and((float) $score->technical_score)->toBe(42.0)
        ->and((float) $score->total_score)->toBe(52.0)
        ->and($score->classification)->toBe('High')
        ->and($score->raw_data['_heks_score_calculation']['social_weights'])->not->toBeEmpty()
        ->and($score->raw_data['_heks_score_calculation']['technical_weights'])->not->toBeEmpty();
});

test('kobo rest submission does not total incomplete HEKS score components', function () {
    HeksScoringWeight::query()->create([
        'source' => 'T-V',
        'question_key' => 'damage_level',
        'option_value' => 'moderate',
        'option_score' => 27,
    ]);

    $submission = KoboRestSubmission::query()->create([
        'service_name' => 'heks-boq',
        'submission_uuid' => 'uuid:heks-incomplete-score',
        'payload' => [
            '_uuid' => 'uuid:heks-incomplete-score',
            'code' => 'PARTIAL1',
            'beneficiary_name' => 'Partial Score Beneficiary',
            'damage_level' => 'moderate',
        ],
        'received_at' => now(),
    ]);

    $sync = app(HeksKoboSubmissionSyncService::class)->sync($submission);

    expect($sync['status'])->toBe('synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'PARTIAL1')->sole();
    $score = HeksScore::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect($score->social_score)->toBeNull()
        ->and((float) $score->technical_score)->toBe(27.0)
        ->and($score->total_score)->toBeNull()
        ->and($score->classification)->toBe('');
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

test('kobo rest submission syncs current HEKS main technical contact fields', function () {
    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-main-current-technical-fields',
            'identification/application_code' => 'GDE7',
            'identification/q_093' => 'Engineer Name',
            'identification/q_103' => '0599465783',
            'family_info/head_name' => 'Current Technical Beneficiary',
            'family_info/_003' => '906762851',
            'family_info/q_095' => '0599465784',
            'family_info/area_001' => 'Beach Camp',
            'family_info/address_001' => 'West of mosque',
            'housing_info/occupancy_type' => '________________',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'GDE7')->sole();

    expect($beneficiary->name)->toBe('Current Technical Beneficiary')
        ->and($beneficiary->identity_number)->toBe('906762851')
        ->and($beneficiary->phone)->toBe('0599465783')
        ->and($beneficiary->field_engineer)->toBe('Engineer Name')
        ->and($beneficiary->area)->toBe('Beach Camp')
        ->and($beneficiary->address)->toBe('West of mosque')
        ->and($beneficiary->occupancy_status)->toBeNull();
});

test('heks kobo sync resolves field engineer choice codes from mapping', function () {
    foreach ([
        ['2', 'م. محمد الشيخ', 2],
        ['3', 'آخرون /حدد:', 3],
    ] as [$choiceName, $choiceLabel, $order]) {
        HeksKoboChoice::query()->create([
            'service_name' => 'heks-main',
            'question_key' => 'identification/q_087',
            'list_name' => 'kx0yx98',
            'choice_name' => $choiceName,
            'choice_label' => $choiceLabel,
            'language' => 'ar',
            'sort_order' => $order,
            'is_active' => true,
        ]);
    }

    $selectedSubmission = KoboRestSubmission::query()->create([
        'service_name' => 'heks-main',
        'submission_uuid' => 'uuid:heks-engineer-choice-code',
        'payload' => [
            '_uuid' => 'uuid:heks-engineer-choice-code',
            'identification/application_code' => 'ENG-CODE-1',
            'family_info/head_name' => 'Engineer Choice Beneficiary',
            'identification/q_087' => '2',
        ],
        'received_at' => now(),
    ]);

    app(HeksKoboSubmissionSyncService::class)->sync($selectedSubmission);

    $otherSubmission = KoboRestSubmission::query()->create([
        'service_name' => 'heks-main',
        'submission_uuid' => 'uuid:heks-engineer-other-choice',
        'payload' => [
            '_uuid' => 'uuid:heks-engineer-other-choice',
            'identification/application_code' => 'ENG-CODE-2',
            'family_info/head_name' => 'Engineer Other Beneficiary',
            'identification/q_087' => '3',
            'identification/q_093' => 'م. مهندس آخر',
        ],
        'received_at' => now(),
    ]);

    app(HeksKoboSubmissionSyncService::class)->sync($otherSubmission);

    expect(HeksBeneficiary::query()->where('code', 'ENG-CODE-1')->value('field_engineer'))
        ->toBe('م. محمد الشيخ')
        ->and(HeksBeneficiary::query()->where('code', 'ENG-CODE-2')->value('field_engineer'))
        ->toBe('م. مهندس آخر');
});

test('kobo rest submission applies configured HEKS Kobo display labels to technical fields', function () {
    HeksKoboFieldMapping::query()->create([
        'service_name' => 'heks-main',
        'table_name' => 'heks_main_kobo_records',
        'kobo_field' => 'q_001',
        'column_name' => 'q_001',
        'display_label' => 'اسم رب الأسرة',
    ]);
    HeksKoboFieldMapping::query()->create([
        'service_name' => 'heks-main',
        'table_name' => 'heks_main_kobo_records',
        'kobo_field' => 'q_002',
        'column_name' => 'q_002',
        'display_label' => 'رقم هوية رب الأسرة',
    ]);
    HeksKoboFieldMapping::query()->create([
        'service_name' => 'heks-main',
        'table_name' => 'heks_main_kobo_records',
        'kobo_field' => 'q_087',
        'column_name' => 'q_087',
        'display_label' => 'اسم المهندس الميداني',
    ]);
    HeksKoboFieldMapping::query()->create([
        'service_name' => 'heks-main',
        'table_name' => 'heks_main_kobo_records',
        'kobo_field' => 'q_092',
        'column_name' => 'q_092',
        'display_label' => 'تقييم حالة ضرر المأوى:',
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-main-technical-field-labels',
            'application_code' => 'DGN1',
            'q_001' => 'Technical Field Beneficiary',
            'q_002' => '987654321',
            'q_087' => 'م. نعيم شاهين',
            'q_092' => 'أضرار جزئية متوسطة',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'DGN1')->sole();

    expect($beneficiary->name)->toBe('Technical Field Beneficiary')
        ->and($beneficiary->identity_number)->toBe('987654321')
        ->and($beneficiary->field_engineer)->toBe('م. نعيم شاهين')
        ->and($beneficiary->damage_status)->toBe('أضرار جزئية متوسطة')
        ->and(Schema::hasColumn('heks_main_kobo_records', 'q_087'))->toBeTrue()
        ->and(HeksLabel::query()
            ->where('heks_beneficiary_id', $beneficiary->id)
            ->where('label_key', 'like', 'survey:%اسم المهندس الميداني')
            ->where('label_value', 'م. نعيم شاهين')
            ->exists())->toBeTrue();
});

test('heks kobo field label import command builds mappings from paired exports', function () {
    $directory = storage_path('framework/testing/heks-kobo-labels');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $technicalPath = $directory.'/technical.xlsx';
    $labelsPath = $directory.'/labels.xlsx';

    $technicalWorkbook = new Spreadsheet;
    $technicalWorkbook->getActiveSheet()
        ->setTitle('Heks Final V1')
        ->fromArray(['application_code', 'q_001', 'q_087'], null, 'A1');
    IOFactory::createWriter($technicalWorkbook, 'Xlsx')->save($technicalPath);
    $technicalWorkbook->disconnectWorksheets();

    $labelsWorkbook = new Spreadsheet;
    $labelsWorkbook->getActiveSheet()
        ->setTitle('Heks Final V1')
        ->fromArray(['رقم الطلب/الكود', 'اسم رب الأسرة', 'اسم المهندس الميداني'], null, 'A1');
    IOFactory::createWriter($labelsWorkbook, 'Xlsx')->save($labelsPath);
    $labelsWorkbook->disconnectWorksheets();

    $this->artisan('heks:kobo-import-field-labels', [
        'service' => 'heks-main',
        'technical_file' => $technicalPath,
        'labels_file' => $labelsPath,
    ])->assertSuccessful();

    expect(HeksKoboFieldMapping::query()
        ->where('service_name', 'heks-main')
        ->where('kobo_field', 'q_087')
        ->value('display_label'))->toBe('اسم المهندس الميداني');

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-main-imported-label-map',
            'application_code' => 'MAP1',
            'q_001' => 'Mapped Export Beneficiary',
            'q_087' => 'م. نعيم شاهين',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'MAP1')->sole();

    expect($beneficiary->name)->toBe('Mapped Export Beneficiary')
        ->and($beneficiary->field_engineer)->toBe('م. نعيم شاهين');
});

test('heks kobo form mapping import command builds mappings from Kobo API', function () {
    config(['services.kobotoolbox.token' => 'api-token']);

    Http::fake([
        'https://kf.kobotoolbox.org/api/v2/assets/asset123/?format=json' => Http::response([
            'content' => [
                'survey' => [
                    [
                        'type' => 'begin_group',
                        'name' => 'family_info',
                        'label' => ['Arabic' => 'تقييم الهشاشة الاجتماعية'],
                    ],
                    [
                        'type' => 'text',
                        'name' => 'head_name',
                        'label' => ['Arabic' => 'اسم رب الأسرة'],
                    ],
                    [
                        'type' => 'integer',
                        'name' => 'Age',
                        'label' => ['Arabic' => 'العمر'],
                    ],
                    [
                        'type' => 'select_one yes_no',
                        'name' => 'has_disability',
                        'label' => ['Arabic' => 'يوجد أشخاص ذوي إعاقة'],
                    ],
                    [
                        'type' => 'end_group',
                    ],
                    [
                        'type' => 'note',
                        'name' => 'readonly_note',
                        'label' => ['Arabic' => 'ملاحظة فقط'],
                    ],
                ],
                'choices' => [
                    [
                        'list_name' => 'yes_no',
                        'name' => 'yes',
                        'label' => ['Arabic' => 'نعم'],
                    ],
                    [
                        'list_name' => 'yes_no',
                        'name' => 'no',
                        'label' => ['Arabic' => 'لا'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->artisan('heks:kobo-import-form-mapping', [
        'service' => 'heks-main',
        'asset' => 'asset123',
    ])
        ->expectsOutputToContain('HEKS Kobo form mapping imported. Created: 3, updated: 0, skipped: 1.')
        ->expectsOutputToContain('HEKS Kobo choices synced. Select one: 1, select multiple: 0, choices: 2, inactive: 0.')
        ->assertSuccessful();

    expect(HeksKoboFieldMapping::query()
        ->where('service_name', 'heks-main')
        ->where('kobo_field', 'family_info/head_name')
        ->value('display_label'))->toBe('اسم رب الأسرة')
        ->and(HeksKoboFieldMapping::query()
            ->where('service_name', 'heks-main')
            ->where('kobo_field', 'head_name')
            ->exists())->toBeFalse()
        ->and(HeksKoboFieldMapping::query()
            ->where('service_name', 'heks-main')
            ->where('kobo_field', 'family_info/Age')
            ->value('data_type'))->toBe('integer')
        ->and(HeksKoboFieldMapping::query()
            ->where('service_name', 'heks-main')
            ->where('kobo_field', 'family_info/has_disability')
            ->value('field_type'))->toBe('select_one')
        ->and(HeksKoboFieldMapping::query()
            ->where('service_name', 'heks-main')
            ->where('kobo_field', 'family_info/has_disability')
            ->value('list_name'))->toBe('yes_no')
        ->and(HeksKoboFieldMapping::query()
            ->where('service_name', 'heks-main')
            ->where('kobo_field', 'readonly_note')
            ->exists())->toBeFalse();

    $choiceNotes = json_decode((string) HeksKoboFieldMapping::query()
        ->where('service_name', 'heks-main')
        ->where('kobo_field', 'family_info/has_disability')
        ->value('notes'), true);

    expect($choiceNotes['choice_labels'] ?? [])->toBe([
        'yes' => 'نعم',
        'no' => 'لا',
    ])
        ->and($choiceNotes['form_order'] ?? null)->toBe(4)
        ->and(HeksKoboChoice::query()
            ->where('service_name', 'heks-main')
            ->where('question_key', 'family_info/has_disability')
            ->where('choice_name', 'yes')
            ->value('choice_label'))->toBe('نعم');
});

test('heks kobo form mapping import command builds mappings from a local xlsform', function () {
    $workbook = new Spreadsheet;
    $survey = $workbook->getActiveSheet();
    $survey->setTitle('survey');
    $survey->fromArray([
        ['type', 'name', 'label::Arabic'],
        ['begin_group', 'family_info', 'Family information'],
        ['select_one yes_no', 'has_disability', 'Has disability'],
        ['end_group', null, null],
    ]);

    $choices = $workbook->createSheet();
    $choices->setTitle('choices');
    $choices->fromArray([
        ['list_name', 'name', 'label::Arabic'],
        ['yes_no', 'yes', 'Yes'],
        ['yes_no', 'no', 'No'],
    ]);

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'heks-xlsform-'.\Illuminate\Support\Str::random(8).'.xlsx';
    IOFactory::createWriter($workbook, 'Xlsx')->save($path);

    $this->artisan('heks:kobo-import-form-mapping', [
        'service' => 'heks-main',
        '--xlsform' => $path,
    ])
        ->expectsOutputToContain('HEKS Kobo form mapping imported. Created: 1, updated: 0, skipped: 0.')
        ->expectsOutputToContain('HEKS Kobo choices synced. Select one: 1, select multiple: 0, choices: 2, inactive: 0.')
        ->assertSuccessful();

    expect(HeksKoboFieldMapping::query()
        ->where('service_name', 'heks-main')
        ->where('kobo_field', 'family_info/has_disability')
        ->value('list_name'))->toBe('yes_no')
        ->and(HeksKoboChoice::query()
            ->where('service_name', 'heks-main')
            ->where('question_key', 'family_info/has_disability')
            ->where('choice_name', 'yes')
            ->value('choice_label'))->toBe('Yes');
});

test('heks kobo field label import command imports observed select choices', function () {
    HeksKoboFieldMapping::query()->create([
        'service_name' => 'heks-main',
        'table_name' => 'heks_main_kobo_records',
        'kobo_field' => 'marital_status',
        'column_name' => 'marital_status',
        'display_label' => 'Marital status',
        'data_type' => 'select_one',
    ]);

    HeksKoboFieldMapping::query()->create([
        'service_name' => 'heks-main',
        'table_name' => 'heks_main_kobo_records',
        'kobo_field' => 'q_092',
        'column_name' => 'q_092',
        'display_label' => 'Safety issues',
        'data_type' => 'select_multiple',
    ]);

    $technicalWorkbook = new Spreadsheet;
    $technicalSheet = $technicalWorkbook->getActiveSheet();
    $technicalSheet->setTitle('Heks Final V1');
    $technicalSheet->fromArray([
        ['marital_status', 'q_092', 'q_092/9'],
        ['18', '9', '1'],
        ['19', '9 10', '0'],
    ]);

    $labelsWorkbook = new Spreadsheet;
    $labelsSheet = $labelsWorkbook->getActiveSheet();
    $labelsSheet->setTitle('Heks Final V1');
    $labelsSheet->fromArray([
        ['Marital status', 'Safety issues', 'Safety issues/No issue'],
        ['Married', 'No issue', '1'],
        ['Single', 'No issue Legal issue', '0'],
    ]);

    $technicalPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'heks-technical-'.\Illuminate\Support\Str::random(8).'.xlsx';
    $labelsPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'heks-labels-'.\Illuminate\Support\Str::random(8).'.xlsx';
    IOFactory::createWriter($technicalWorkbook, 'Xlsx')->save($technicalPath);
    IOFactory::createWriter($labelsWorkbook, 'Xlsx')->save($labelsPath);

    $this->artisan('heks:kobo-import-field-labels', [
        'service' => 'heks-main',
        'technical_file' => $technicalPath,
        'labels_file' => $labelsPath,
    ])
        ->expectsOutputToContain('HEKS Kobo choices imported from paired exports: 3.')
        ->assertSuccessful();

    expect(HeksKoboChoice::query()
        ->where('question_key', 'marital_status')
        ->pluck('choice_label', 'choice_name')
        ->all())->toBe([
            18 => 'Married',
            19 => 'Single',
        ])
        ->and(HeksKoboChoice::query()
            ->where('question_key', 'q_092')
            ->pluck('choice_label', 'choice_name')
            ->all())->toBe([
                9 => 'No issue',
            ])
        ->and(HeksKoboChoice::query()
            ->where('question_key', 'q_092')
            ->where('choice_name', '9 10')
            ->exists())->toBeFalse();
});

test('heks kobo form choice sync keeps observed choices active', function () {
    HeksKoboChoice::query()->create([
        'service_name' => 'heks-main',
        'question_key' => 'q_059',
        'list_name' => 'damage_status',
        'choice_name' => '_____1',
        'choice_label' => 'أضرار جزئية طفيفة',
        'language' => 'ar',
        'sort_order' => 1,
        'is_active' => true,
        'raw_data' => null,
    ]);

    HeksKoboChoice::query()->create([
        'service_name' => 'heks-main',
        'question_key' => 'q_059',
        'list_name' => 'damage_status',
        'choice_name' => 'old-form-choice',
        'choice_label' => 'Old form choice',
        'language' => 'ar',
        'sort_order' => 2,
        'is_active' => true,
        'raw_data' => ['name' => 'old-form-choice'],
    ]);

    app(\App\Modules\Heks\Services\HeksKoboChoiceSyncService::class)->sync(
        'heks-main',
        [
            ['type' => 'select_one damage_status', 'name' => 'q_059', 'label' => ['Arabic' => 'Damage status']],
        ],
        [
            ['list_name' => 'damage_status', 'name' => 'new-form-choice', 'label' => ['Arabic' => 'New form choice']],
        ],
        dryRun: false
    );

    expect(HeksKoboChoice::query()
        ->where('service_name', 'heks-main')
        ->where('question_key', 'q_059')
        ->where('choice_name', '_____1')
        ->value('is_active'))->toBeTrue()
        ->and(HeksKoboChoice::query()
            ->where('service_name', 'heks-main')
            ->where('question_key', 'q_059')
            ->where('choice_name', 'old-form-choice')
            ->value('is_active'))->toBeFalse()
        ->and(HeksKoboChoice::query()
            ->where('service_name', 'heks-main')
            ->where('question_key', 'q_059')
            ->where('choice_name', 'new-form-choice')
            ->value('is_active'))->toBeTrue();
});

test('heks kobo sync fills basic beneficiary fields from imported Kobo labels', function () {
    foreach ([
        'q_gov' => 'المحافظة',
        'q_displacement' => 'حالة النزوح حاليا للأسرة',
        'q_occupancy' => 'حالة الإشغال الحالي للوحدة السكنية',
        'q_damage' => 'تقييم حالة ضرر المأوى',
        'q_grant' => 'المنحة',
        'q_payment_30' => 'دفعة 30%',
        'q_recommendation' => 'توصيات نهائية',
    ] as $field => $label) {
        HeksKoboFieldMapping::query()->create([
            'service_name' => 'heks-main',
            'table_name' => 'heks_main_kobo_records',
            'kobo_field' => $field,
            'column_name' => $field,
            'display_label' => $label,
        ]);
    }

    $submission = KoboRestSubmission::query()->create([
        'service_name' => 'heks-main',
        'submission_uuid' => 'uuid:heks-basic-label-fields',
        'payload' => [
            'application_code' => 'BASIC-LABELS',
            'head_name' => 'Basic Labels Beneficiary',
            'q_gov' => 'غزة',
            'q_displacement' => 'نازح',
            'q_occupancy' => 'مأهولة من قبل الأسرة المالكة',
            'q_damage' => 'أضرار جزئية متوسطة',
            'q_grant' => '1200.50',
            'q_payment_30' => '360.15',
            'q_recommendation' => 'يحتاج إلى تدخل طارئ',
        ],
        'sync_status' => 'pending',
    ]);

    app(HeksKoboSubmissionSyncService::class)->sync($submission);

    $beneficiary = HeksBeneficiary::query()->where('code', 'BASIC-LABELS')->sole();

    expect($beneficiary->governorate)->toBe('غزة')
        ->and($beneficiary->displacement_status)->toBe('نازح')
        ->and($beneficiary->occupancy_status)->toBe('مأهولة من قبل الأسرة المالكة')
        ->and($beneficiary->damage_status)->toBe('أضرار جزئية متوسطة')
        ->and((float) $beneficiary->grant_amount)->toBe(1200.5)
        ->and((float) $beneficiary->payment_1)->toBe(360.15)
        ->and($beneficiary->recommendations)->toBe('يحتاج إلى تدخل طارئ');
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

test('kobo rest submission keeps all HEKS BOQ quantity fields when only some match the catalog', function () {
    HeksBoqCatalogItem::query()->create([
        'section' => 'Block work',
        'item_code' => '3.2',
        'description' => 'Concrete block 15 cm',
        'unit' => 'M2',
        'unit_price_ils' => 585,
        'is_active' => true,
    ]);

    HeksKoboFieldMapping::query()->create([
        'service_name' => 'heks-boq',
        'table_name' => 'heks_boq_kobo_records',
        'kobo_field' => 'sec_03/qty_3_2',
        'column_name' => 'sec_03_qty_3_2',
        'display_label' => 'الكمية - البند 3.2',
        'data_type' => 'decimal',
        'field_type' => 'decimal',
    ]);

    HeksKoboFieldMapping::query()->create([
        'service_name' => 'heks-boq',
        'table_name' => 'heks_boq_kobo_records',
        'kobo_field' => 'sec_08/qty_8_12_001',
        'column_name' => 'sec_08_qty_8_12_001',
        'display_label' => 'الكمية - البند 8.13',
        'data_type' => 'decimal',
        'field_type' => 'decimal',
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-boq', [
            '_uuid' => 'uuid:heks-boq-mixed-catalog',
            'meta_group/Code' => 'MIXEDBOQ',
            'sec_03/qty_3_2' => '1',
            'sec_08/qty_8_12_001' => '4',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'MIXEDBOQ')->sole();
    $items = HeksBoqItem::query()
        ->where('heks_beneficiary_id', $beneficiary->id)
        ->orderBy('item_code')
        ->get()
        ->keyBy('item_code');

    expect($items)->toHaveCount(2)
        ->and((float) $items->get('3.2')->quantity)->toBe(1.0)
        ->and((float) $items->get('3.2')->total_price_ils)->toBe(585.0)
        ->and((float) $items->get('8.13')->quantity)->toBe(4.0)
        ->and((float) $items->get('8.13')->total_price_ils)->toBe(0.0)
        ->and($items->get('8.13')->description)->toBe('الكمية - البند 8.13');
});

test('kobo rest submission does not collapse BOQ quantity fields into one noisy description row', function () {
    HeksBoqCatalogItem::query()->create([
        'section' => 'Block work',
        'item_code' => '3.2',
        'description' => 'Concrete block 15 cm',
        'unit' => 'M2',
        'unit_price_ils' => 585,
        'is_active' => true,
    ]);

    $submission = KoboRestSubmission::query()->create([
        'service_name' => 'heks-boq',
        'submission_uuid' => 'uuid:heks-boq-description-noise',
        'payload' => [
            '_uuid' => 'uuid:heks-boq-description-noise',
            'meta_group/Code' => 'NOISEBOQ',
            'description' => '3',
            'sec_01/qty_1_1' => '3',
            'sec_03/qty_3_2' => '1',
        ],
        'received_at' => now(),
    ]);

    $sync = app(HeksKoboSubmissionSyncService::class)->sync($submission);

    expect($sync['status'])->toBe('synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'NOISEBOQ')->sole();
    $items = HeksBoqItem::query()
        ->where('heks_beneficiary_id', $beneficiary->id)
        ->orderBy('item_code')
        ->get()
        ->keyBy('item_code');

    expect($items)->toHaveCount(2)
        ->and($items->has('1.1'))->toBeTrue()
        ->and((float) $items->get('1.1')->quantity)->toBe(3.0)
        ->and($items->has('3.2'))->toBeTrue()
        ->and((float) $items->get('3.2')->quantity)->toBe(1.0)
        ->and(HeksBoqItem::query()
            ->where('heks_beneficiary_id', $beneficiary->id)
            ->whereNull('item_code')
            ->where('description', '3')
            ->exists())->toBeFalse();
});

test('kobo rest submission builds HEKS main BOQ rows from technical item code fields', function () {
    HeksBoqCatalogItem::query()->create([
        'section' => 'Block work',
        'item_code' => '3.1',
        'description' => 'Concrete block work',
        'unit' => 'M2',
        'unit_price_ils' => 85,
        'is_active' => true,
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/heks-main', [
            '_uuid' => 'uuid:heks-main-technical-boq',
            'identification/application_code' => 'MAINBOQ1',
            'family_info/head_name' => 'Main BOQ Beneficiary',
            'group_fj7vq52/group_op9xp69/_3_1' => '__2',
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $beneficiary = HeksBeneficiary::query()->where('code', 'MAINBOQ1')->sole();
    $item = HeksBoqItem::query()->where('heks_beneficiary_id', $beneficiary->id)->sole();

    expect($item->source)->toBe('heks-main')
        ->and($item->item_code)->toBe('3.1')
        ->and($item->description)->toBe('Concrete block work')
        ->and((float) $item->quantity)->toBe(2.0)
        ->and((float) $item->total_price_ils)->toBe(170.0);
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

test('kobo rest submission falls back to form number when borrower identity differs', function () {
    $borrower = DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB303',
        'borrower_name' => 'Approved Borrower',
        'borrower_id_number' => '922435243',
        'is_borrower_alive' => true,
    ]);

    $this
        ->withHeader('X-Kobo-Token', 'test-kobo-token')
        ->postJson('/api/kobo/iqrad', [
            '_uuid' => 'uuid:form-number-fallback',
            'رقم الاستمارة ' => 'IDB 303',
            'borrower_name' => 'Visited Borrower',
            'borrower_id_number' => '922435423',
            'group_lv9gw32' => ['__007' => '1'],
        ])
        ->assertCreated()
        ->assertJsonPath('sync_status', 'synced');

    $submission = KoboRestSubmission::query()->where('submission_uuid', 'uuid:form-number-fallback')->sole();

    expect(DamageAssessmentBorrower::query()->count())->toBe(1)
        ->and($submission->damage_assessment_borrower_id)->toBe($borrower->id)
        ->and($borrower->refresh()->borrower_id_number)->toBe('922435243')
        ->and($borrower->loan_unit_damage_status)->toBe('destroyed')
        ->and($borrower->source_uuid)->toBe('uuid:form-number-fallback');
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

test('kobo sync command can replay only the selected service', function () {
    KoboRestSubmission::query()->create([
        'service_name' => 'iqrad',
        'submission_uuid' => 'uuid:service-iqrad-only',
        'payload' => [
            '_uuid' => 'uuid:service-iqrad-only',
            'borrower_name' => 'Service Filter Borrower',
        ],
        'sync_status' => 'synced',
        'received_at' => now(),
    ]);

    KoboRestSubmission::query()->create([
        'service_name' => 'other-service',
        'submission_uuid' => 'uuid:service-other',
        'payload' => [
            '_uuid' => 'uuid:service-other',
            'borrower_name' => 'Other Service Borrower',
        ],
        'sync_status' => 'synced',
        'received_at' => now(),
    ]);

    $this->artisan('kobo:sync-rest-submissions --service=iqrad --all')
        ->expectsOutputToContain('Synced: 1')
        ->assertSuccessful();

    expect(KoboRestSubmission::query()->where('service_name', 'iqrad')->value('sync_status'))->toBe('synced')
        ->and(KoboRestSubmission::query()->where('service_name', 'other-service')->value('damage_assessment_borrower_id'))->toBeNull()
        ->and(DamageAssessmentBorrower::query()->where('borrower_name', 'Service Filter Borrower')->exists())->toBeTrue()
        ->and(DamageAssessmentBorrower::query()->where('borrower_name', 'Other Service Borrower')->exists())->toBeFalse();
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
