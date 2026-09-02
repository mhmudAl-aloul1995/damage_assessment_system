<?php

use App\Enums\BuildingDeletionStatus;
use App\Models\Building;
use App\Models\BuildingDeletionRequest;
use App\Models\BuildingDeletionSnapshot;
use App\Models\HousingUnit;
use App\Models\TeamLeaderFieldEngineer;
use App\Models\User;
use App\Notifications\BuildingDeletionReviewRequested;
use App\services\BuildingDeletion\BuildingDeletionProcessor;
use App\services\BuildingDeletion\BuildingDeletionSnapshotService;
use App\services\BuildingDeletion\BuildingDeletionSnapshotValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Cache::flush();

    config()->set('services.arcgis.username', 'user');
    config()->set('services.arcgis.password', 'pass');
    config()->set('services.arcgis.referer', 'https://app.example.test');
    config()->set('services.arcgis.source_service', 'https://source.example.test/FeatureServer');
    config()->set('services.arcgis.target_service', 'https://target.example.test/FeatureServer');
    config()->set('services.arcgis.building_deletion_dry_run', true);
});

it('opens new building deletion requests from an ajax modal on the index page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('building-deletions.index'))
        ->assertOk()
        ->assertSee('data-building-deletion-open-modal', false)
        ->assertSee('buildingDeletionRequestModal')
        ->assertSee('building-deletions-pagination')
        ->assertSee('pagination', false)
        ->assertSee('No building deletion requests have been submitted yet.')
        ->assertDontSee('href="'.route('building-deletions.create').'"', false);
});

it('allows an ordinary authenticated user to open the building deletion request form', function (): void {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 101,
        'globalid' => 'building-open-form',
        'building_name' => 'Open Form Building',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'ar'])
        ->get(route('building-deletions.create', ['building_globalid' => 'building-open-form']))
        ->assertOk()
        ->assertSee('طلب حذف مبنى جديد')
        ->assertSee('سبب الحذف')
        ->assertSee('لصق ObjectIDs')
        ->assertDontSee('مصادر البيانات')
        ->assertDontSee('خطة الحذف')
        ->assertSee('Open Form Building');
});

it('renders the building deletion request form in english locale', function (): void {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 103,
        'globalid' => 'building-english-form',
        'building_name' => 'English Form Building',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('building-deletions.create', ['building_globalid' => 'building-english-form']))
        ->assertOk()
        ->assertSee('New Building Deletion Request')
        ->assertSee('Reason')
        ->assertSee('Paste ObjectIDs')
        ->assertDontSee('Data Sources')
        ->assertDontSee('Deletion Plan')
        ->assertSee('English Form Building');
});

it('loads the building deletion request form through ajax for the modal', function (): void {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 108,
        'globalid' => 'building-modal-form',
        'building_name' => 'Modal Form Building',
    ]);

    $this->actingAs($user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('building-deletions.create', ['building_globalid' => 'building-modal-form']))
        ->assertOk()
        ->assertSee('buildingDeletionForm')
        ->assertSee('Modal Form Building')
        ->assertDontSee('card card-flush')
        ->assertDontSee('DRY RUN');
});

it('searches building deletion candidates by object id beyond the initial options limit', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 210) as $objectId) {
        Building::query()->create([
            'objectid' => $objectId,
            'globalid' => 'search-filler-'.$objectId,
            'building_name' => 'Search Filler '.$objectId,
        ]);
    }

    Building::query()->create([
        'objectid' => 999210,
        'globalid' => 'search-target-building',
        'building_name' => 'Search Target Building',
    ]);

    $this->actingAs($user)
        ->getJson(route('building-deletions.search-buildings', ['q' => '999210']))
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'search-target-building',
        ]);
});

