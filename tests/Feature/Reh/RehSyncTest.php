<?php

use App\Modules\Reh\Jobs\SyncRehSurvey;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehSyncIssue;
use App\Modules\Reh\Models\RehSyncRun;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehArcgisClient;
use App\Modules\Reh\Services\RehImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->buildingFeature = ['attributes' => ['objectid' => 10, 'globalid' => '{11111111-1111-4111-8111-111111111111}', 'building_name' => 'مبنى الاختبار', 'building_structurally_safe' => 'yes'], 'geometry' => ['rings' => [[[34.3, 31.4], [34.31, 31.4], [34.3, 31.41], [34.3, 31.4]]]]];
    $this->unitFeature = ['attributes' => ['objectid' => 20, 'globalid' => '{22222222-2222-4222-8222-222222222222}', 'parentglobalid' => '{11111111-1111-4111-8111-111111111111}', 'owner_full_name' => 'مالك تجريبي', 'boq_1_1_water_tank_repair_qty' => 2]];
    $this->importer = app(RehImporter::class);
    Cache::flush();
    Http::preventStrayRequests();
    config(['reh.username' => 'test-user', 'reh.password' => 'test-password', 'reh.referer' => 'https://example.test']);
});

it('normalizes global ids and imports buildings and related units idempotently', function (): void {
    $building = $this->importer->building($this->buildingFeature);
    $unit = $this->importer->unit($this->unitFeature);
    $this->importer->building($this->buildingFeature);
    $this->importer->unit($this->unitFeature);
    expect(RehBuilding::query()->count())->toBe(1)->and(RehUnit::query()->count())->toBe(1)
        ->and($unit->building_id)->toBe($building->id)->and($unit->fresh()->version)->toBe(1)
        ->and($building->global_id)->toBe('11111111-1111-4111-8111-111111111111');
});

it('stores the layer creator in dedicated columns without resetting approvals for metadata changes', function (string $field): void {
    $this->buildingFeature['attributes'][$field] = 'building-collector';
    $this->unitFeature['attributes'][$field] = 'unit-collector';
    $building = $this->importer->building($this->buildingFeature);
    $unit = $this->importer->unit($this->unitFeature);
    expect($building->fresh()->creator)->toBe('building-collector')
        ->and($unit->fresh()->creator)->toBe('unit-collector')
        ->and($unit->fresh()->source[$field])->toBe('unit-collector');
    $unit->update(['engineering_status' => 'approved', 'social_status' => 'approved', 'decision' => 'selected', 'source_hash' => 'legacy-metadata-hash']);
    $building->update(['source_hash' => 'legacy-metadata-hash']);
    $this->buildingFeature['attributes'][$field] = 'updated-building-collector';
    $this->unitFeature['attributes'][$field] = 'updated-unit-collector';
    $this->importer->building($this->buildingFeature);
    $this->importer->unit($this->unitFeature);
    expect($building->fresh()->creator)->toBe('updated-building-collector')
        ->and($unit->fresh()->creator)->toBe('updated-unit-collector')
        ->and($unit->fresh()->version)->toBe(1)
        ->and($unit->fresh()->engineering_status)->toBe('approved')
        ->and($unit->fresh()->social_status)->toBe('approved')
        ->and($unit->fresh()->decision)->toBe('selected')
        ->and($unit->reviews()->count())->toBe(0);
})->with(['Creator', 'creator']);

it('invalidates approvals on source changes but preserves quantity overrides and history', function (): void {
    $this->importer->building($this->buildingFeature);
    $unit = $this->importer->unit($this->unitFeature);
    $unit->update(['engineering_status' => 'approved', 'social_status' => 'approved', 'decision' => 'selected', 'quantity_overrides' => ['boq_1_1_water_tank_repair_qty' => 0]]);
    $this->unitFeature['attributes']['boq_1_1_water_tank_repair_qty'] = 3;
    $this->importer->unit($this->unitFeature);
    $unit->refresh();
    expect($unit->workflow_status)->toBe('source_changed')->and($unit->decision)->toBe('pending')
        ->and($unit->engineering_status)->toBe('pending')->and($unit->quantity_overrides)->toBe(['boq_1_1_water_tank_repair_qty' => 0])
        ->and($unit->reviews()->first()->action)->toBe('source_changed');
    $version = $unit->version;
    $this->unitFeature['attributes']['EditDate'] = 1780000000000;
    $this->importer->unit($this->unitFeature);
    expect($unit->fresh()->version)->toBe($version);
});

it('invalidates unit decisions when building safety changes', function (): void {
    $this->importer->building($this->buildingFeature);
    $unit = $this->importer->unit($this->unitFeature);
    $unit->update(['decision' => 'selected', 'engineering_status' => 'approved']);
    $this->buildingFeature['attributes']['building_structurally_safe'] = 'no';
    $this->importer->building($this->buildingFeature);
    expect($unit->fresh()->decision)->toBe('pending')->and($unit->fresh()->workflow_status)->toBe('source_changed');
});

it('rejects orphan units and invalid global ids instead of silently dropping them', function (): void {
    expect(fn () => $this->importer->unit($this->unitFeature))->toThrow(RuntimeException::class);
    $this->buildingFeature['attributes']['globalid'] = 'not-a-guid';
    expect(fn () => $this->importer->building($this->buildingFeature))->toThrow(RuntimeException::class);
    expect(RehUnit::query()->count())->toBe(0);
});

