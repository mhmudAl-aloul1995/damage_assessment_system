<?php

use App\Models\KoboRestSubmission;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksScore;
use Illuminate\Support\Facades\Http;

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

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer api-token'));
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