it('limits field engineer building deletion ajax search to assigned buildings', function (): void {
    $fieldEngineer = User::factory()->create(['username_arcgis' => 'field.engineer']);
    $fieldEngineer->assignRole(Role::findOrCreate('Field Engineer', 'web'));

    Building::query()->create([
        'objectid' => 999211,
        'globalid' => 'assigned-search-target',
        'building_name' => 'Assigned Search Target',
        'assignedto' => 'field.engineer',
    ]);

    Building::query()->create([
        'objectid' => 999212,
        'globalid' => 'other-search-target',
        'building_name' => 'Other Search Target',
        'assignedto' => 'other.engineer',
    ]);

    $this->actingAs($fieldEngineer)
        ->getJson(route('building-deletions.search-buildings', ['q' => 'Search Target']))
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'assigned-search-target',
        ])
        ->assertJsonMissing([
            'id' => 'other-search-target',
        ]);
});

it('limits users with a field engineer role to assigned base and audited buildings in the deletion form', function (): void {
    ensureAuditedBuildingDeletionFormColumns();

    $fieldEngineer = User::factory()->create(['username_arcgis' => 'field.engineer']);
    $fieldEngineer->assignRole(Role::findOrCreate('Field Engineer', 'web'));
    $fieldEngineer->assignRole(Role::findOrCreate('Project Officer', 'web'));

    Building::query()->create([
        'objectid' => 104,
        'globalid' => 'assigned-base-building',
        'building_name' => 'Assigned Base Building',
        'assignedto' => 'field.engineer',
    ]);

    Building::query()->create([
        'objectid' => 105,
        'globalid' => 'other-base-building',
        'building_name' => 'Other Base Building',
        'assignedto' => 'other.engineer',
    ]);

    DB::table('audited_buildings')->insert([
        'objectid' => 106,
        'globalid' => 'assigned-audited-building',
        'building_name' => 'Assigned Audited Building',
        'assignedto' => 'field.engineer',
    ]);

    DB::table('audited_buildings')->insert([
        'objectid' => 107,
        'globalid' => 'other-audited-building',
        'building_name' => 'Other Audited Building',
        'assignedto' => 'other.engineer',
    ]);

    $this->actingAs($fieldEngineer)
        ->withSession(['locale' => 'en'])
        ->get(route('building-deletions.create'))
        ->assertOk()
        ->assertSee('Assigned Base Building')
        ->assertSee('Assigned Audited Building')
        ->assertDontSee('Other Base Building')
        ->assertDontSee('Other Audited Building');
});

it('allows an ordinary authenticated user to submit a building deletion request', function (): void {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 102,
        'globalid' => 'building-open-submit',
        'building_name' => 'Open Submit Building',
    ]);

    $this->actingAs($user)
        ->post(route('building-deletions.store'), [
            'building_globalid' => 'building-open-submit',
            'reason' => 'Duplicate building confirmed by the field team.',
            'notes' => 'Submitted by ordinary user.',
            'confirmation' => '1',
        ])
        ->assertRedirect();

    $request = BuildingDeletionRequest::query()
        ->where('building_globalid', 'building-open-submit')
        ->firstOrFail();

    expect($request->requested_by)->toBe($user->id)
        ->and($request->status)->toBe(BuildingDeletionStatus::PendingGisReview)
        ->and($request->requires_field_engineer_approvals)->toBeFalse();
});

it('creates separate deletion requests from multiple selected buildings and pasted object ids', function (): void {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 120,
        'globalid' => 'multi-selected-one',
        'building_name' => 'Multi Selected One',
    ]);
    Building::query()->create([
        'objectid' => 121,
        'globalid' => 'multi-selected-two',
        'building_name' => 'Multi Selected Two',
    ]);
    Building::query()->create([
        'objectid' => 122,
        'globalid' => 'multi-pasted-one',
        'building_name' => 'Multi Pasted One',
    ]);

    $this->actingAs($user)
        ->post(route('building-deletions.store'), [
            'building_globalids' => ['multi-selected-one', 'multi-selected-two'],
            'building_objectids_text' => "121\n122",
            'reason' => 'Duplicate buildings confirmed by the field team.',
            'notes' => 'Submitted as a batch request.',
            'confirmation' => '1',
        ])
        ->assertRedirect(route('building-deletions.index'));

    expect(BuildingDeletionRequest::query()->whereIn('building_globalid', [
        'multi-selected-one',
        'multi-selected-two',
        'multi-pasted-one',
    ])->count())->toBe(3)
        ->and(BuildingDeletionRequest::query()->where('building_globalid', 'multi-selected-two')->count())->toBe(1);
});

