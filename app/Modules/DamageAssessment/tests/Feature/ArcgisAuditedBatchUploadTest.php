<?php

use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use App\services\ArcgisAuditedUploadService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    config()->set([
        'services.arcgis.username' => 'tester',
        'services.arcgis.password' => 'secret',
        'services.arcgis.referer' => 'http://localhost',
        'services.arcgis.target_service' => 'https://arcgis.test/TARGET',
        'services.arcgis.source_service' => 'https://arcgis.test/SOURCE',
        'services.arcgis.target_buildings_layer' => 0,
        'services.arcgis.target_units_layer' => 1,
        'services.arcgis.source_buildings_layer' => 10,
        'services.arcgis.source_units_layer' => 11,
    ]);
    $this->remote = [0 => [], 1 => []];
    $this->writes = [];
    $this->editResponse = null;
    $this->queryResponse = null;
    $this->attachmentResponse = null;
    $this->supportsPartialEdits = true;
    $this->fieldDefinitions = [];
    $this->tokens = 0;

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, 'generateToken')) {
            return Http::response(['token' => 'token-'.++$this->tokens]);
        }

        if (str_contains($url, 'Attachment') || str_contains($url, '/attachments')) {
            return $this->attachmentResponse === null
                ? Http::response(['attachmentInfos' => []])
                : ($this->attachmentResponse)($request);
        }

        preg_match('~/TARGET/([01])~', $url, $layerMatch);
        $layer = (int) ($layerMatch[1] ?? 0);
        $suffix = $layer === 0 ? 'B' : 'U';

        if (preg_match('~/TARGET/[01]\?~', $url)) {
            return Http::response([
                'objectIdField' => 'OBJECTID',
                'supportsRollbackOnFailureParameter' => $this->supportsPartialEdits,
                'fields' => array_merge(array_map(fn (string $name): array => ['name' => $name], [
                    'OBJECTID', 'objectid', 'globalid', 'old_objectid_'.$suffix, 'old_global_id_'.$suffix,
                    'parentglobalid', 'is_audited', 'building_damage_status', 'unit_damage_status',
                ]), $this->fieldDefinitions),
            ]);
        }

        if (str_contains($url, '/query')) {
            if ($this->queryResponse !== null) {
                $override = ($this->queryResponse)($request);

                if ($override !== null) {
                    return $override;
                }
            }

            preg_match('/^(\w+) (?:IN \((.*)\)|= (.*))$/', $request['where'], $matches);
            $field = $matches[1];
            $values = str_getcsv($matches[2] !== '' ? $matches[2] : $matches[3], ',', "'", '\\');

            if (str_contains($url, '/SOURCE/')) {
                return Http::response(['features' => array_map(fn (string $id): array => [
                    'attributes' => ['OBJECTID' => (int) $id], 'geometry' => ['x' => (int) $id, 'y' => 31],
                ], $values)]);
            }

            return Http::response(['features' => array_values(array_map(fn (array $attributes): array => ['attributes' => $attributes],
                array_filter($this->remote[$layer], fn (array $attributes): bool => in_array((string) ($attributes[$field] ?? ''), $values, true))))]);
        }

        if (preg_match('~/(add|update)Features$~', $url, $operationMatch)) {
            $operation = $operationMatch[1];
            $features = json_decode($request['features'], true);
            $this->writes[] = ['layer' => $layer, 'operation' => $operation, 'features' => $features];

            if ($this->editResponse !== null) {
                $override = ($this->editResponse)($request, $features);

                if ($override !== null) {
                    return $override;
                }
            }

            $results = [];

            foreach ($features as $feature) {
                $attributes = $feature['attributes'];
                $id = $attributes['OBJECTID'] ?? 9000 + count($this->remote[$layer]);
                $attributes['OBJECTID'] = $id;
                $attributes['globalid'] = 'target-'.$layer.'-'.$id;
                $this->remote[$layer][$id] = $attributes;
                $results[] = ['success' => true, 'objectId' => $id];
            }

            return Http::response([$operation.'Results' => $results]);
        }

        throw new RuntimeException('Unexpected request: '.$url);
    });
});

function createAuditedBatchBuildings(int $count, bool $withUnits = false): void
{
    foreach (range(1, $count) as $id) {
        AuditedBuilding::query()->create(['objectid' => $id, 'globalid' => 'building-'.$id]);

        if ($withUnits) {
            AuditedHousingUnit::query()->create(['objectid' => 1000 + $id, 'globalid' => 'unit-'.$id, 'parentglobalid' => 'building-'.$id]);
        }
    }
}

