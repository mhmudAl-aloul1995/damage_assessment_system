<?php

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    app(RolesAndPermissionsSeeder::class)->run();
});

it('shows committee decision pages and datatable data for buildings and housing units', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view committee decisions');

    $building = Building::query()->create([
        'objectid' => 9001,
        'globalid' => 'building-guid-1',
        'building_name' => 'Hope Tower',
        'neighborhood' => 'Rimal',
        'assignedto' => 'Engineer Field',
        'building_damage_status' => 'committee_review',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9101,
        'globalid' => 'housing-guid-1',
        'parentglobalid' => 'building-guid-1',
        'housing_unit_number' => 'A-12',
        'q_9_3_1_first_name' => 'Ahmad',
        'q_9_3_2_second_name__father' => 'Mohammad',
        'q_9_3_4_last_name' => 'Haddad',
        'neighborhood' => 'Rimal',
        'unit_damage_status' => 'committee_review2',
    ]);

    $this->actingAs($user)
        ->get(route('committee-decisions.index'))
        ->assertOk()
        ->assertSee('Committee Decisions')
        ->assertSee('Buildings')
        ->assertSee('Housing Units');

    $this->actingAs($user)
        ->get(route('committee-decisions.buildings.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonFragment(['building_name' => 'Hope Tower']);

    $this->actingAs($user)
        ->get(route('committee-decisions.housing-units.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertSee('A-12');

    $this->actingAs($user)
        ->get(route('committee-decisions.buildings.show', $building))
        ->assertOk()
        ->assertSee('Decision Summary');
});

it('completes the committee workflow, archives the object, and syncs arcgis after required signatures', function () {
    config()->set('services.committee_decisions.arcgis.base_url', 'https://example.test/arcgis/FeatureServer');
    config()->set('services.committee_decisions.arcgis.building_layer_id', 0);
    config()->set('services.committee_decisions.arcgis.identifier_field', 'objectid');
    config()->set('services.committee_decisions.arcgis.status_field', 'field_status');
    config()->set('services.committee_decisions.arcgis.status_value', 'Not_Completed');
    config()->set('services.committee_decisions.arcgis.token', 'static-token');

    Http::fake([
        'https://example.test/arcgis/FeatureServer/0/updateFeatures' => Http::response([
            'updateResults' => [['success' => true]],
        ], 200),
    ]);

    $manager = User::factory()->create(['name' => 'Manager User']);
    $manager->givePermissionTo([
        'view committee decisions',
        'manage committee decision content',
        'edit committee decisions',
    ]);

    $engineer = User::factory()->create([
        'name' => 'Engineer Field',
        'username_arcgis' => 'engineer.field.arcgis',
    ]);

    $signerOneUser = User::factory()->create();
    $signerTwoUser = User::factory()->create();
    $signerOneUser->givePermissionTo('sign committee decisions');
    $signerTwoUser->givePermissionTo('sign committee decisions');

    $memberOne = CommitteeMember::factory()->linkedUser($signerOneUser)->create([
        'name' => 'Signer One',
        'sort_order' => 1,
        'is_required' => true,
    ]);
    $memberTwo = CommitteeMember::factory()->linkedUser($signerTwoUser)->create([
        'name' => 'Signer Two',
        'sort_order' => 2,
        'is_required' => true,
    ]);

    $building = Building::query()->create([
        'objectid' => 9301,
        'globalid' => 'building-guid-2',
        'building_name' => 'Safa Building',
        'neighborhood' => 'Tel Al Hawa',
        'assignedto' => $engineer->username_arcgis,
        'building_damage_status' => 'committee_review',
    ]);

    $this->actingAs($manager)->put(route('committee-decisions.update', CommitteeDecision::query()->firstOrCreate([
        'decisionable_type' => Building::class,
        'decisionable_id' => $building->id,
    ], [
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ])), [
        'decision_type' => 'accepted',
        'decision_text' => 'The final decision for the building was approved.',
        'action_text' => 'Run field action',
        'notes' => 'Reviewed',
        'decision_date' => '2026-04-21',
    ])->assertRedirect();

    $decision = CommitteeDecision::query()->whereMorphedTo('decisionable', $building)->firstOrFail();

    $this->actingAs($signerOneUser)->post(route('committee-decisions.sign', $decision), [
        'committee_member_id' => $memberOne->id,
        'status' => 'approved',
        'notes' => 'Approved',
    ])->assertRedirect();

    $this->actingAs($signerTwoUser)->post(route('committee-decisions.sign', $decision), [
        'committee_member_id' => $memberTwo->id,
        'status' => 'approved',
        'notes' => 'Signed',
    ])->assertRedirect();

    $decision->refresh();

    expect($decision->status)->toBe('completed');
    expect($decision->completed_at)->not->toBeNull();
    expect($decision->arcgis_sync_status)->toBe('synced');
    expect(BuildingSurveyArchiveObject::query()
        ->where('source_type', 'committee_decision')
        ->where('committee_decision_id', $decision->id)
        ->where('building_objectid', $building->objectid)
        ->exists())->toBeTrue();

    Http::assertSent(function ($request): bool {
        $features = json_decode((string) data_get($request->data(), 'features'), true);

        return str_contains($request->url(), '/updateFeatures')
            && data_get($features, '0.attributes.field_status') === 'Not_Completed';
    });
});
