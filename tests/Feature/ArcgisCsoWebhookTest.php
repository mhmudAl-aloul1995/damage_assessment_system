<?php

use Illuminate\Support\Facades\Artisan;
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

test('arcgis cso webhook runs sync immediately when signature is valid', function (): void {
    Queue::fake();
    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:arcgis-layers', [
            'table' => 'cso_surveys',
            '--force' => true,
        ])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:arcgis-layers', [
            'table' => 'cso_survey_organizations',
            '--force' => true,
        ])
        ->andReturn(0);
    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:arcgis-layers', [
            'table' => 'cso_survey_units',
            '--force' => true,
        ])
        ->andReturn(0);

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
        ->assertJsonPath('message', 'CSO ArcGIS webhook synced.');

    Queue::assertNothingPushed();
});

test('arcgis cso webhook rejects invalid signatures', function (): void {
    Queue::fake();

    $this
        ->withHeader('X-EsriHook-Signature', 'sha256=invalid')
        ->postJson('/api/arcgis/csos/webhook', ['events' => ['FeaturesCreated']])
        ->assertUnauthorized();

    Queue::assertNothingPushed();
});