it('reduces the measured request count for the same hundred buildings', function (int $batchSize, int $expectedRequests, int $expectedWrites): void {
    createAuditedBatchBuildings(100);
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: $batchSize);

    expect($summary['errors'])->toBe(0)->and($summary['buildings_uploaded'])->toBe(100)
        ->and(count($this->writes))->toBe($expectedWrites);
    Http::assertSentCount($expectedRequests);

    foreach ($this->writes as $write) {
        foreach ($write['features'] as $feature) {
            expect($feature['geometry']['x'])->toBe($feature['attributes']['old_objectid_B']);
        }
    }
})->with(['individual requests' => [1, 402, 100], 'batch requests' => [100, 6, 1]]);

it('uses batches by default and finishes buildings before linking units to their target global ids', function (): void {
    createAuditedBatchBuildings(3, true);
    $this->artisan('arcgis:upload-audited', ['--without-attachments' => true])->assertSuccessful();

    expect(array_column($this->writes, 'layer'))->toBe([0, 1]);

    foreach ($this->writes[1]['features'] as $index => $feature) {
        expect($feature['attributes']['parentglobalid'])->toBe('target-0-'.(9000 + $index));
    }
});

it('matches by source object id first then global id and separates additions from updates', function (): void {
    createAuditedBatchBuildings(4);
    $this->remote[0] = [
        81 => ['OBJECTID' => 81, 'old_objectid_B' => 1, 'old_global_id_B' => 'building-1'],
        82 => ['OBJECTID' => 82, 'old_objectid_B' => 99, 'old_global_id_B' => 'BUILDING-2'],
    ];
    $this->queryResponse = function (Request $request) {
        if (str_contains($request->url(), '/TARGET/0/query') && str_starts_with($request['where'], 'old_global_id_B')) {
            return Http::response(['features' => [['attributes' => $this->remote[0][82]]]]);
        }

        return null;
    };
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['buildings_uploaded'])->toBe(2)->and($summary['buildings_updated'])->toBe(2)->and($summary['errors'])->toBe(0);
    expect($this->writes[1]['features'][0]['attributes']['OBJECTID'])->toBe(81)
        ->and($this->writes[1]['features'][0]['attributes']['objectid'])->toBe(1)
        ->and($this->writes[1]['features'][1]['attributes']['OBJECTID'])->toBe(82);
});

it('retries only explicitly failed transient edits and keeps successful rows out of the retry', function (): void {
    createAuditedBatchBuildings(3);
    $this->editResponse = function (Request $request, array $features) {
        if (count($features) === 3) {
            return Http::response(['addResults' => [
                ['success' => true, 'objectId' => 91],
                ['success' => false, 'error' => ['code' => 503, 'description' => 'Busy']],
                ['success' => false, 'error' => ['code' => 1000, 'description' => 'Invalid value']],
            ]]);
        }

        return null;
    };
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['buildings_uploaded'])->toBe(2)->and($summary['errors'])->toBe(1)
        ->and(count($this->writes))->toBe(2)
        ->and($this->writes[1]['features'][0]['attributes']['old_objectid_B'])->toBe(2);
});

it('does not blindly retry an ambiguous failed write', function (string $failure): void {
    createAuditedBatchBuildings(2);
    $this->editResponse = fn () => $failure === 'http'
        ? Http::response(['message' => 'Gateway timeout'], 504)
        : Http::response(['addResults' => [['success' => true, 'objectId' => 91]]]);
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['errors'])->toBe(2)->and(count($this->writes))->toBe(1);
})->with(['http', 'incomplete results']);

it('refreshes an expired token before retrying a rejected batch', function (): void {
    createAuditedBatchBuildings(2);
    $this->editResponse = fn (Request $request) => $request['token'] === 'token-1'
        ? Http::response(['error' => ['code' => 498, 'message' => 'Invalid token']]) : null;
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['errors'])->toBe(0)->and($summary['buildings_uploaded'])->toBe(2)
        ->and($this->tokens)->toBe(2)->and(count($this->writes))->toBe(2);
});

