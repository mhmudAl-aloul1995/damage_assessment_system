<?php

use App\Jobs\SyncCsoArcgisWebhook;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config(['services.arcgis.cso_webhook_secret' => 'arcgis-secret']);
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

test('arcgis cso webhook queues sync job when signature is valid', function (): void {
    Queue::fake();

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
        ->assertAccepted()
        ->assertJsonPath('message', 'CSO ArcGIS webhook queued.');

    Queue::assertPushedOn('arcgis', SyncCsoArcgisWebhook::class, function (SyncCsoArcgisWebhook $job) use ($payload): bool {
        return $job->payload === $payload;
    });
});

test('arcgis cso webhook rejects invalid signatures', function (): void {
    Queue::fake();

    $this
        ->withHeader('X-EsriHook-Signature', 'sha256=invalid')
        ->postJson('/api/arcgis/csos/webhook', ['events' => ['FeaturesCreated']])
        ->assertUnauthorized();

    Queue::assertNothingPushed();
});

test('arcgis cso webhook sync job is unique', function (): void {
    $job = new SyncCsoArcgisWebhook;

    expect($job)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('arcgis-cso-webhook-sync')
        ->and($job->uniqueFor)->toBe(3600);
});
