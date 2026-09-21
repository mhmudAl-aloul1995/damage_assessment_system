<?php

use App\Jobs\SyncCsoArcgisWebhook;
use App\Models\ArcgisWebhookDelivery;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Cache::flush();

    config([
        'services.arcgis.cso_webhook_secret' => 'arcgis-secret',
        'services.arcgis.username' => 'arcgis-user',
        'services.arcgis.password' => 'arcgis-password',
    ]);
});

test('arcgis cso webhook returns crc response token', function (): void {
    $response = $this->getJson('/api/arcgis/csos/webhook?crc_token=check-token');

    $response
        ->assertOk()
        ->assertJsonPath(
            'response_token',
            'sha256='.base64_encode(hash_hmac('sha256', 'check-token', 'arcgis-secret', true))
        );
});

test('arcgis cso webhook runs sync immediately when signature is valid', function (): void {
    Queue::fake();
    CsoSurveyUnit::query()->create([
        'objectid' => 9000,
        'globalid' => 'deleted-unit',
        'unit_name' => 'Deleted unit',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer/extractChanges' => Http::response([
            'layers' => [
                [
                    'id' => 0,
                    'features' => [
                        [
                            'attributes' => [
                                'objectid' => 7001,
                                'globalid' => '{CSO-SURVEY-7001}',
                                'building_name' => 'New CSO Building',
                                'organization_name_en' => 'New CSO Organization',
                                'building_damage_status' => 'partial',
                            ],
                            'geometry' => [
                                'x' => 34.45,
                                'y' => 31.52,
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 1,
                    'updates' => [
                        [
                            'attributes' => [
                                'objectid' => 8001,
                                'globalid' => '{CSO-ORG-8001}',
                                'parentglobalid' => '{CSO-SURVEY-7001}',
                                'organization_name_en' => 'Updated Organization',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 2,
                    'features' => [
                        [
                            'attributes' => [
                                'objectid' => 9001,
                                'globalid' => '{CSO-UNIT-9001}',
                                'parentglobalid' => '{CSO-ORG-8001}',
                                'unit_name' => 'New CSO Unit',
                            ],
                        ],
                    ],
                    'deletes' => [9000],
                ],
            ],
        ]),
    ]);

    $payload = [
        'name' => 'CSO webhook',
        'events' => ['FeaturesCreated'],
        'changesUrl' => urlencode('https://example.com/FeatureServer/extractChanges'),
    ];
    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.base64_encode(hash_hmac('sha256', $content, 'arcgis-secret', true));

    $response = $this->call(
        method: 'POST',
        uri: '/api/arcgis/csos/webhook',
        content: $content,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ESRIHOOK_SIGNATURE' => $signature,
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath('message', 'CSO ArcGIS webhook synced.')
        ->assertJsonPath('summary.upserted', 3)
        ->assertJsonPath('summary.deleted', 1);

    Queue::assertNothingPushed();

    expect(CsoSurvey::query()->where('objectid', 7001)->value('building_name'))->toBe('New CSO Building')
        ->and(CsoSurvey::query()->where('objectid', 7001)->value('organization_name'))->toBe('New CSO Organization')
        ->and(CsoSurvey::query()->where('objectid', 7001)->value('building_damage_status'))->toBe('2')
        ->and(CsoSurveyOrganization::query()->where('objectid', 8001)->value('parentglobalid'))->toBe('cso-survey-7001')
        ->and(CsoSurveyUnit::query()->where('objectid', 9001)->value('parentglobalid'))->toBe('cso-survey-7001')
        ->and(CsoSurveyUnit::query()->where('objectid', 9000)->exists())->toBeFalse();

    $delivery = ArcgisWebhookDelivery::query()->first();

    expect($delivery)
        ->not->toBeNull()
        ->and($delivery->status)->toBe('success')
        ->and($delivery->http_status)->toBe(200)
        ->and($delivery->signature_present)->toBeTrue()
        ->and($delivery->event_names)->toBe(['FeaturesCreated'])
        ->and($delivery->summary)->toBe([
            'upserted' => 3,
            'deleted' => 1,
            'skipped' => 0,
        ]);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.com/FeatureServer/extractChanges'
        && $request['returnInserts'] === 'true'
        && $request['returnUpdates'] === 'true'
        && $request['returnDeletes'] === 'true'
        && $request['token'] === 'arcgis-token');
});

test('arcgis cso webhook rejects invalid signatures', function (): void {
    Queue::fake();

    $this
        ->withHeader('X-EsriHook-Signature', 'sha256=invalid')
        ->postJson('/api/arcgis/csos/webhook', [
            'name' => 'CSO webhook',
            'events' => ['FeaturesCreated'],
        ])
        ->assertUnauthorized();

    Queue::assertNothingPushed();

    expect(ArcgisWebhookDelivery::query()->first())
        ->status->toBe('rejected')
        ->http_status->toBe(401)
        ->webhook_name->toBe('CSO webhook');
});

test('arcgis cso webhook records sync failures', function (): void {
    $payload = [
        'name' => 'CSO webhook',
        'events' => ['FeaturesCreated'],
    ];
    $content = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.base64_encode(hash_hmac('sha256', $content, 'arcgis-secret', true));

    $this->call(
        method: 'POST',
        uri: '/api/arcgis/csos/webhook',
        content: $content,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ESRIHOOK_SIGNATURE' => $signature,
        ]
    )->assertInternalServerError()
        ->assertJsonPath('message', 'CSO ArcGIS webhook sync failed.');

    expect(ArcgisWebhookDelivery::query()->first())
        ->status->toBe('failed')
        ->http_status->toBe(500)
        ->error_message->toContain('does not include changesUrl');
});

test('arcgis cso webhook sync is not queued', function (): void {
    expect(new SyncCsoArcgisWebhook)->not->toBeInstanceOf(ShouldQueue::class);
});