it('subdivides truncated lookups before deciding which records are missing', function (): void {
    createAuditedBatchBuildings(4);
    $this->queryResponse = function (Request $request) {
        if (str_contains($request->url(), '/TARGET/0/query') && str_starts_with($request['where'], 'old_objectid_B IN')) {
            preg_match('/IN \((.*)\)/', $request['where'], $matches);
            $ids = explode(',', $matches[1]);

            if (count($ids) > 2) {
                return Http::response(['features' => [], 'exceededTransferLimit' => true]);
            }

            return Http::response(['features' => array_map(fn (string $id): array => ['attributes' => ['OBJECTID' => 80 + (int) $id, 'old_objectid_B' => (int) $id]], $ids)]);
        }

        return null;
    };
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['buildings_updated'])->toBe(4)->and($summary['buildings_uploaded'])->toBe(0)->and($summary['errors'])->toBe(0);
});

it('falls back to individual writes when the layer cannot keep partial successes', function (): void {
    createAuditedBatchBuildings(3);
    $this->supportsPartialEdits = false;
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['errors'])->toBe(0)->and(count($this->writes))->toBe(3);
});

it('validates tuning options before contacting arcgis', function (array $options): void {
    $this->artisan('arcgis:upload-audited', $options)->assertFailed();
    Http::assertNothingSent();
})->with([
    [['--batch-size' => 0]], [['--batch-size' => 501]], [['--batch-size' => '2.5']],
    [['--attachment-concurrency' => 0]], [['--attachment-concurrency' => 11]],
]);

it('preserves the resume point and unit scope when processing bounded batches', function (?int $limit, array $expectedBuildings, array $expectedUnits): void {
    createAuditedBatchBuildings(6, true);

    foreach ([1, 2] as $id) {
        $this->remote[0][80 + $id] = ['OBJECTID' => 80 + $id, 'old_objectid_B' => $id, 'old_global_id_B' => 'building-'.$id, 'globalid' => 'previous-target-'.$id];
    }

    $summary = app(ArcgisAuditedUploadService::class)->upload(
        buildingsLimit: $limit, withoutAttachments: true, afterBuildingObjectId: 2, batchSize: 2,
    );
    $buildings = collect($this->writes)->where('layer', 0)->pluck('features')->flatten(1)->pluck('attributes.old_objectid_B')->all();
    $units = collect($this->writes)->where('layer', 1)->pluck('features')->flatten(1)->pluck('attributes.old_objectid_U')->all();
    expect($buildings)->toBe($expectedBuildings)->and($units)->toBe($expectedUnits)
        ->and($summary['errors'])->toBe(0);
})->with([
    'limited' => [2, [3, 4], [1003, 1004]],
    'all units' => [null, [3, 4, 5, 6], [1001, 1002, 1003, 1004, 1005, 1006]],
]);

it('only copies attachments for matched targets without reading geometries or editing features', function (): void {
    createAuditedBatchBuildings(3);
    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1];
    $this->remote[0][92] = ['OBJECTID' => 92, 'old_objectid_B' => 2];
    $summary = app(ArcgisAuditedUploadService::class)->upload(attachmentsOnly: true, batchSize: 100, attachmentConcurrency: 3);
    expect($summary['features_skipped'])->toBe(1)->and($summary['errors'])->toBe(0)->and($this->writes)->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/10/query') || str_contains($request->url(), '/10/3/attachments'));
});

it('keeps changed since updates partial when only audit fields changed', function (): void {
    createAuditedBatchBuildings(3);

    foreach ([1, 2] as $id) {
        Building::query()->create(['objectid' => $id, 'globalid' => 'building-'.$id, 'editdate' => '2026-09-01']);
        EditAssessment::query()->create([
            'global_id' => 'building-'.$id, 'type' => 'building_table', 'field_name' => 'building_damage_status',
            'field_value' => 'major', 'updated_at' => '2026-10-15',
        ]);
        AuditedBuilding::query()->whereKey($id)->update(['building_damage_status' => 'major', 'editdate' => '2026-09-01']);
        $this->remote[0][90 + $id] = ['OBJECTID' => 90 + $id, 'old_objectid_B' => $id];
    }

    $summary = app(ArcgisAuditedUploadService::class)->upload(
        withoutAttachments: true, changedSince: \Carbon\CarbonImmutable::parse('2026-10-01'), batchSize: 100,
    );
    expect($summary['buildings_updated'])->toBe(2)->and($summary['features_partially_updated'])->toBe(2)->and($summary['errors'])->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/'));
    expect($this->writes[0]['features'][0])->toBe(['attributes' => ['building_damage_status' => 'major', 'OBJECTID' => 91]]);
});