it('retains missing records and suspends decisions after a complete snapshot', function (): void {
    $building = $this->importer->building($this->buildingFeature);
    $unit = $this->importer->unit($this->unitFeature);
    $unit->update(['decision' => 'selected']);
    $this->importer->markMissing([$building->id], []);
    expect($unit->fresh()->source_missing)->toBeTrue()->and($unit->fresh()->decision)->toBe('pending');
    expect(RehUnit::query()->count())->toBe(1);
});

it('reads all object id batches despite service transfer limits', function (): void {
    $first = $this->buildingFeature;
    $second = $this->buildingFeature;
    $second['attributes']['objectid'] = 11;
    Http::fake(function ($request) use ($first, $second) {
        if (str_contains($request->url(), 'generateToken')) {
            return Http::response(['token' => 'fake-secret']);
        }
        if (! str_contains($request->url(), '/query')) {
            return Http::response(['fields' => [['name' => 'globalid'], ['name' => 'objectid']]]);
        }
        if ($request['returnIdsOnly'] ?? false) {
            return Http::response(['objectIds' => [10, 11]]);
        }
        if ($request['objectIds'] === '10,11') {
            return Http::response(['features' => [$first], 'exceededTransferLimit' => true]);
        }

        return Http::response(['features' => [$request['objectIds'] === '10' ? $first : $second]]);
    });
    $records = iterator_to_array(app(RehArcgisClient::class)->records(0), false);
    expect($records)->toHaveCount(2);
    Http::assertSent(fn ($request): bool => ($request['outSR'] ?? null) === 4326);
});

it('fails on ArcGIS errors returned with HTTP 200 and does not expose tokens', function (): void {
    Http::fake(['*generateToken' => Http::response(['token' => 'never-leak-this']), '*' => Http::response(['error' => ['code' => 403, 'message' => 'denied']])]);
    $run = RehSyncRun::query()->create();
    expect(fn () => app()->call([new SyncRehSurvey($run->id), 'handle']))->toThrow(RuntimeException::class);
    expect($run->fresh()->status)->toBe('failed')->and($run->fresh()->message)->not->toContain('never-leak-this');
});

it('marks sync complete only after both layers have been received', function (): void {
    $building = $this->buildingFeature;
    $unit = $this->unitFeature;
    Http::fake(function ($request) use ($building, $unit) {
        if (str_contains($request->url(), 'generateToken')) {
            return Http::response(['token' => 'fake-secret']);
        }
        $unitLayer = str_contains($request->url(), '/1');
        if (! str_contains($request->url(), '/query')) {
            return Http::response(['fields' => [['name' => 'globalid'], ['name' => 'objectid'], ['name' => 'parentglobalid']]]);
        }
        if ($request['returnIdsOnly'] ?? false) {
            return Http::response(['objectIds' => [$unitLayer ? 20 : 10]]);
        }

        return Http::response(['features' => [$unitLayer ? $unit : $building]]);
    });
    $run = RehSyncRun::query()->create();
    app()->call([new SyncRehSurvey($run->id), 'handle']);
    expect($run->fresh()->status)->toBe('completed')->and($run->fresh()->buildings_count)->toBe(1)->and($run->fresh()->units_count)->toBe(1);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'applyEdits'));
});

it('quarantines orphan source data and resolves it on a later sync without duplicating records', function (): void {
    $building = $this->buildingFeature;
    $unit = $this->unitFeature;
    $orphan = $unit;
    $orphan['attributes']['objectid'] = 21;
    $orphan['attributes']['globalid'] = '33333333-3333-4333-8333-333333333333';
    $orphan['attributes']['parentglobalid'] = '44444444-4444-4444-8444-444444444444';
    Http::fake(function ($request) use ($building, $unit, &$orphan) {
        if (str_contains($request->url(), 'generateToken')) {
            return Http::response(['token' => 'fake-secret']);
        }
        $unitLayer = str_contains($request->url(), '/1');
        if (! str_contains($request->url(), '/query')) {
            return Http::response(['fields' => [['name' => 'globalid'], ['name' => 'objectid'], ['name' => 'parentglobalid']]]);
        }
        if ($request['returnIdsOnly'] ?? false) {
            return Http::response(['objectIds' => $unitLayer ? [20, 21] : [10]]);
        }

        return Http::response(['features' => $unitLayer ? [$unit, $orphan] : [$building]]);
    });
    $run = RehSyncRun::query()->create();
    app()->call([new SyncRehSurvey($run->id), 'handle']);
    expect($run->fresh()->status)->toBe('completed_with_warnings')->and(RehUnit::query()->count())->toBe(1);
    $issue = RehSyncIssue::query()->sole();
    expect($issue->object_id)->toBe(21)->and($issue->source)->toBe($orphan)->and($issue->resolved_at)->toBeNull();
    $orphan['attributes']['parentglobalid'] = $unit['attributes']['parentglobalid'];
    $next = RehSyncRun::query()->create();
    app()->call([new SyncRehSurvey($next->id), 'handle']);
    expect($next->fresh()->status)->toBe('completed')->and(RehUnit::query()->count())->toBe(2)->and($issue->fresh()->resolved_at)->not->toBeNull();
});
