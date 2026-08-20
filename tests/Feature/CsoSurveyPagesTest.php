<?php

declare(strict_types=1);

use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\User;

test('it shows cso survey listing and details like other survey pages', function (): void {
    $user = User::factory()->create();

    $survey = CsoSurvey::query()->create([
        'objectid' => 7301,
        'globalid' => 'cso-survey-page-global-id',
        'organization_name' => 'Civil Support Organization',
        'building_name' => 'CSO Main Building',
        'assignedto' => 'Engineer CSO',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
        'building_damage_status' => 'partial_damage',
        'operational_status' => 'partially_operational',
        'creationdate' => '2026-08-19 19:55:00',
        'raw_payload' => [
            'weather' => 'sunny',
        ],
    ]);

    CsoSurveyOrganization::query()->create([
        'objectid' => 8301,
        'globalid' => 'cso-organization-page-global-id',
        'parentglobalid' => $survey->globalid,
        'organization_name_en' => 'Civil Support Organization Branch',
        'operational_status' => 'operational',
    ]);

    CsoSurveyUnit::query()->create([
        'objectid' => 9301,
        'globalid' => 'cso-unit-page-global-id',
        'parentglobalid' => $survey->globalid,
        'unit_name' => 'Ground Floor Unit',
        'unit_damage_status' => 'minor_damage',
    ]);

    $indexResponse = $this->actingAs($user)->get(route('cso-surveys.index'));

    $indexResponse->assertOk()
        ->assertSee('CSO Damage Assessment')
        ->assertSee('CSO Filters')
        ->assertSee('CSO Surveys')
        ->assertSee('Total Surveys');

    $dataResponse = $this->actingAs($user)->get(route('cso-surveys.data', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Rimal',
    ]));

    $dataResponse->assertOk()
        ->assertSee('Civil Support Organization')
        ->assertSee('CSO Main Building');

    $showResponse = $this->actingAs($user)->get(route('cso-surveys.show', $survey));

    $showResponse->assertOk()
        ->assertSee('Civil Support Organization')
        ->assertSee('Survey')
        ->assertSee('CSO Organizations')
        ->assertSee('Unit Information')
        ->assertSee('Civil Support Organization Branch')
        ->assertSee('Ground Floor Unit')
        ->assertSee('لا يوجد جواب');
});