it('rejects pasted object ids that are not assigned to a user with the field engineer role', function (): void {
    $fieldEngineer = User::factory()->create(['username_arcgis' => 'field.engineer']);
    $fieldEngineer->assignRole(Role::findOrCreate('Field Engineer', 'web'));

    Building::query()->create([
        'objectid' => 999123,
        'globalid' => 'not-assigned-to-field-engineer',
        'building_name' => 'Not Assigned To Field Engineer',
        'assignedto' => 'other.engineer',
    ]);

    $this->actingAs($fieldEngineer)
        ->postJson(route('building-deletions.store'), [
            'building_objectids_text' => '999123',
            'reason' => 'Duplicate building confirmed by the field team.',
            'confirmation' => '1',
        ])
        ->assertJsonValidationErrors('building_globalids');

    expect(BuildingDeletionRequest::query()->where('building_globalid', 'not-assigned-to-field-engineer')->exists())->toBeFalse();
});

it('sends requests from users with a field engineer role to team leader review first', function (): void {
    Notification::fake();

    $fieldEngineer = User::factory()->create([
        'username_arcgis' => 'field.engineer',
    ]);
    $teamLeader = User::factory()->create();

    $fieldEngineer->assignRole(Role::findOrCreate('Field Engineer', 'web'));
    $fieldEngineer->assignRole(Role::findOrCreate('Project Officer', 'web'));
    $teamLeader->assignRole(Role::findOrCreate('Team Leader', 'web'));

    TeamLeaderFieldEngineer::query()->create([
        'team_leader_id' => $teamLeader->id,
        'field_engineer_id' => $fieldEngineer->id,
    ]);

    Building::query()->create([
        'objectid' => 110,
        'globalid' => 'field-engineer-workflow-building',
        'building_name' => 'Field Engineer Workflow Building',
        'assignedto' => 'field.engineer',
    ]);

    $this->actingAs($fieldEngineer)
        ->post(route('building-deletions.store'), [
            'building_globalid' => 'field-engineer-workflow-building',
            'reason' => 'Duplicate building confirmed by the field team.',
            'notes' => 'Submitted by field engineer.',
            'confirmation' => '1',
        ])
        ->assertRedirect();

    $request = BuildingDeletionRequest::query()
        ->where('building_globalid', 'field-engineer-workflow-building')
        ->firstOrFail();

    expect($request->status)->toBe(BuildingDeletionStatus::PendingTeamLeaderReview)
        ->and($request->requires_field_engineer_approvals)->toBeTrue()
        ->and($request->team_leader_reviewed_by)->toBe($teamLeader->id);

    Notification::assertSentTo($teamLeader, BuildingDeletionReviewRequested::class);
});