it('copies missing attachments in bounded pools and retries only expired transfers', function (): void {
    createAuditedBatchBuildings(1);
    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1];
    $events = [];
    $attempts = [];
    $this->attachmentResponse = function (Request $request) use (&$events, &$attempts) {
        $url = $request->url();

        if (str_contains($url, '/SOURCE/10/1/attachments?')) {
            return Http::response(['attachmentInfos' => array_map(fn (int $id): array => ['id' => $id, 'name' => $id.'.jpg', 'size' => 3], range(1, 7))]);
        }

        if (str_contains($url, '/TARGET/0/91/attachments?')) {
            return Http::response(['attachmentInfos' => [['name' => '1.jpg', 'size' => 3]]]);
        }

        if (preg_match('~/attachments/(\d+)\?~', $url, $matches)) {
            $id = (int) $matches[1];
            $events[] = 'download-'.$id;

            if ($id === 3) {
                return Http::response(['error' => ['code' => 404, 'message' => 'Missing']]);
            }

            return Http::response('img');
        }

        if (str_contains($url, '/addAttachment')) {
            $parts = $request->data();
            $file = collect($parts)->first(fn (array $part): bool => ($part['name'] ?? null) === 'attachment');
            $name = $file['filename'];
            $events[] = 'upload-'.$name;
            $attempts[$name] = ($attempts[$name] ?? 0) + 1;

            if ($name === '4.jpg' && $attempts[$name] === 1) {
                return Http::response(['error' => ['code' => 498, 'message' => 'Invalid token']]);
            }

            return Http::response(['addAttachmentResult' => ['success' => true]]);
        }

        throw new RuntimeException('Unexpected attachment request: '.$url);
    };

    $summary = app(ArcgisAuditedUploadService::class)->upload(attachmentsOnly: true, attachmentConcurrency: 3);
    expect($summary['attachments_uploaded'])->toBe(5)->and($summary['attachments_skipped'])->toBe(2)
        ->and($summary['errors'])->toBe(0)->and($this->tokens)->toBe(2)
        ->and($attempts)->toBe(['2.jpg' => 1, '4.jpg' => 2, '5.jpg' => 1, '6.jpg' => 1, '7.jpg' => 1]);
    expect(array_slice($events, 0, 3))->toBe(['download-2', 'download-3', 'download-4']);
    expect(array_search('download-5', $events))->toBeGreaterThan(array_search('upload-4.jpg', $events));
});

it('continues other attachments when one download cannot connect and one upload fails', function (): void {
    createAuditedBatchBuildings(1);
    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1];
    $uploadCalls = 0;
    $this->attachmentResponse = function (Request $request) use (&$uploadCalls) {
        $url = $request->url();

        if (str_contains($url, '/SOURCE/10/1/attachments?')) {
            return Http::response(['attachmentInfos' => array_map(fn (int $id): array => ['id' => $id, 'name' => $id.'.jpg'], range(1, 3))]);
        }

        if (str_contains($url, '/TARGET/0/91/attachments?')) {
            return Http::response(['attachmentInfos' => []]);
        }

        if (str_contains($url, '/attachments/1?')) {
            return Http::failedConnection();
        }

        if (str_contains($url, '/addAttachment')) {
            $uploadCalls++;

            return $uploadCalls === 1 ? Http::response('Gateway timeout', 504) : Http::response(['addAttachmentResult' => ['success' => true]]);
        }

        return Http::response('image');
    };
    $summary = app(ArcgisAuditedUploadService::class)->upload(attachmentsOnly: true, attachmentConcurrency: 3);
    expect($summary['attachments_uploaded'])->toBe(1)->and($summary['errors'])->toBe(2)->and($uploadCalls)->toBe(2);
});

it('does not retry an ambiguous add for a singleton batch', function (): void {
    createAuditedBatchBuildings(1);
    $this->editResponse = fn () => Http::response('Gateway timeout', 504);
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['errors'])->toBe(1)->and(count($this->writes))->toBe(1);
});

it('does not add records when a target lookup omits identifiers', function (): void {
    createAuditedBatchBuildings(2);
    $this->queryResponse = fn (Request $request) => str_contains($request->url(), '/TARGET/0/query')
        ? Http::response(['features' => [['attributes' => ['OBJECTID' => 91]]]]) : null;
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['errors'])->toBe(2)->and($this->writes)->toBe([]);
});

it('refreshes tokens for failed edit results without replaying the successful records', function (): void {
    createAuditedBatchBuildings(2);
    $this->editResponse = function (Request $request) {
        if ($request['token'] === 'token-1') {
            return Http::response(['addResults' => [
                ['success' => true, 'objectId' => 91],
                ['success' => false, 'error' => ['code' => 498, 'description' => 'Invalid token']],
            ]]);
        }

        return null;
    };
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);
    expect($summary['errors'])->toBe(0)->and($summary['buildings_uploaded'])->toBe(2)
        ->and($this->tokens)->toBe(2)->and($this->writes[1]['features'])->toHaveCount(1)
        ->and($this->writes[1]['features'][0]['attributes']['old_objectid_B'])->toBe(2);
});

