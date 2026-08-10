<?php

use App\Models\AssessmentStatus;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\BuildingStatusHistory;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\EditAssessment;
use App\Models\HousingStatus;
use App\Models\HousingStatusHistory;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    Cache::flush();

    config()->set('services.arcgis.buildings_url', 'https://example.test/FeatureServer/0');
    config()->set('services.arcgis.housing_units_url', 'https://example.test/FeatureServer/1');
});

it('deletes a building and its housing units from arcgis and the database', function () {
    Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $assignee = User::factory()->create();
    $user->assignRole('Database Officer');

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'مقبول هندسيا',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 9101,
        'globalid' => 'building-delete-globalid',
        'building_name' => 'Building To Delete',
        'field_status' => 'COMPLETED',
    ]);

    $housingUnit = HousingUnit::query()->create([
        'objectid' => 9201,
        'globalid' => 'housing-delete-globalid',
        'parentglobalid' => $building->globalid,
        'unit_owner' => 'Unit Owner',
    ]);

    AssignedAssessmentUser::query()->create([
        'manager_id' => $user->id,
        'user_id' => $assignee->id,
        'type' => 'QC/QA Engineer',
        'building_id' => $building->objectid,
    ]);

    BuildingStatus::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    BuildingStatusHistory::query()->create([
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    HousingStatus::query()->create([
        'housing_id' => $housingUnit->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    HousingStatusHistory::query()->create([
        'housing_id' => $housingUnit->objectid,
        'status_id' => $status->id,
        'user_id' => $user->id,
        'type' => 'QC/QA Engineer',
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'building_name',
        'field_value' => 'Edited Building Name',
        'user_id' => $user->id,
    ]);

    EditAssessment::query()->create([
        'global_id' => $housingUnit->globalid,
        'type' => 'housing_table',
        'field_name' => 'unit_owner',
        'field_value' => 'Edited Unit Owner',
        'user_id' => $user->id,
    ]);

    Http::fake([
        'www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'fake-token']),
        'example.test/FeatureServer/1/deleteFeatures' => Http::response([
            'deleteResults' => [
                ['objectId' => $housingUnit->objectid, 'success' => true],
            ],
        ]),
        'example.test/FeatureServer/0/deleteFeatures' => Http::response([
            'deleteResults' => [
                ['objectId' => $building->objectid, 'success' => true],
            ],
        ]),
    ]);

    $scheduleResponse = $this->actingAs($user)
        ->postJson(route('audit.building.delete.schedule', $building->globalid), [
            '_token' => csrf_token(),
        ])
        ->assertOk()
        ->assertJson([
            'housing_units_count' => 1,
        ]);

    $token = $scheduleResponse->json('token');

    $this->actingAs($user)
        ->postJson(route('audit.building.delete.commit'), [
            '_token' => csrf_token(),
            'token' => $token,
        ])
        ->assertOk()
        ->assertJson([
            'deleted_buildings_from_database' => 1,
            'deleted_housing_units_from_database' => 1,
            'deleted_buildings_from_arcgis' => 1,
            'deleted_housing_units_from_arcgis' => 1,
            'archived_before_database_deletion' => 2,
        ]);

    expect(Building::query()->whereKey($building->id)->exists())->toBeFalse()
        ->and(HousingUnit::query()->whereKey($housingUnit->id)->exists())->toBeFalse()
        ->and(BuildingStatus::query()->where('building_id', $building->objectid)->exists())->toBeFalse()
        ->and(BuildingStatusHistory::query()->where('building_id', $building->objectid)->exists())->toBeFalse()
        ->and(HousingStatus::query()->where('housing_id', $housingUnit->objectid)->exists())->toBeFalse()
        ->and(HousingStatusHistory::query()->where('housing_id', $housingUnit->objectid)->exists())->toBeFalse()
        ->and(AssignedAssessmentUser::query()->where('building_id', $building->objectid)->exists())->toBeFalse()
        ->and(EditAssessment::query()->whereIn('global_id', [$building->globalid, $housingUnit->globalid])->exists())->toBeFalse()
        ->and(BuildingSurveyArchiveObject::query()->where('source_type', 'building_deletion')->count())->toBe(2);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/FeatureServer/1/deleteFeatures')
        && str_contains((string) $request['objectIds'], (string) $housingUnit->objectid));

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/FeatureServer/0/deleteFeatures')
        && str_contains((string) $request['objectIds'], (string) $building->objectid));
});
