<?php

use App\Enums\BuildingDeletionSignatureAction;
use App\Enums\BuildingDeletionStatus;
use App\Models\Building;
use App\Models\BuildingDeletionRequest;
use App\Models\BuildingDeletionSignature;
use App\Models\BuildingDeletionSnapshot;
use App\Models\HousingUnit;
use App\Models\User;
use App\Services\BuildingDeletion\BuildingDeletionProcessor;
use App\Services\BuildingDeletion\BuildingDeletionSnapshotService;
use App\Services\BuildingDeletion\BuildingDeletionSnapshotValidator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Cache::flush();

    config()->set('services.arcgis.username', 'user');
    config()->set('services.arcgis.password', 'pass');
    config()->set('services.arcgis.referer', 'https://app.example.test');
    config()->set('services.arcgis.source_service', 'https://source.example.test/FeatureServer');
    config()->set('services.arcgis.target_service', 'https://target.example.test/FeatureServer');
    config()->set('services.arcgis.building_deletion_dry_run', true);
});

it('cannot process without applicant signature', function (): void {
    $user = User::factory()->create();
    $request = buildingDeletionRequest($user, BuildingDeletionStatus::Approved);

    BuildingDeletionSignature::query()->create([
        'request_id' => $request->id,
        'user_id' => $user->id,
        'role' => 'GIS',
        'action' => BuildingDeletionSignatureAction::GisApproved,
        'signature_path' => 'fake.png',
        'signed_at' => now(),
    ]);

    expect(fn () => app(BuildingDeletionProcessor::class)->process($request->id))
        ->toThrow(RuntimeException::class, 'applicant signature');

    expect($request->fresh()->status)->toBe(BuildingDeletionStatus::Failed)
        ->and($request->fresh()->failure_reason)->toContain('applicant signature');
});

it('captures null empty zero and false values in the database snapshot', function (): void {
    fakeArcgis();

    $user = User::factory()->create();
    $request = buildingDeletionRequest($user, BuildingDeletionStatus::Approved);

    $snapshot = app(BuildingDeletionSnapshotService::class)->create($request, $user->id);

    expect($snapshot->base_data['building']['database'])->toHaveKey('owner_mobile')
        ->and($snapshot->base_data['building']['database']['owner_mobile'])->toBeNull()
        ->and($snapshot->base_data['building']['database']['owner_name'])->toBe('')
        ->and((string) $snapshot->base_data['building']['database']['units_nos'])->toBe('0')
        ->and($snapshot->base_data['housing_units'][0]['database'])->toHaveKey('occupied')
        ->and($snapshot->base_data['housing_units'][0]['database']['occupied'])->toBe('0')
        ->and($snapshot->base_data['building']['gis']['feature']['geometry'])->toBeArray()
        ->and($snapshot->base_data['building']['gis']['feature']['attributes']['has_elevator'])->toBeFalse();
});

it('fails snapshot validation when a database column key is missing', function (): void {
    app(BuildingDeletionSnapshotValidator::class)->validate([
        'schema' => [
            'building_columns' => ['id', 'globalid', 'owner_mobile'],
            'housing_unit_columns' => [],
        ],
        'base' => [
            'building' => [
                'database' => [
                    'id' => 1,
                    'globalid' => 'building-one',
                ],
            ],
            'housing_units' => [],
        ],
    ]);
})->throws(RuntimeException::class, 'owner_mobile');

it('does not call deleteFeatures before creating a verified snapshot', function (): void {
    fakeArcgis();

    $user = User::factory()->create();
    $request = buildingDeletionRequest($user, BuildingDeletionStatus::Approved);
    sign($request, $user, BuildingDeletionSignatureAction::Requested);
    sign($request, $user, BuildingDeletionSignatureAction::GisApproved);

    app(BuildingDeletionProcessor::class)->process($request->id);

    expect(BuildingDeletionSnapshot::query()->where('request_id', $request->id)->whereNotNull('verified_at')->exists())->toBeTrue()
        ->and($request->fresh()->status)->toBe(BuildingDeletionStatus::Completed);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/deleteFeatures'));
});

it('marks the request failed when a GIS delete fails after snapshot verification', function (): void {
    config()->set('services.arcgis.building_deletion_dry_run', false);
    fakeArcgis(deleteSucceeds: false);

    $user = User::factory()->create();
    $request = buildingDeletionRequest($user, BuildingDeletionStatus::Approved);
    sign($request, $user, BuildingDeletionSignatureAction::Requested);
    sign($request, $user, BuildingDeletionSignatureAction::GisApproved);

    expect(fn () => app(BuildingDeletionProcessor::class)->process($request->id))
        ->toThrow(RuntimeException::class, 'GIS deletion failed');

    expect(BuildingDeletionSnapshot::query()->where('request_id', $request->id)->whereNotNull('verified_at')->exists())->toBeTrue()
        ->and($request->fresh()->status)->toBe(BuildingDeletionStatus::Failed)
        ->and($request->fresh()->failure_reason)->toContain('GIS deletion failed');
});