it('advances a field engineer deletion request through team leader and area manager before gis', function (): void {
    Notification::fake();

    $fieldEngineer = User::factory()->create(['username_arcgis' => 'field.engineer']);
    $teamLeader = User::factory()->create();
    $areaManager = User::factory()->create(['region' => 'north']);

    $fieldEngineer->assignRole(Role::findOrCreate('Field Engineer', 'web'));
    $teamLeader->assignRole(Role::findOrCreate('Team Leader', 'web'));
    $areaManager->assignRole(Role::findOrCreate('Area Manager', 'web'));

    TeamLeaderFieldEngineer::query()->create([
        'team_leader_id' => $teamLeader->id,
        'field_engineer_id' => $fieldEngineer->id,
    ]);

    Building::query()->create([
        'objectid' => 111,
        'globalid' => 'field-engineer-approval-building',
        'building_name' => 'Field Engineer Approval Building',
        'assignedto' => 'field.engineer',
        'governorate' => 'Gaza',
    ]);

    $this->actingAs($fieldEngineer)
        ->post(route('building-deletions.store'), [
            'building_globalid' => 'field-engineer-approval-building',
            'reason' => 'Duplicate building confirmed by the field team.',
            'confirmation' => '1',
        ])
        ->assertRedirect();

    $request = BuildingDeletionRequest::query()
        ->where('building_globalid', 'field-engineer-approval-building')
        ->firstOrFail();

    $this->actingAs($teamLeader)
        ->post(route('building-deletions.review', $request), [
            'decision' => 'approve',
            'review_notes' => 'Team leader approval is recorded.',
        ])
        ->assertRedirect();

    $request->refresh();

    expect($request->status)->toBe(BuildingDeletionStatus::PendingAreaManagerReview)
        ->and($request->team_leader_reviewed_by)->toBe($teamLeader->id)
        ->and($request->team_leader_reviewed_at)->not->toBeNull()
        ->and($request->area_manager_reviewed_by)->toBe($areaManager->id);

    Notification::assertSentTo($areaManager, BuildingDeletionReviewRequested::class);

    $this->actingAs($areaManager)
        ->post(route('building-deletions.review', $request), [
            'decision' => 'approve',
            'review_notes' => 'Area manager approval is recorded.',
        ])
        ->assertRedirect();

    $request->refresh();

    expect($request->status)->toBe(BuildingDeletionStatus::PendingGisReview)
        ->and($request->area_manager_reviewed_by)->toBe($areaManager->id)
        ->and($request->area_manager_reviewed_at)->not->toBeNull();
});

it('starts snapshot processing only after gis approves the deletion request', function (): void {
    config()->set('queue.default', 'database');

    $requester = User::factory()->create();
    $gisReviewer = User::factory()->create();
    $gisReviewer->assignRole(Role::findOrCreate('Gis Officer', 'web'));

    $request = buildingDeletionRequest($requester, BuildingDeletionStatus::PendingGisReview);

    $this->actingAs($gisReviewer)
        ->post(route('building-deletions.review', $request), [
            'decision' => 'approve',
            'review_notes' => 'GIS approval is recorded.',
        ])
        ->assertRedirect();

    expect($request->refresh()->status)->toBe(BuildingDeletionStatus::Approved)
        ->and($request->gis_reviewed_by)->toBe($gisReviewer->id)
        ->and($request->gis_notes)->toBe('GIS approval is recorded.')
        ->and(DB::table('jobs')->where('queue', 'arcgis')->where('payload', 'like', '%ProcessBuildingDeletionRequest%')->exists())->toBeTrue();
});

it('notifies gis reviewers when a non field engineer submits a deletion request', function (): void {
    Notification::fake();

    $requester = User::factory()->create();
    $gisReviewer = User::factory()->create();
    $gisReviewer->assignRole(Role::findOrCreate('Gis Officer', 'web'));

    Building::query()->create([
        'objectid' => 124,
        'globalid' => 'gis-notification-building',
        'building_name' => 'GIS Notification Building',
    ]);

    $this->actingAs($requester)
        ->post(route('building-deletions.store'), [
            'building_globalids' => ['gis-notification-building'],
            'reason' => 'Duplicate building confirmed by the field team.',
            'confirmation' => '1',
        ])
        ->assertRedirect();

    Notification::assertSentTo($gisReviewer, BuildingDeletionReviewRequested::class);
});

it('notifies the requester when a building deletion request is returned', function (): void {
    Notification::fake();

    $requester = User::factory()->create();
    $gisReviewer = User::factory()->create();
    $gisReviewer->assignRole(Role::findOrCreate('Gis Officer', 'web'));

    $request = buildingDeletionRequest($requester, BuildingDeletionStatus::PendingGisReview);

    $this->actingAs($gisReviewer)
        ->post(route('building-deletions.review', $request), [
            'decision' => 'return',
            'review_notes' => 'Please revise this request.',
        ])
        ->assertRedirect();

    Notification::assertSentTo($requester, BuildingDeletionReviewRequested::class);
});