it('returns a failed command status and skips attachments for rejected feature edits', function (): void {
    createAuditedBatchBuildings(2);
    $this->editResponse = fn () => Http::response(['addResults' => [
        ['success' => true, 'objectId' => 91],
        ['success' => false, 'error' => ['code' => 1000, 'description' => 'Invalid value']],
    ]]);
    $this->artisan('arcgis:upload-audited')->assertFailed();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/10/1/attachments'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/10/2/attachments'));
});

it('resumes missing features while completing existing attachments without updating their data', function (int $batchSize): void {
    createAuditedBatchBuildings(3, true);
    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1, 'old_global_id_B' => 'building-1', 'globalid' => 'existing-building', 'building_damage_status' => 'existing-status'];
    $this->remote[1][191] = ['OBJECTID' => 191, 'old_objectid_U' => 1001, 'old_global_id_U' => 'unit-1', 'parentglobalid' => 'existing-building', 'unit_damage_status' => 'existing-status'];
    $targetAttachments = ['0:91' => ['existing.jpg'], '1:191' => ['existing.jpg']];
    $uploadedFiles = [];
    $this->attachmentResponse = function (Request $request) use (&$targetAttachments, &$uploadedFiles) {
        $url = $request->url();

        if (preg_match('~/SOURCE/\d+/\d+/attachments\?~', $url)) {
            return Http::response(['attachmentInfos' => [
                ['id' => 1, 'name' => 'existing.jpg', 'size' => 3],
                ['id' => 2, 'name' => 'missing.jpg', 'size' => 3],
            ]]);
        }

        if (preg_match('~/TARGET/(\d+)/(\d+)/attachments\?~', $url, $matches)) {
            return Http::response(['attachmentInfos' => array_map(
                fn (string $name): array => ['name' => $name, 'size' => 3],
                $targetAttachments[$matches[1].':'.$matches[2]] ?? [],
            )]);
        }

        if (preg_match('~/TARGET/(\d+)/(\d+)/addAttachment\?~', $url, $matches)) {
            $file = collect($request->data())->first(fn (array $part): bool => ($part['name'] ?? null) === 'attachment');
            $key = $matches[1].':'.$matches[2];
            $targetAttachments[$key][] = $file['filename'];
            $uploadedFiles[] = $key.'/'.$file['filename'];

            return Http::response(['addAttachmentResult' => ['success' => true]]);
        }

        return Http::response('img');
    };
    $service = app(ArcgisAuditedUploadService::class);
    $summary = $service->upload(batchSize: $batchSize, attachmentConcurrency: 3, skipExisting: true);

    expect($summary['errors'])->toBe(0)->and($summary['existing_features_skipped'])->toBe(2)
        ->and($summary['buildings_uploaded'])->toBe(2)->and($summary['units_uploaded'])->toBe(2)
        ->and($summary['buildings_updated'])->toBe(0)->and($summary['units_updated'])->toBe(0)
        ->and($summary['attachments_uploaded'])->toBe(10)
        ->and($this->remote[0][91]['building_damage_status'])->toBe('existing-status')
        ->and($this->remote[1][191]['unit_damage_status'])->toBe('existing-status')
        ->and($uploadedFiles)->toContain('0:91/missing.jpg', '1:191/missing.jpg')
        ->and($uploadedFiles)->not->toContain('0:91/existing.jpg', '1:191/existing.jpg');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/updateFeatures'));

    $writesBeforeResume = count($this->writes);
    $summary = $service->upload(batchSize: $batchSize, attachmentConcurrency: 3, skipExisting: true);
    expect($summary['errors'])->toBe(0)->and($summary['existing_features_skipped'])->toBe(6)
        ->and($summary['attachments_uploaded'])->toBe(0)->and(count($this->writes))->toBe($writesBeforeResume)
        ->and($uploadedFiles)->toHaveCount(10);
})->with(['individual' => 1, 'batches' => 100]);