it('blocks users without raw snapshot permission from viewing raw snapshot json', function (): void {
    $user = User::factory()->create();
    $request = buildingDeletionRequest($user, BuildingDeletionStatus::Completed);

    BuildingDeletionSnapshot::query()->create([
        'request_id' => $request->id,
        'building_id' => $request->building_id,
        'building_globalid' => $request->building_globalid,
        'building_objectid' => $request->building_objectid,
        'snapshot_version' => '1.0',
        'base_data' => ['building' => ['database' => ['globalid' => $request->building_globalid]]],
        'audited_data' => [],
        'target_data' => null,
        'related_data' => [],
        'attachments_data' => [],
        'metadata' => [],
        'schema_data' => [],
        'snapshot_hash' => str_repeat('a', 64),
        'created_by' => $user->id,
        'verified_at' => now(),
    ]);

    Permission::findOrCreate('damage-assessment.building-deletion.view', 'web');
    $user->givePermissionTo('damage-assessment.building-deletion.view');

    $this->actingAs($user)
        ->get(route('building-deletions.raw-snapshot', $request))
        ->assertForbidden();
});

function buildingDeletionRequest(User $user, BuildingDeletionStatus $status): BuildingDeletionRequest
{
    $building = Building::query()->create([
        'objectid' => 100,
        'globalid' => 'building-one',
        'building_name' => 'Deletion Candidate',
        'owner_mobile' => null,
        'owner_name' => '',
        'units_nos' => 0,
    ]);

    HousingUnit::query()->create([
        'objectid' => 200,
        'globalid' => 'unit-one',
        'parentglobalid' => $building->globalid,
        'housing_unit_number' => '0',
        'occupied' => '0',
    ]);

    return BuildingDeletionRequest::query()->create([
        'building_id' => $building->id,
        'building_globalid' => $building->globalid,
        'building_objectid' => $building->objectid,
        'requested_by' => $user->id,
        'reason' => 'Duplicate building record confirmed by field team.',
        'status' => $status,
        'gis_reviewed_by' => $user->id,
        'gis_reviewed_at' => now(),
    ]);
}

function sign(BuildingDeletionRequest $request, User $user, BuildingDeletionSignatureAction $action): void
{
    BuildingDeletionSignature::query()->create([
        'request_id' => $request->id,
        'user_id' => $user->id,
        'role' => 'Test',
        'action' => $action,
        'signature_path' => 'fake.png',
        'signed_at' => now(),
    ]);
}

function fakeArcgis(bool $deleteSucceeds = true): void
{
    Http::fake(function (Request $request) use ($deleteSucceeds) {
        $url = $request->url();

        if (str_contains($url, 'generateToken')) {
            return Http::response(['token' => 'fake-token']);
        }

        if (str_contains($url, '/attachments')) {
            return Http::response(['attachmentInfos' => [
                ['id' => 1, 'name' => 'photo.jpg', 'contentType' => 'image/jpeg', 'size' => 100],
            ]]);
        }

        if (str_contains($url, '/deleteFeatures')) {
            return Http::response([
                'deleteResults' => [
                    ['objectId' => 200, 'success' => $deleteSucceeds],
                ],
            ]);
        }

        $where = (string) $request['where'];

        if (str_contains($where, 'objectid =')) {
            return Http::response(['features' => []]);
        }

        if (str_contains($where, 'parentglobalid')) {
            return Http::response(['features' => [[
                'attributes' => [
                    'objectid' => 200,
                    'globalid' => 'unit-one',
                    'parentglobalid' => 'building-one',
                    'housing_unit_number' => '0',
                ],
                'geometry' => ['x' => 35.2, 'y' => 31.5],
            ]]]);
        }

        return Http::response(['features' => [[
            'attributes' => [
                'objectid' => 100,
                'globalid' => 'building-one',
                'owner_mobile' => null,
                'owner_name' => '',
                'units_nos' => 0,
                'has_elevator' => false,
            ],
            'geometry' => ['rings' => [[[35.1, 31.5], [35.2, 31.5], [35.2, 31.6], [35.1, 31.5]]]],
        ]]]);
    });
}