it('prevents database officers without the gis officer role from approving gis review', function (): void {
    $requester = User::factory()->create();
    $databaseOfficer = User::factory()->create();
    $databaseOfficer->assignRole(Role::findOrCreate('Database Officer', 'web'));

    $request = buildingDeletionRequest($requester, BuildingDeletionStatus::PendingGisReview);

    $this->actingAs($databaseOfficer)
        ->post(route('building-deletions.review', $request), [
            'decision' => 'approve',
            'review_notes' => 'Database officer cannot approve GIS review.',
        ])
        ->assertForbidden();

    expect($request->refresh()->status)->toBe(BuildingDeletionStatus::PendingGisReview);
});

it('allows database officers to see all building deletion requests without gis approval access', function (): void {
    $firstRequester = User::factory()->create(['name' => 'First Requester']);
    $secondRequester = User::factory()->create(['name' => 'Second Requester']);
    $databaseOfficer = User::factory()->create();
    $databaseOfficer->assignRole(Role::findOrCreate('Database Officer', 'web'));

    $firstRequest = buildingDeletionRequest($firstRequester, BuildingDeletionStatus::PendingGisReview);
    $secondBuilding = Building::query()->create([
        'objectid' => 101,
        'globalid' => 'database-officer-visible-building',
        'building_name' => 'Database Officer Visible Building',
    ]);
    $secondRequest = BuildingDeletionRequest::query()->create([
        'building_id' => $secondBuilding->id,
        'building_globalid' => $secondBuilding->globalid,
        'building_objectid' => $secondBuilding->objectid,
        'requested_by' => $secondRequester->id,
        'reason' => 'Second requester deletion.',
        'status' => BuildingDeletionStatus::PendingGisReview,
    ]);

    $this->actingAs($databaseOfficer)
        ->get(route('building-deletions.index'))
        ->assertOk()
        ->assertSee('First Requester')
        ->assertSee('Second Requester');

    $this->actingAs($databaseOfficer)
        ->get(route('building-deletions.show', $firstRequest))
        ->assertOk()
        ->assertSee('First Requester');

    $this->actingAs($databaseOfficer)
        ->post(route('building-deletions.review', $secondRequest), [
            'decision' => 'approve',
            'review_notes' => 'Database officer can view, not approve.',
        ])
        ->assertForbidden();
});

it('submits a building deletion request through ajax for the modal', function (): void {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 109,
        'globalid' => 'building-modal-submit',
        'building_name' => 'Modal Submit Building',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('building-deletions.store'), [
            'building_globalid' => 'building-modal-submit',
            'reason' => 'Duplicate building confirmed by the field team.',
            'notes' => 'Submitted through modal.',
            'confirmation' => '1',
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'message',
            'redirect_url',
        ]);

    expect($response->json('redirect_url'))->toContain('/building-deletions/');
});

it('renders the building deletion request details in arabic locale', function (): void {
    $user = User::factory()->create();
    $request = buildingDeletionRequest($user, BuildingDeletionStatus::PendingGisReview);
    $request->auditLogs()->create([
        'step' => 'audited_buildings',
        'status' => 'gis_building_deleting',
        'message' => 'Deleting Audited/Target Buildings.',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'ar'])
        ->get(route('building-deletions.show', $request))
        ->assertOk()
        ->assertSee('طلب حذف مبنى')
        ->assertSee('بانتظار مراجعة GIS')
        ->assertSee('عرض المبنى المدقق')
        ->assertSee('عرض المبنى الأساسي')
        ->assertSee('damage-assessment/showAssessmentAudit/building-one', false)
        ->assertSee(route('assessment.show', 'building-one'), false)
        ->assertSee('بدء حذف المبنى من الطبقة المدققة')
        ->assertDontSee('جاري حذف مبنى GIS')
        ->assertSee('بدأ النظام حذف المبنى من طبقة المباني المدققة.')
        ->assertDontSee('Deleting Audited/Target Buildings.')
        ->assertSee('لم يتم إنشاء النسخة بعد.');
});

