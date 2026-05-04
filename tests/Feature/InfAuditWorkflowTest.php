<?php

declare(strict_types=1);

use App\Models\InfEditAssessment;
use App\Models\PublicBuildingAuditHistory;
use App\Models\PublicBuildingFilter;
use App\Models\PublicBuildingSurvey;
use App\Models\PublicBuildingSurveyUnit;
use App\Models\RoadFacilityAuditHistory;
use App\Models\RoadFacilityFilter;
use App\Models\RoadFacilitySurvey;
use App\Models\RoadFacilitySurveyItem;
use App\Models\User;
use Database\Seeders\InfAuditRolesSeeder;
use Database\Seeders\InfAuditStatusesSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::query()->firstOrCreate(['name' => 'Database Officer']);

    $this->seed([
        InfAuditRolesSeeder::class,
        InfAuditStatusesSeeder::class,
    ]);
});

function infAuditUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('database officer can assign and inf engineer can audit public building and units', function (): void {
    $officer = infAuditUser('Database Officer');
    $engineer = infAuditUser('Inf - QC/QA Engineer');

    PublicBuildingFilter::query()->create([
        'list_name' => 'building_damage_status',
        'name' => 'Moderate',
        'label' => 'ضرر متوسط',
        'sort_order' => '1',
    ]);

    PublicBuildingFilter::query()->create([
        'list_name' => 'building_damage_status',
        'name' => 'Severe',
        'label' => 'ضرر كبير',
        'sort_order' => '2',
    ]);

    $survey = PublicBuildingSurvey::query()->create([
        'objectid' => 149,
        'globalid' => 'public-building-global-id',
        'building_name' => 'Public Building',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Sabra',
        'raw_payload' => ['building_damage_status' => 'Moderate'],
    ]);

    $unit = PublicBuildingSurveyUnit::query()->create([
        'objectid' => 1149,
        'globalid' => 'public-building-unit-global-id',
        'parentglobalid' => $survey->globalid,
        'unit_name' => 'Floor 1',
        'floor_number' => 1,
        'raw_payload' => ['dm1' => '12'],
    ]);

    $this->actingAs($officer)
        ->postJson(route('inf-audit.public-buildings.assign'), [
            'ids' => [$survey->id],
            'assigned_to' => $engineer->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('public_building_audit_statuses', [
        'public_building_survey_id' => $survey->id,
        'assigned_to' => $engineer->id,
    ]);

    $this->assertDatabaseHas('inf_audit_assignments', [
        'type' => 'public_building',
        'globalid' => $survey->globalid,
        'user_id' => $engineer->id,
        'manager_id' => $officer->id,
    ]);

    $this->actingAs($officer)
        ->getJson(route('inf-audit.public-buildings.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'objectid' => '149',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonFragment([
            'objectid' => 149,
        ]);

    $this->actingAs($engineer)
        ->get(route('inf-audit.public-buildings.show', $survey))
        ->assertOk()
        ->assertSee('Public Building')
        ->assertSee('Floor 1')
        ->assertSee('معلومات الإسناد')
        ->assertSee('ضرر متوسط')
        ->assertSee($engineer->name);

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.public-buildings.field-update', $survey), [
            'table_type' => 'public_building_table',
            'auditable_id' => $survey->id,
            'field_name' => 'building_name',
            'field_value' => 'Edited Public Building',
        ])
        ->assertOk();

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.public-buildings.field-update', $survey), [
            'table_type' => 'public_building_table',
            'auditable_id' => $survey->id,
            'field_name' => 'building_damage_status',
            'field_value' => 'Severe',
        ])
        ->assertOk()
        ->assertJsonPath('display_value', 'ضرر كبير');

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.public-buildings.field-update', $survey), [
            'table_type' => 'public_building_unit_table',
            'auditable_id' => $unit->id,
            'field_name' => 'unit_name',
            'field_value' => 'Edited Floor 1',
        ])
        ->assertOk();

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.public-buildings.children.store', $survey))
        ->assertOk()
        ->assertJsonPath('reload', true);

    expect(PublicBuildingSurveyUnit::query()->where('parentglobalid', $survey->globalid)->count())->toBe(2);

    $addedUnit = PublicBuildingSurveyUnit::query()->where('parentglobalid', $survey->globalid)->latest('id')->first();

    if (Schema::hasColumn('public_building_survey_units', 'creationdate')) {
        expect($addedUnit?->creationdate)->not->toBeNull();
    }

    if (Schema::hasColumn('public_building_survey_units', 'editdate')) {
        expect($addedUnit?->editdate)->not->toBeNull();
    }

    $this->assertDatabaseHas('inf_edit_assessments', [
        'table_type' => 'public_building_table',
        'field_name' => 'building_name',
        'field_value' => 'Edited Public Building',
    ]);

    $this->assertDatabaseHas('inf_edit_assessments', [
        'table_type' => 'public_building_unit_table',
        'field_name' => 'unit_name',
        'field_value' => 'Edited Floor 1',
    ]);

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.public-buildings.status', $survey), [
            'status' => 'accepted',
        ])
        ->assertOk();

    $historyCount = PublicBuildingAuditHistory::query()->where('public_building_survey_id', $survey->id)->count();

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.public-buildings.status', $survey), [
            'status' => 'accepted',
        ])
        ->assertOk();

    expect(PublicBuildingAuditHistory::query()->where('public_building_survey_id', $survey->id)->count())->toBe($historyCount);
});

