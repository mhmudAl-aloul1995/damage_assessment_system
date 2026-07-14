<?php

use App\Models\Assessment;
use App\Models\Building;
use App\Models\Filter;
use App\Models\HousingUnit;
use App\Models\User;

it('shows grouped housing unit filters from the assessment survey', function () {
    $user = User::factory()->create();

    seedHousingFilterOptions();

    Building::query()->create([
        'objectid' => 2001,
        'globalid' => 'building-housing-1',
        'assignedto' => 'Engineer One',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3001,
        'globalid' => 'housing-unit-1',
        'parentglobalid' => 'building-housing-1',
        'housing_unit_type' => 'apartment',
        'unit_damage_status' => 'fully_damaged2',
    ]);

    Assessment::query()->create([
        'name' => 'housing_unit_type',
        'label' => 'Housing unit type',
        'hint' => 'نوع الوحدة السكنية',
        'type' => '0',
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing');

    $response->assertOk();
    $response->assertSee('Housing Unit Filters');
    $response->assertSee('Unit information and damage');
    $response->assertSee('Resident and household');
    $response->assertSee('Support and safety');
    $response->assertSee('Apartment');
    $response->assertSee('Totally Damaged');
    $response->assertSee('Engineer One');
});

it('filters housing unit datatable records using grouped filters and ranges', function () {
    $user = User::factory()->create();

    seedHousingFilterOptions();

    Building::query()->create([
        'objectid' => 2001,
        'globalid' => 'building-housing-1',
        'assignedto' => 'Engineer One',
    ]);

    Building::query()->create([
        'objectid' => 2002,
        'globalid' => 'building-housing-2',
        'assignedto' => 'Engineer Two',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3001,
        'globalid' => 'housing-unit-1',
        'parentglobalid' => 'building-housing-1',
        'housing_unit_type' => 'apartment',
        'unit_damage_status' => 'fully_damaged2',
        'housing_unit_number' => '12',
        'q_9_3_1_first_name' => 'Mona',
        'q_9_3_4_last_name' => 'Saleh',
        'id_number1' => '900123456',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'floor_number' => 3,
        'damaged_area_m2' => 80,
        'unit_support_needed' => 'yes',
        'rubble_removal_is_needed' => 'yes',
        'activation_of_uxo_ha_d_material_clearance' => 'no',
        'has_fire' => 'no',
        'editdate' => '2026-06-02 10:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3002,
        'globalid' => 'housing-unit-2',
        'parentglobalid' => 'building-housing-2',
        'housing_unit_type' => 'warehouse',
        'unit_damage_status' => 'partially_damaged2',
        'housing_unit_number' => '2',
        'q_9_3_1_first_name' => 'Hani',
        'q_9_3_4_last_name' => 'Nassar',
        'id_number1' => '800123456',
        'municipalitie' => 'North Gaza',
        'neighborhood' => 'Camp',
        'floor_number' => 1,
        'damaged_area_m2' => 20,
        'unit_support_needed' => 'no',
        'rubble_removal_is_needed' => 'no',
        'activation_of_uxo_ha_d_material_clearance' => 'no',
        'has_fire' => 'no',
        'editdate' => '2026-06-03 10:00:00',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'unit_damage_status' => ['fully_damaged2'],
            'housing_unit_type' => ['apartment'],
            'municipalitie' => ['Gaza'],
            'damaged_area_m2_from' => 50,
            'unsafe_column' => 'ignored',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Mona Saleh');
    $response->assertSee('Totally Damaged');
    $response->assertSee('Support needed');
    $response->assertSee('Rubble removal');
    $response->assertDontSee('Hani Nassar');
});

it('filters housing unit datatable records by housing unit objectid', function () {
    $user = User::factory()->create();

    HousingUnit::query()->create([
        'objectid' => 3001,
        'globalid' => 'housing-unit-objectid-1',
        'housing_unit_number' => '12',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3002,
        'globalid' => 'housing-unit-objectid-2',
        'housing_unit_number' => '13',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'objectid' => '3001',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('12');
    expect(collect($response->json('data'))->pluck('housing_unit_number')->all())
        ->toBe(['12']);
});

it('filters housing unit datatable records by submission date range', function () {
    $user = User::factory()->create();

    HousingUnit::query()->create([
        'objectid' => 3101,
        'globalid' => 'housing-unit-submission-date-inside',
        'housing_unit_number' => '21',
        'q_9_3_1_first_name' => 'Inside',
        'q_9_3_4_last_name' => 'Range',
        'building_submit_date' => '2026-06-15 09:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3102,
        'globalid' => 'housing-unit-submission-date-outside',
        'housing_unit_number' => '22',
        'q_9_3_1_first_name' => 'Outside',
        'q_9_3_4_last_name' => 'Range',
        'building_submit_date' => '2026-06-30 09:00:00',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'submission_date_from' => '2026-06-01',
            'submission_date_to' => '2026-06-20',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Inside Range');
    $response->assertDontSee('Outside Range');
});

it('filters housing unit datatable records by the assigned building researcher', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 2201,
        'globalid' => 'building-assigned-one',
        'assignedto' => 'Engineer One',
    ]);

    Building::query()->create([
        'objectid' => 2202,
        'globalid' => 'building-assigned-two',
        'assignedto' => 'Engineer Two',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3201,
        'globalid' => 'housing-unit-assigned-one',
        'parentglobalid' => 'building-assigned-one',
        'housing_unit_number' => '31',
        'q_9_3_1_first_name' => 'Assigned',
        'q_9_3_4_last_name' => 'One',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3202,
        'globalid' => 'housing-unit-assigned-two',
        'parentglobalid' => 'building-assigned-two',
        'housing_unit_number' => '32',
        'q_9_3_1_first_name' => 'Assigned',
        'q_9_3_4_last_name' => 'Two',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'assignedto' => ['Engineer One'],
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Assigned One');
    $response->assertSee('Engineer One');
    $response->assertDontSee('Assigned Two');
    $response->assertDontSee('Engineer Two');
});

it('filters housing unit datatable records by the building save date', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 2301,
        'globalid' => 'building-save-date-inside',
        'assignedto' => 'Engineer Inside',
        'end' => '2026-06-15 09:00:00',
    ]);

    Building::query()->create([
        'objectid' => 2302,
        'globalid' => 'building-save-date-outside',
        'assignedto' => 'Engineer Outside',
        'end' => '2026-06-30 09:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3301,
        'globalid' => 'housing-unit-save-date-inside',
        'parentglobalid' => 'building-save-date-inside',
        'housing_unit_number' => '41',
        'q_9_3_1_first_name' => 'Saved',
        'q_9_3_4_last_name' => 'Inside',
    ]);

    HousingUnit::query()->create([
        'objectid' => 3302,
        'globalid' => 'housing-unit-save-date-outside',
        'parentglobalid' => 'building-save-date-outside',
        'housing_unit_number' => '42',
        'q_9_3_1_first_name' => 'Saved',
        'q_9_3_4_last_name' => 'Outside',
    ]);

    $query = http_build_query([
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'filters' => [
            'end_from' => '2026-06-01',
            'end_to' => '2026-06-20',
        ],
    ]);

    $response = $this->actingAs($user)->get('/damage-assessment/housing/show?'.$query);

    $response->assertOk();
    $response->assertJsonPath('recordsFiltered', 1);
    $response->assertSee('Saved Inside');
    $response->assertDontSee('Saved Outside');
});

function seedHousingFilterOptions(): void
{
    collect([
        ['housing_unit_type', 'apartment', 'Apartment'],
        ['housing_unit_type', 'warehouse', 'Warehouse'],
        ['unit_damage_status', 'fully_damaged2', 'Totally Damaged'],
        ['unit_damage_status', 'partially_damaged2', 'Partially Damaged'],
        ['sex', 'female', 'Female'],
        ['marital_status', 'Married', 'Married'],
        ['are_there_people_with_disability', 'yes', 'Yes'],
        ['is_refugee', 'yes', 'Yes'],
        ['the_unit_resident', 'owner2', 'Owner'],
        ['current_residence', 'rented2', 'Rented accommodation'],
        ['unit_support_needed', 'yes', 'Yes'],
        ['unit_support_needed', 'no', 'No'],
        ['rubble_removal_is_needed', 'yes', 'Yes'],
        ['rubble_removal_is_needed', 'no', 'No'],
        ['activation_of_uxo_ha_d_material_clearance', 'yes', 'Yes'],
        ['activation_of_uxo_ha_d_material_clearance', 'no', 'No'],
        ['is_the_housing_unit_or_living_habitable', 'yes', 'Yes'],
        ['is_the_housing_unit_or_living_habitable', 'no', 'No'],
    ])->each(function (array $option): void {
        Filter::query()->create([
            'list_name' => $option[0],
            'name' => $option[1],
            'label' => $option[2],
        ]);
    });
}
