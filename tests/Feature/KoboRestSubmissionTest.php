<?php

use App\Models\KoboRestSubmission;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;

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