it('passes the skip existing option through the command and omits feature preparation for existing units', function (): void {
    createAuditedBatchBuildings(2, true);

    foreach ([1, 2] as $id) {
        $this->remote[1][190 + $id] = ['OBJECTID' => 190 + $id, 'old_objectid_U' => 1000 + $id];
    }

    $this->artisan('arcgis:upload-audited', ['--skip-existing' => true, '--only' => 'units'])->assertSuccessful();
    expect($this->writes)->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/11/query')
        || str_contains($request->url(), '/TARGET/0'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/11/1001/attachments'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/11/1002/attachments'));
});

it('can skip existing features without transferring attachments when requested', function (int $batchSize): void {
    createAuditedBatchBuildings(2);
    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1];
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: $batchSize, skipExisting: true);
    expect($summary['errors'])->toBe(0)->and($summary['existing_features_skipped'])->toBe(1)
        ->and($summary['buildings_uploaded'])->toBe(1)->and($summary['buildings_updated'])->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'attachments') || str_contains($request->url(), '/addAttachment'));
})->with([1, 100]);

it('preserves skip existing behavior for changed since and audit edit uploads', function (bool $onlyAuditEdits, int $batchSize): void {
    createAuditedBatchBuildings(2, true);

    foreach ([1, 2] as $id) {
        Building::query()->create(['objectid' => $id, 'globalid' => 'building-'.$id, 'editdate' => '2026-09-01']);
        HousingUnit::query()->create(['objectid' => 1000 + $id, 'globalid' => 'unit-'.$id, 'editdate' => '2026-09-01']);
        EditAssessment::query()->create([
            'global_id' => 'building-'.$id, 'type' => 'building_table', 'field_name' => 'building_damage_status',
            'field_value' => 'major', 'updated_at' => '2026-10-15',
        ]);
        EditAssessment::query()->create([
            'global_id' => 'unit-'.$id, 'type' => 'housing_table', 'field_name' => 'unit_damage_status',
            'field_value' => 'major', 'updated_at' => '2026-10-15',
        ]);
        $this->remote[0][90 + $id] = ['OBJECTID' => 90 + $id, 'old_objectid_B' => $id];
        $this->remote[1][190 + $id] = ['OBJECTID' => 190 + $id, 'old_objectid_U' => 1000 + $id];
    }

    $summary = app(ArcgisAuditedUploadService::class)->upload(
        changedSince: \Carbon\CarbonImmutable::parse('2026-10-01'),
        onlyAuditEdits: $onlyAuditEdits, batchSize: $batchSize, skipExisting: true,
    );
    expect($summary['errors'])->toBe(0)->and($summary['existing_features_skipped'])->toBe(4)->and($this->writes)->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/10/query') || str_contains($request->url(), '/SOURCE/11/query'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/10/1/attachments'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/11/1001/attachments'));
})->with(['changes' => [false, 100], 'individual changes' => [false, 1], 'audit edits' => [true, 100]]);

it('does not retain skip existing mode for later uploads on the same service', function (): void {
    createAuditedBatchBuildings(1);
    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1];
    $service = app(ArcgisAuditedUploadService::class);
    $service->upload(withoutAttachments: true, skipExisting: true);
    $summary = $service->upload(withoutAttachments: true);
    expect($summary['buildings_updated'])->toBe(1)->and($summary['existing_features_skipped'])->toBe(0);
    $service->upload(withoutAttachments: true, skipExisting: true);
    $summary = $service->uploadObjectIds(buildingObjectIds: [1], withoutAttachments: true);
    expect($summary['buildings_updated'])->toBe(1)->and($summary['existing_features_skipped'])->toBe(0);
});

it('encodes date fields as unix milliseconds when reconciling building 9414', function (mixed $value, ?int $expected): void {
    config()->set('app.timezone', 'UTC');
    $this->fieldDefinitions = [['name' => 'EditDate', 'type' => 'esriFieldTypeDate']];
    AuditedBuilding::query()->create(['objectid' => 9414, 'globalid' => 'building-9414', 'editdate' => $value]);
    $summary = app(ArcgisAuditedUploadService::class)->uploadObjectIds(buildingObjectIds: [9414], withoutAttachments: true);

    expect($summary['errors'])->toBe(0)->and($summary['buildings_uploaded'])->toBe(1)
        ->and($this->writes[0]['features'][0]['attributes']['EditDate'])->toBe($expected);
})->with([
    'sql date time' => ['2024-01-01 00:00:00', 1704067200000],
    'explicit timezone' => ['2024-01-01T03:00:00+03:00', 1704067200000],
    'fractional seconds' => ['2024-01-01T00:00:00.123Z', 1704067200123],
    'date only' => ['2024-01-01', 1704067200000],
    'milliseconds' => [1704067200000, 1704067200000],
    'string milliseconds' => ['1704067200000', 1704067200000],
    'epoch' => [0, 0],
    'before epoch' => [-1000, -1000],
    'null' => [null, null],
    'empty' => ['', null],
    'whitespace' => ['  ', null],
    'zero date' => ['0000-00-00', null],
    'zero date time' => ['0000-00-00 00:00:00', null],
]);