it('renders an archived audited building assessment from the deletion snapshot', function (): void {
    $user = User::factory()->create();

    createArchivedDeletionSnapshot($user);

    $this->actingAs($user)
        ->withSession(['locale' => 'ar'])
        ->get('damage-assessment/showAssessmentAudit/archived-building-one')
        ->assertOk()
        ->assertSee('بيانات المبنى المؤرشفة')
        ->assertSee('هذا المبنى مؤرشف')
        ->assertSee('نسخة المبنى المدقق')
        ->assertSee('Archived Audited Building')
        ->assertSee('Audited Owner')
        ->assertSee('فتح طلب الحذف');
});

it('renders an archived base building assessment from the deletion snapshot', function (): void {
    $user = User::factory()->create();

    createArchivedDeletionSnapshot($user);

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('assessment.show', 'archived-building-one'))
        ->assertOk()
        ->assertSee('Archived Building Data')
        ->assertSee('This building is archived')
        ->assertSee('Base building copy')
        ->assertSee('Archived Base Building')
        ->assertSee('Base Owner')
        ->assertSee('Open Deletion Request');
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

function createArchivedDeletionSnapshot(User $user): BuildingDeletionSnapshot
{
    $request = BuildingDeletionRequest::query()->create([
        'building_globalid' => 'archived-building-one',
        'building_objectid' => 900,
        'requested_by' => $user->id,
        'reason' => 'Archived building test.',
        'status' => BuildingDeletionStatus::Completed,
        'gis_reviewed_by' => $user->id,
        'gis_reviewed_at' => now(),
    ]);

    return BuildingDeletionSnapshot::query()->create([
        'request_id' => $request->id,
        'building_globalid' => 'archived-building-one',
        'building_objectid' => 900,
        'snapshot_version' => '1.0',
        'base_data' => [
            'building' => [
                'database' => [
                    'objectid' => 900,
                    'globalid' => 'archived-building-one',
                    'building_name' => 'Archived Base Building',
                    'floor_nos' => 4,
                    'assignedto' => 'Base Engineer',
                ],
                'gis' => ['found' => false],
            ],
            'housing_units' => [
                [
                    'database' => [
                        'objectid' => 901,
                        'globalid' => 'archived-base-unit',
                        'parentglobalid' => 'archived-building-one',
                        'housing_unit_number' => 'B-1',
                        'unit_owner' => 'Base Owner',
                    ],
                    'gis' => ['found' => false],
                ],
            ],
        ],
        'audited_data' => [
            'building' => [
                'database' => [
                    'objectid' => 900,
                    'globalid' => 'archived-building-one',
                    'building_name' => 'Archived Audited Building',
                    'floor_nos' => 5,
                    'assignedto' => 'Audited Engineer',
                ],
                'gis' => ['found' => false],
            ],
            'housing_units' => [
                [
                    'database' => [
                        'objectid' => 902,
                        'globalid' => 'archived-audited-unit',
                        'parentglobalid' => 'archived-building-one',
                        'housing_unit_number' => 'A-1',
                        'unit_owner' => 'Audited Owner',
                    ],
                    'gis' => ['found' => false],
                ],
            ],
        ],
        'related_data' => [],
        'attachments_data' => [],
        'metadata' => [],
        'schema_data' => [],
        'snapshot_hash' => str_repeat('a', 64),
        'created_by' => $user->id,
        'verified_at' => now(),
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

function ensureAuditedBuildingDeletionFormColumns(): void
{
    Schema::table('audited_buildings', function (Blueprint $table): void {
        foreach (['globalid', 'building_name', 'assignedto', 'municipalitie', 'governorate', 'neighborhood'] as $column) {
            if (! Schema::hasColumn('audited_buildings', $column)) {
                $table->text($column)->nullable();
            }
        }
    });
}
