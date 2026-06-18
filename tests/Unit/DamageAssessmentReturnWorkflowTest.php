<?php

use App\Models\AssessmentEditHistory;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\BuildingSurveyReturnRequest;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use App\Models\TeamLeaderFieldEngineer;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
    Cache::forget('arcgis_token');

    foreach (['Team Leader', 'Field Engineer', 'Area Manager'] as $role) {
        Role::query()->firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    }

    Role::query()->firstOrCreate([
        'name' => 'Team Leader',
        'guard_name' => 'web',
    ]);
});

it('links a team leader with a field engineer and prevents duplicates', function () {
    $admin = User::factory()->create();
    $teamLeader = User::factory()->create();
    $fieldEngineer = User::factory()->create();

    $teamLeader->assignRole('Team Leader');
    $fieldEngineer->assignRole('Field Engineer');

    $this->actingAs($admin)
        ->postJson(route('admin.team-leader-field-engineers.store'), [
            'team_leader_id' => $teamLeader->id,
            'field_engineer_id' => $fieldEngineer->id,
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->assertDatabaseHas('team_leader_field_engineers', [
        'team_leader_id' => $teamLeader->id,
        'field_engineer_id' => $fieldEngineer->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.team-leader-field-engineers.store'), [
            'team_leader_id' => $teamLeader->id,
            'field_engineer_id' => $fieldEngineer->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('field_engineer_id');
});

it('runs the building survey return workflow and archives the full building snapshot', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-arcgis-token',
        ]),
        'https://services2.arcgis.com/*/FeatureServer/0/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true],
            ],
        ]),
    ]);

    $fieldEngineer = User::factory()->create(['region' => 'south']);
    $teamLeader = User::factory()->create();
    $areaManager = User::factory()->create(['region' => 'north']);

    $fieldEngineer->assignRole('Field Engineer');
    $teamLeader->assignRole('Team Leader');
    $areaManager->assignRole('Area Manager');

    TeamLeaderFieldEngineer::query()->create([
        'team_leader_id' => $teamLeader->id,
        'field_engineer_id' => $fieldEngineer->id,
    ]);

    $building = Building::query()->create([
        'objectid' => 7201,
        'globalid' => 'return-building-7201',
        'building_name' => 'Return Building',
        'governorate' => 'Gaza',
    ]);

    $this->actingAs($fieldEngineer)
        ->post(route('building-survey-return-requests.store'), [
            'building_objectid' => $building->objectid,
            'reason' => 'Need survey return',
        ])
        ->assertRedirect(route('building-survey-return-requests.index'));

    $returnRequest = BuildingSurveyReturnRequest::query()->firstOrFail();

    expect($returnRequest->team_leader_id)->toBe($teamLeader->id)
        ->and($returnRequest->status)->toBe('pending')
        ->and($returnRequest->current_step)->toBe('team_leader');

    $this->actingAs($teamLeader)
        ->postJson(route('building-survey-return-requests.team-leader.approve', $returnRequest), [
            'notes' => 'Approved by TL',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $returnRequest->refresh();

    expect($returnRequest->status)->toBe('approved_by_team_leader')
        ->and($returnRequest->current_step)->toBe('area_manager')
        ->and($returnRequest->area_manager_id)->toBe($areaManager->id);

    $this->actingAs($areaManager)
        ->postJson(route('building-survey-return-requests.area-manager.approve', $returnRequest), [
            'notes' => 'Archive object',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $returnRequest->refresh();

    expect($returnRequest->status)->toBe('completed')
        ->and($returnRequest->current_step)->toBe('completed');

    $this->assertDatabaseHas('building_survey_archive_objects', [
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'return_request_id' => $returnRequest->id,
        'archived_by' => $areaManager->id,
    ]);

    $archiveObject = BuildingSurveyArchiveObject::query()->firstOrFail();

    expect(BuildingSurveyArchiveObject::query()->count())->toBe(1);
    expect($archiveObject->building_snapshot['building_name'])->toBe('Return Building');
    expect(Building::query()->where('objectid', $building->objectid)->value('building_name'))->toBe('Return Building');

    Http::assertSent(function ($request) use ($building) {
        $features = json_decode((string) data_get($request->data(), 'features'), true);

        return str_contains($request->url(), '/FeatureServer/0/updateFeatures')
            && data_get($features, '0.attributes.objectid') === $building->objectid
            && data_get($features, '0.attributes.field_status') === 'Not_Completed';
    });
});

it('creates a building survey return request through ajax json', function () {
    $fieldEngineer = User::factory()->create();
    $teamLeader = User::factory()->create();

    $fieldEngineer->assignRole('Field Engineer');
    $teamLeader->assignRole('Team Leader');

    TeamLeaderFieldEngineer::query()->create([
        'team_leader_id' => $teamLeader->id,
        'field_engineer_id' => $fieldEngineer->id,
    ]);

    $building = Building::query()->create([
        'objectid' => 7301,
        'globalid' => 'ajax-return-building-7301',
        'building_name' => 'Ajax Return Building',
    ]);

    $this->actingAs($fieldEngineer)
        ->postJson(route('building-survey-return-requests.store'), [
            'building_objectid' => $building->objectid,
            'reason' => 'Created from modal',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['message', 'return_request_id', 'redirect_url', 'table_row']);

    $this->assertDatabaseHas('building_survey_return_requests', [
        'building_objectid' => $building->objectid,
        'requested_by' => $fieldEngineer->id,
        'team_leader_id' => $teamLeader->id,
        'status' => 'pending',
        'current_step' => 'team_leader',
        'reason' => 'Created from modal',
    ]);

    $this->assertDatabaseHas('building_survey_return_request_logs', [
        'action' => 'created',
        'step' => 'field_engineer',
        'notes' => 'Created from modal',
    ]);
});

it('rejects a building survey return request through ajax json', function () {
    $fieldEngineer = User::factory()->create();
    $teamLeader = User::factory()->create();

    $fieldEngineer->assignRole('Field Engineer');
    $teamLeader->assignRole('Team Leader');

    $building = Building::query()->create([
        'objectid' => 7501,
        'globalid' => 'reject-return-building-7501',
        'building_name' => 'Reject Return Building',
    ]);

    $returnRequest = BuildingSurveyReturnRequest::query()->create([
        'building_id' => $building->id,
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'requested_by' => $fieldEngineer->id,
        'team_leader_id' => $teamLeader->id,
        'current_step' => 'team_leader',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs($teamLeader)
        ->postJson(route('building-survey-return-requests.reject', $returnRequest), [
            'reason' => 'Missing documents',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->assertDatabaseHas('building_survey_return_requests', [
        'id' => $returnRequest->id,
        'status' => 'rejected',
        'current_step' => 'rejected',
        'reason' => 'Missing documents',
    ]);

    $this->assertDatabaseHas('building_survey_return_request_logs', [
        'request_id' => $returnRequest->id,
        'action' => 'rejected',
        'step' => 'team_leader',
        'notes' => 'Missing documents',
    ]);
});

it('requires an area manager for the building governorate when team leader approves through ajax', function () {
    $fieldEngineer = User::factory()->create(['region' => 'north']);
    $teamLeader = User::factory()->create();
    $areaManager = User::factory()->create(['region' => 'north']);

    $fieldEngineer->assignRole('Field Engineer');
    $teamLeader->assignRole('Team Leader');
    $areaManager->assignRole('Area Manager');

    $building = Building::query()->create([
        'objectid' => 7551,
        'globalid' => 'no-governorate-area-manager-building-7551',
        'building_name' => 'No Governorate Area Manager Building',
        'governorate' => 'Rafah',
    ]);

    $returnRequest = BuildingSurveyReturnRequest::query()->create([
        'building_id' => $building->id,
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'requested_by' => $fieldEngineer->id,
        'team_leader_id' => $teamLeader->id,
        'current_step' => 'team_leader',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs($teamLeader)
        ->postJson(route('building-survey-return-requests.team-leader.approve', $returnRequest), [
            'notes' => 'Try approve',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('area_manager_id');

    expect($returnRequest->refresh()->area_manager_id)->toBeNull();
});

it('maps middle area governorate to south area managers', function () {
    $fieldEngineer = User::factory()->create(['region' => 'north']);
    $teamLeader = User::factory()->create();
    $areaManager = User::factory()->create(['region' => 'south']);

    $fieldEngineer->assignRole('Field Engineer');
    $teamLeader->assignRole('Team Leader');
    $areaManager->assignRole('Area Manager');

    $building = Building::query()->create([
        'objectid' => 7552,
        'globalid' => 'middle-area-building-7552',
        'building_name' => 'Middle Area Building',
        'governorate' => 'Middle Area',
    ]);

    $returnRequest = BuildingSurveyReturnRequest::query()->create([
        'building_id' => $building->id,
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'requested_by' => $fieldEngineer->id,
        'team_leader_id' => $teamLeader->id,
        'current_step' => 'team_leader',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs($teamLeader)
        ->postJson(route('building-survey-return-requests.team-leader.approve', $returnRequest), [
            'notes' => 'Middle Area approval',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    expect($returnRequest->refresh()->area_manager_id)->toBe($areaManager->id);
});

it('shows team leader requests for lowercase team leader users even when they also have field engineer role', function () {
    $fieldEngineer = User::factory()->create();
    $teamLeader = User::factory()->create();

    $fieldEngineer->assignRole('Field Engineer');
    $teamLeader->assignRole('Team Leader');
    $teamLeader->assignRole('Field Engineer');

    $building = Building::query()->create([
        'objectid' => 7553,
        'globalid' => 'lowercase-team-leader-building-7553',
        'building_name' => 'Lowercase Team Leader Building',
        'governorate' => 'Khan Younis',
    ]);

    $returnRequest = BuildingSurveyReturnRequest::query()->create([
        'building_id' => $building->id,
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'requested_by' => $fieldEngineer->id,
        'team_leader_id' => $teamLeader->id,
        'current_step' => 'team_leader',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs($teamLeader)
        ->get(route('building-survey-return-requests.index'))
        ->assertOk()
        ->assertSee((string) $returnRequest->building_objectid, false)
        ->assertSee('Lowercase Team Leader Building', false);
});

it('renders the create return request modal on the requests index for field engineers', function () {
    $fieldEngineer = User::factory()->create();
    $fieldEngineer->assignRole('Field Engineer');

    $building = Building::query()->create([
        'objectid' => 7401,
        'globalid' => 'modal-return-building-7401',
        'building_name' => 'Modal Return Building',
    ]);

    $this->actingAs($fieldEngineer)
        ->get(route('building-survey-return-requests.index'))
        ->assertOk()
        ->assertSee('createReturnRequestModal', false)
        ->assertSee('createReturnRequestForm', false)
        ->assertSee((string) $building->objectid, false)
        ->assertSee('Modal Return Building', false);
});

it('does not render the create return request modal for database officers and returns json permission errors', function () {
    Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $databaseOfficer = User::factory()->create();
    $databaseOfficer->assignRole('Database Officer');

    $building = Building::query()->create([
        'objectid' => 7601,
        'globalid' => 'database-officer-return-building-7601',
        'building_name' => 'Database Officer Return Building',
    ]);

    $this->actingAs($databaseOfficer)
        ->get(route('building-survey-return-requests.index'))
        ->assertOk()
        ->assertDontSee('createReturnRequestModal', false)
        ->assertDontSee('createReturnRequestForm', false);

    $this->actingAs($databaseOfficer)
        ->postJson(route('building-survey-return-requests.store'), [
            'building_objectid' => $building->objectid,
            'reason' => 'Not allowed',
        ])
        ->assertForbidden()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'فقط المهندس الميداني يمكنه إنشاء طلب إرجاع استبيان.');
});

it('stores assessment edit history with old and new values and return request linkage', function () {
    $user = User::factory()->create();
    $building = Building::query()->create([
        'objectid' => 8101,
        'globalid' => 'history-building',
        'building_name' => 'Original Name',
    ]);

    $archiveRequest = BuildingSurveyReturnRequest::query()->create([
        'building_id' => $building->id,
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'requested_by' => $user->id,
        'current_step' => 'completed',
        'status' => 'completed',
    ]);

    BuildingSurveyArchiveObject::query()->create([
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'return_request_id' => $archiveRequest->id,
        'archived_by' => $user->id,
        'archived_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('assessment.inline.update'), [
            'type' => 'building_table',
            'globalid' => $building->globalid,
            'field' => 'building_name',
            'value' => 'First Edit',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->actingAs($user)
        ->postJson(route('assessment.inline.update'), [
            'type' => 'building_table',
            'globalid' => $building->globalid,
            'field' => 'building_name',
            'value' => 'Second Edit',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonCount(2, 'history');

    $this->assertDatabaseHas('assessment_edit_histories', [
        'global_id' => $building->globalid,
        'field_name' => 'building_name',
        'old_value' => 'Original Name',
        'new_value' => 'First Edit',
        'return_request_id' => $archiveRequest->id,
    ]);

    $this->assertDatabaseHas('assessment_edit_histories', [
        'global_id' => $building->globalid,
        'field_name' => 'building_name',
        'old_value' => 'First Edit',
        'new_value' => 'Second Edit',
        'return_request_id' => $archiveRequest->id,
    ]);

    expect(EditAssessment::query()
        ->where('global_id', $building->globalid)
        ->where('field_name', 'building_name')
        ->count())->toBe(1);

    expect(AssessmentEditHistory::query()->count())->toBe(2);
});

it('links housing unit edit history to the archived parent building return request', function () {
    $user = User::factory()->create();
    $building = Building::query()->create([
        'objectid' => 9101,
        'globalid' => 'parent-history-building',
        'building_name' => 'Parent',
    ]);
    $housing = HousingUnit::query()->create([
        'objectid' => 9102,
        'globalid' => 'housing-history-unit',
        'parentglobalid' => $building->globalid,
        'unit_owner' => 'Original Owner',
    ]);

    $archiveRequest = BuildingSurveyReturnRequest::query()->create([
        'building_id' => $building->id,
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'requested_by' => $user->id,
        'current_step' => 'completed',
        'status' => 'completed',
    ]);

    BuildingSurveyArchiveObject::query()->create([
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'return_request_id' => $archiveRequest->id,
        'archived_by' => $user->id,
        'archived_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('assessment.inline.update'), [
            'type' => 'housing_table',
            'globalid' => $housing->globalid,
            'field' => 'unit_owner',
            'value' => 'Updated Owner',
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->assertDatabaseHas('assessment_edit_histories', [
        'global_id' => $housing->globalid,
        'objectid' => $housing->objectid,
        'type' => 'housing_table',
        'field_name' => 'unit_owner',
        'old_value' => 'Original Owner',
        'new_value' => 'Updated Owner',
        'return_request_id' => $archiveRequest->id,
    ]);
});