it('normalizes dates for bulk additions and updates without changing text fields', function (): void {
    config()->set('app.timezone', 'Asia/Jerusalem');
    $this->fieldDefinitions = [
        ['name' => 'EditDate', 'type' => 'esriFieldTypeDate'],
        ['name' => 'submission_date', 'type' => 'esriFieldTypeString', 'length' => 255],
    ];
    createAuditedBatchBuildings(3);
    AuditedBuilding::query()->update(['editdate' => '2024-01-01 00:00:00', 'submission_date' => '2024-01-01 00:00:00']);
    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1];
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: 100);

    expect($summary['errors'])->toBe(0)->and($summary['buildings_uploaded'])->toBe(2)->and($summary['buildings_updated'])->toBe(1);

    foreach ($this->writes as $write) {
        foreach ($write['features'] as $feature) {
            expect($feature['attributes']['EditDate'])->toBe(1704060000000)
                ->and($feature['attributes']['submission_date'])->toBe('2024-01-01 00:00:00');
        }
    }
});

it('normalizes housing unit dates and date objects in partial audit edits', function (): void {
    $this->fieldDefinitions = [['name' => 'EditDate', 'type' => 'esriFieldTypeDate']];
    $this->remote[1][191] = ['OBJECTID' => 191, 'old_global_id_U' => 'unit-globalid'];
    app(ArcgisAuditedUploadService::class)->syncAuditEditField(
        'housing_table', 'unit-globalid', 'editdate', new DateTimeImmutable('2024-01-01T03:00:00+03:00'),
    );
    expect($this->writes[0]['features'][0]['attributes']['EditDate'])->toBe(1704067200000);
});

it('identifies malformed date fields before sending a rejected feature to arcgis', function (mixed $value): void {
    $this->fieldDefinitions = [['name' => 'EditDate', 'type' => 'esriFieldTypeDate']];
    AuditedBuilding::query()->create(['objectid' => 9414, 'globalid' => 'building-9414', 'editdate' => $value]);
    Log::spy();
    $summary = app(ArcgisAuditedUploadService::class)->uploadObjectIds(buildingObjectIds: [9414], withoutAttachments: true);
    expect($summary['errors'])->toBe(1)->and($this->writes)->toBe([]);
    Log::shouldHaveReceived('error')->once()->with('Failed uploading reconciled audited building to ArcGIS.', \Mockery::on(
        fn (array $context): bool => (int) $context['objectid'] === 9414
            && str_contains($context['message'], 'Invalid date value for ArcGIS field "EditDate"'),
    ));
})->with(['not-a-date', '2024-02-30', 'tomorrow', '2024-01-01 +1 day', '99999999999999999999999']);

it('updates existing features without adding missing rows or transferring attachments', function (int $batchSize): void {
    createAuditedBatchBuildings(3, true);

    foreach ([1, 2] as $id) {
        $this->remote[0][90 + $id] = ['OBJECTID' => 90 + $id, 'old_objectid_B' => $id, 'old_global_id_B' => 'building-'.$id, 'globalid' => 'existing-building-'.$id];
    }

    $this->remote[1][191] = ['OBJECTID' => 191, 'old_objectid_U' => 1001];
    $summary = app(ArcgisAuditedUploadService::class)->upload(withoutAttachments: true, batchSize: $batchSize, existingOnly: true);

    expect($summary['errors'])->toBe(0)->and($summary['buildings_updated'])->toBe(2)
        ->and($summary['units_updated'])->toBe(1)->and($summary['missing_features_skipped'])->toBe(3)
        ->and($summary['buildings_uploaded'])->toBe(0)->and($summary['units_uploaded'])->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/addFeatures')
        || str_contains($request->url(), '/attachments') || str_contains($request->url(), '/addAttachment'));
})->with(['individual' => 1, 'batches' => 100]);

