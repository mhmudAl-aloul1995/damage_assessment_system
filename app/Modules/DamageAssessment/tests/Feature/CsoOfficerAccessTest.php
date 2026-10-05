<?php

use App\Models\CsoSurvey;
use App\Models\User;
use Database\Seeders\DashboardCardSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-arcgis-token',
        ]),
        'https://services2.arcgis.com/*' => Http::response([
            'features' => [],
            'exceededTransferLimit' => false,
        ]),
    ]);
});

it('allows cso officers to access cso pages and assign cso audits', function (): void {
    app(RolesAndPermissionsSeeder::class)->run();
    app(DashboardCardSeeder::class)->run();

    $officer = User::factory()->create();
    $officer->assignRole('CSO Officer');

    $auditor = User::factory()->create();
    $auditor->assignRole(Role::findOrCreate('Inf - QC/QA Engineer', 'web'));

    $survey = CsoSurvey::query()->create([
        'objectid' => 1201,
        'globalid' => 'cso-officer-survey-1201',
        'building_name' => 'CSO Building',
        'organization_name' => 'CSO Organization',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'assignedto' => 'CSO Field Engineer',
        'field_status' => 'COMPLETED',
        'building_damage_status' => '2',
    ]);

    $this->actingAs($officer)->get(route('cso-surveys.index'))->assertOk();
    $this->actingAs($officer)->get(route('cso-surveys.export-data'))->assertOk();
    $this->actingAs($officer)->get(route('inf-audit.cso.index'))->assertOk();
    $this->actingAs($officer)->get(route('reports.area-productivity.cso-surveys'))->assertOk();

    $this->actingAs($officer)
        ->get(route('damageAssessment.index'))
        ->assertOk()
        ->assertSee(__('ui.damage_dashboard.cso_surveys'), false)
        ->assertDontSee(__('ui.damage_dashboard.buildings_status_summary'), false)
        ->assertViewHas('dashboardCards', fn ($cards): bool => $cards->pluck('key')->all() === ['cso_surveys']);

    $this->actingAs($officer)
        ->post(route('inf-audit.cso.assign'), [
            'ids' => [$survey->id],
            'assigned_to' => $auditor->id,
            'notes' => 'Assigned by CSO officer',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'تم إسناد 1 سجل بنجاح.');

    $this->actingAs($officer)
        ->get(route('reports.area-productivity.housing-units'))
        ->assertForbidden();

    $this->actingAs($officer)
        ->get(route('building-deletions.index'))
        ->assertForbidden();

    $this->actingAs($officer)
        ->getJson(route('damageAssessment.latest-stats'))
        ->assertForbidden();
});

it('blocks authenticated users without cso access from cso pages', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('cso-surveys.index'))->assertForbidden();
    $this->actingAs($user)->get(route('inf-audit.cso.index'))->assertForbidden();
});