test('database officer can assign and inf engineer can audit road facilities and items', function (): void {
    $officer = infAuditUser('Database Officer');
    $engineer = infAuditUser('Inf - QC/QA Engineer');

    RoadFacilityFilter::query()->create([
        'list_name' => 'road_damage_level',
        'name' => 'High',
        'label' => 'ضرر عالي',
        'sort_order' => 1,
    ]);

    RoadFacilityFilter::query()->create([
        'list_name' => 'road_damage_level',
        'name' => 'Low',
        'label' => 'ضرر منخفض',
        'sort_order' => 2,
    ]);

    RoadFacilityFilter::query()->create([
        'list_name' => 'unit',
        'name' => 'm2',
        'label' => 'متر مربع',
        'sort_order' => 1,
    ]);

    $road = RoadFacilitySurvey::query()->create([
        'objectid' => 4361,
        'globalid' => 'road-facility-global-id',
        'str_name' => 'Main Road',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Old City',
        'raw_payload' => ['road_damage_level' => 'High'],
    ]);

    $item = RoadFacilitySurveyItem::query()->create([
        'objectid' => 5361,
        'globalid' => 'road-facility-item-global-id',
        'parentglobalid' => $road->globalid,
        'item_required' => 'Asphalt',
        'raw_payload' => ['quantity_001' => 10, 'unit_001' => 'm2'],
    ]);

    $this->actingAs($officer)
        ->postJson(route('inf-audit.roads.assign'), [
            'ids' => [$road->id],
            'assigned_to' => $engineer->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('inf_audit_assignments', [
        'type' => 'road_facility',
        'globalid' => $road->globalid,
        'user_id' => $engineer->id,
        'manager_id' => $officer->id,
    ]);

    $this->actingAs($officer)
        ->getJson(route('inf-audit.roads.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'objectid' => '4361',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonFragment([
            'objectid' => 4361,
        ]);

    $this->actingAs($engineer)
        ->get(route('inf-audit.roads.show', $road))
        ->assertOk()
        ->assertSee('Main Road')
        ->assertSee('Asphalt')
        ->assertSee('ضرر عالي')
        ->assertSee('متر مربع')
        ->assertSee('معلومات الإسناد');

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.roads.field-update', $road), [
            'table_type' => 'road_facility_table',
            'auditable_id' => $road->id,
            'field_name' => 'str_name',
            'field_value' => 'Edited Main Road',
        ])
        ->assertOk();

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.roads.field-update', $road), [
            'table_type' => 'road_facility_table',
            'auditable_id' => $road->id,
            'field_name' => 'road_damage_level',
            'field_value' => 'Low',
        ])
        ->assertOk()
        ->assertJsonPath('display_value', 'ضرر منخفض')
        ->assertJsonPath('history.0.field_value', 'ضرر منخفض');

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.roads.field-update', $road), [
            'table_type' => 'road_facility_item_table',
            'auditable_id' => $item->id,
            'field_name' => 'unit_001',
            'field_value' => 'm2',
        ])
        ->assertOk()
        ->assertJsonPath('display_value', 'متر مربع');

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.roads.field-update', $road), [
            'table_type' => 'road_facility_item_table',
            'auditable_id' => $item->id,
            'field_name' => 'item_required',
            'field_value' => 'Edited Asphalt',
        ])
        ->assertOk();

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.roads.children.store', $road))
        ->assertOk()
        ->assertJsonPath('reload', true);

    expect(RoadFacilitySurveyItem::query()->where('parentglobalid', $road->globalid)->count())->toBe(2);

    $addedItem = RoadFacilitySurveyItem::query()->where('parentglobalid', $road->globalid)->latest('id')->first();

    if (Schema::hasColumn('road_facility_survey_items', 'creationdate')) {
        expect($addedItem?->creationdate)->not->toBeNull();
    }

    if (Schema::hasColumn('road_facility_survey_items', 'editdate')) {
        expect($addedItem?->editdate)->not->toBeNull();
    }

    expect(InfEditAssessment::query()->where('table_type', 'road_facility_item_table')->where('field_name', 'item_required')->exists())->toBeTrue();

    $this->actingAs($engineer)
        ->postJson(route('inf-audit.roads.status', $road), [
            'status' => 'need_review',
        ])
        ->assertOk();

    expect(RoadFacilityAuditHistory::query()->where('road_facility_survey_id', $road->id)->count())->toBe(2);
});