it('supports a separate command for existing unit data while attachments are handled separately', function (): void {
    createAuditedBatchBuildings(3, true);

    foreach ([1, 2] as $id) {
        $this->remote[0][90 + $id] = ['OBJECTID' => 90 + $id, 'old_global_id_B' => 'building-'.$id, 'globalid' => 'existing-building-'.$id];
        $this->remote[1][190 + $id] = ['OBJECTID' => 190 + $id, 'old_objectid_U' => 1000 + $id];
    }

    $this->artisan('arcgis:upload-audited', [
        '--only' => 'units', '--existing-only' => true, '--without-attachments' => true, '--skip-counts' => true,
    ])->assertSuccessful();

    expect($this->writes)->toHaveCount(1)->and($this->writes[0]['layer'])->toBe(1)
        ->and($this->writes[0]['operation'])->toBe('update')
        ->and($this->writes[0]['features'])->toHaveCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/addFeatures')
        || str_contains($request->url(), '/attachments') || str_contains($request->url(), '/addAttachment'));
});

it('skips missing units without preparing their geometry or looking up their parents', function (): void {
    createAuditedBatchBuildings(3, true);
    $summary = app(ArcgisAuditedUploadService::class)->upload(only: 'units', withoutAttachments: true, batchSize: 100, existingOnly: true);
    expect($summary['errors'])->toBe(0)->and($summary['missing_features_skipped'])->toBe(3)->and($this->writes)->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/SOURCE/') || str_contains($request->url(), '/TARGET/0'));
});

it('keeps existing only behavior for changed records and audit edits', function (bool $onlyAuditEdits, int $batchSize): void {
    createAuditedBatchBuildings(2, true);

    foreach ([1, 2] as $id) {
        Building::query()->create(['objectid' => $id, 'globalid' => 'building-'.$id, 'editdate' => '2026-09-01']);
        HousingUnit::query()->create(['objectid' => 1000 + $id, 'globalid' => 'unit-'.$id, 'editdate' => '2026-09-01']);
        EditAssessment::query()->create([
            'global_id' => 'building-'.$id, 'type' => 'building_table', 'field_name' => 'building_damage_status',
            'field_value' => 'major', 'updated_at' => '2026-10-15',
        ]);
        EditAssessment::query()->create([
            'global_id' => 'unit-'.$id, 'type' => 'housing_table', 'field_name' => 'unit_damage_status',
            'field_value' => 'major', 'updated_at' => '2026-10-15',
        ]);
    }

    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1];
    $this->remote[1][191] = ['OBJECTID' => 191, 'old_objectid_U' => 1001];
    $summary = app(ArcgisAuditedUploadService::class)->upload(
        withoutAttachments: true, changedSince: \Carbon\CarbonImmutable::parse('2026-10-01'),
        onlyAuditEdits: $onlyAuditEdits, batchSize: $batchSize, existingOnly: true,
    );
    expect($summary['errors'])->toBe(0)->and($summary['buildings_updated'])->toBe(1)
        ->and($summary['units_updated'])->toBe(1)->and($summary['missing_features_skipped'])->toBe(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/addFeatures') || str_contains($request->url(), '/attachments'));
})->with(['changes' => [false, 100], 'individual changes' => [false, 1], 'audit edits' => [true, 100]]);

it('rejects conflicting skip existing and existing only options before contacting arcgis', function (): void {
    $this->artisan('arcgis:upload-audited', ['--skip-existing' => true, '--existing-only' => true])
        ->expectsOutput('The --skip-existing and --existing-only options cannot be used together.')
        ->assertFailed();
    expect(fn () => app(ArcgisAuditedUploadService::class)->upload(skipExisting: true, existingOnly: true))
        ->toThrow(RuntimeException::class, 'The --skip-existing and --existing-only options cannot be used together.');
    Http::assertNothingSent();
});

it('does not retain existing only mode for later reconciliation or normal uploads', function (): void {
    createAuditedBatchBuildings(2);
    $this->remote[0][91] = ['OBJECTID' => 91, 'old_objectid_B' => 1];
    $service = app(ArcgisAuditedUploadService::class);
    $service->upload(withoutAttachments: true, existingOnly: true);
    $summary = $service->uploadObjectIds(buildingObjectIds: [2], withoutAttachments: true);
    expect($summary['errors'])->toBe(0)->and($summary['buildings_uploaded'])->toBe(1);
    $service->upload(withoutAttachments: true, existingOnly: true);
    AuditedBuilding::query()->create(['objectid' => 3, 'globalid' => 'building-3']);
    $summary = $service->upload(withoutAttachments: true);
    expect($summary['errors'])->toBe(0)->and($summary['buildings_uploaded'])->toBe(1)
        ->and($summary['missing_features_skipped'])->toBe(0);
});
