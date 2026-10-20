<?php

use App\Models\User;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehSurveyColumns;
use App\Modules\Reh\Services\RehSurveyFilters;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['reh.view', 'reh.export', 'reh.scores.view', 'reh.sensitive']);
    $this->actingAs($this->user);
});

it('combines building services and unit filters across counts map csv and pdf', function (): void {
    $building = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED', 'electricity_available' => 'unknown', 'water_network_connected' => 'yes', 'social_worker_name' => 'res2', 'Old_objectID' => 2328]]);
    RehUnit::factory()->create(['building_id' => $building->id, 'code' => 'SURVEY-MATCH', 'source' => ['OldUnit_objectID' => 5087, 'ownership_proof_status' => 'yes_unavailable', 'unit_area_sqm' => 65.5, 'current_unit_occupancy' => 'empty', 'vacancy_reason' => 'cant_afford']]);
    RehUnit::factory()->create(['building_id' => $building->id, 'code' => 'WRONG-PROOF', 'source' => ['ownership_proof_status' => 'yes', 'unit_area_sqm' => 65.5, 'current_unit_occupancy' => 'empty', 'vacancy_reason' => 'cant_afford']]);
    $filters = ['sf_electricity_available' => 'unknown', 'sf_water_network_connected' => 'yes', 'sf_social_worker_name' => 'res2', 'sf_ownership_proof_status' => 'yes_unavailable', 'sf_unit_area_sqm_min' => 65.5, 'sf_unit_area_sqm_max' => 65.5, 'sf_vacancy_reason' => 'cant_afford'];
    $this->getJson(route('reh.data', $filters))->assertOk()->assertJsonPath('total_units', 1)->assertJsonPath('total_buildings', 1);
    $html = $this->getJson(route('reh.data', [...$filters, 'tab' => 'buildings']))->assertOk()->assertJsonPath('total_buildings', 1)->json('html');
    expect($html)->toContain('5087')->not->toContain('WRONG-PROOF');
    expect($this->get(route('reh.export', $filters))->assertOk()->streamedContent())->toContain('SURVEY-MATCH')->not->toContain('WRONG-PROOF');
    $captured = null;
    View::composer('reh::pdf.results', function ($view) use (&$captured): void {
        $captured = $view->getData();
    });
    $this->get(route('reh.export.pdf', $filters))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($captured['rows'])->toHaveCount(1)->and(implode(' ', $captured['filterSummary']))->toContain('غير معروف', 'نعم، غير متاح', 'صفية الاسطل');
});

it('distinguishes unknown no and missing answers', function (string $value, string $code): void {
    foreach (['unknown' => 'ANSWER-UNKNOWN', 'no' => 'ANSWER-NO', '__missing' => 'ANSWER-MISSING'] as $answer => $name) {
        $building = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED', 'electricity_available' => $answer === '__missing' ? null : $answer]]);
        RehUnit::factory()->create(['building_id' => $building->id, 'code' => $name]);
    }
    $html = $this->getJson(route('reh.data', ['sf_electricity_available' => $value]))->assertOk()->assertJsonPath('total_units', 1)->json('html');
    expect($html)->toContain($code);
})->with([['unknown', 'ANSWER-UNKNOWN'], ['no', 'ANSWER-NO'], ['__missing', 'ANSWER-MISSING']]);

it('matches exact multiple choice tokens with spaces commas and line breaks', function (string $value): void {
    RehUnit::factory()->create(['source' => ['owner_has_disability' => 'yes', 'owner_disability_types' => $value]]);
    RehUnit::factory()->create(['source' => ['owner_has_disability' => 'yes', 'owner_disability_types' => 'selfcare see_extra']]);
    $this->getJson(route('reh.data', ['sf_owner_disability_types' => 'see']))->assertOk()->assertJsonPath('total_units', 1);
})->with(['see walk', 'walk,see', "walk\nsee"]);

it('filters field work quantities by their own unit with decimal bounds zero and missing', function (): void {
    $field = array_key_first(app(RehSurveyFilters::class)->workItems());
    foreach ([4.5, 0, null] as $quantity) {
        RehUnit::factory()->create(['source' => [$field => $quantity]]);
    }
    foreach (['positive', 'zero', 'missing'] as $state) {
        $filters = ['work_item' => $field, 'work_state' => $state];
        if ($state === 'positive') {
            $filters += ['work_min' => 4.5, 'work_max' => 4.5];
        }
        $this->getJson(route('reh.data', $filters))->assertOk()->assertJsonPath('total_units', 1);
    }
    $this->getJson(route('reh.data', ['work_item' => 'source->arbitrary', 'work_state' => 'positive']))->assertUnprocessable()->assertJsonValidationErrors('work_item');
    $this->getJson(route('reh.data', ['work_item' => $field, 'work_state' => 'missing', 'work_min' => 0]))->assertUnprocessable()->assertJsonValidationErrors('work_min');
});

it('enforces scope permissions and valid dependencies on every result endpoint', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($viewer);
    foreach (['reh.index', 'reh.data', 'reh.export', 'reh.export.pdf'] as $route) {
        $this->getJson(route($route, ['sf_owner_age_min' => 0]))->assertForbidden();
        $this->getJson(route($route, ['sf_nonowner_has_disability' => 'no']))->assertForbidden();
    }
    $this->get(route('reh.index'))->assertOk()->assertSee('id="reh-filter-search"', false)->assertDontSee('name="sf_owner_age_min"', false)->assertSee('name="sf_electricity_available"', false);
    $this->actingAs($this->user)->getJson(route('reh.data', ['sf_current_unit_occupancy' => 'owner_family', 'sf_vacancy_reason' => 'cant_afford']))->assertUnprocessable()->assertJsonValidationErrors('sf_vacancy_reason');
    $this->getJson(route('reh.data', ['sf_owner_age_min' => 70, 'sf_owner_age_max' => 10]))->assertUnprocessable()->assertJsonValidationErrors('sf_owner_age_max');
    $this->getJson(route('reh.data', ['sf_electricity_available' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('sf_electricity_available');
});

it('binds every offered field to an existing column and renders the survey choice labels', function (): void {
    foreach (app(RehSurveyFilters::class)->definitions() as $definition) {
        expect(RehSurveyColumns::FIELDS[$definition['scope']])->toHaveKey($definition['field']);
    }
    $this->get(route('reh.index'))->assertOk()->assertSee('نعم، غير متاح')->assertSee('غير معروف')->assertSee('يشمل الإجابة المختارة')->assertSee('أعمال التأهيل والكميات')->assertSee('استخدم كلمة محددة بدل «هل».')->assertSee('aria-describedby="reh-filter-search-help"', false);
});

it('supports missing legacy social answers without conflating them with no', function (): void {
    RehUnit::factory()->create(['source' => ['owner_has_disability' => null, 'hosts_other_households' => null, 'household_chronic_count' => null]]);
    RehUnit::factory()->create(['source' => ['owner_has_disability' => 'no', 'hosts_other_households' => 'no', 'household_chronic_count' => 0]]);
    foreach (['owner_disability', 'hosting', 'family_chronic'] as $key) {
        $this->getJson(route('reh.data', [$key => '__missing']))->assertOk()->assertJsonPath('total_units', 1);
        $this->getJson(route('reh.data', [$key => 'no']))->assertOk()->assertJsonPath('total_units', 1);
    }
});
