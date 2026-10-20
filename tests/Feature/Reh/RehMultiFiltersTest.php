<?php

use App\Models\User;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['reh.view', 'reh.export', 'reh.sensitive', 'reh.scores.view']);
    $this->actingAs($this->user);
});

it('combines OR choices inside each filter with AND across filters on all export and map endpoints', function (): void {
    foreach (['yes', 'no', 'unknown', null] as $index => $electricity) {
        $building = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED', 'Old_objectID' => 100 + $index, 'electricity_available' => $electricity]]);
        RehUnit::factory()->create(['building_id' => $building->id, 'source' => ['OldUnit_objectID' => 500 + $index, 'ownership_proof_status' => $index === 0 ? 'yes' : 'yes_unavailable'], 'code' => 'MULTI-'.$index, 'workflow_status' => 'ready']);
        RehUnit::factory()->create(['building_id' => $building->id, 'source' => ['ownership_proof_status' => 'no'], 'code' => 'WRONG-PROOF-'.$index, 'workflow_status' => 'ready']);
    }
    $filters = ['sf_electricity_available' => ['yes', '__missing'], 'sf_ownership_proof_status' => ['yes', 'yes_unavailable'], 'status' => ['ready', 'returned'], 'survey_status' => ['completed']];
    foreach (['reh.data', 'reh.exports.data'] as $route) {
        $this->getJson(route($route, $filters))->assertOk()->assertJsonPath('total_units', 2)->assertJsonPath('total_buildings', 2);
    }
    $html = $this->getJson(route('reh.data', [...$filters, 'tab' => 'buildings']))->assertOk()->json('html');
    expect($html)->toContain('500', '503')->not->toContain('WRONG-PROOF');
    $csv = $this->get(route('reh.export', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain('MULTI-0', 'MULTI-3')->not->toContain('MULTI-1', 'MULTI-2', 'WRONG-PROOF');
    $response = $this->get(route('reh.export.excel', $filters))->assertOk();
    Cell::setValueBinder(new DefaultValueBinder);
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    expect($book->getActiveSheet()->getHighestRow())->toBe(3);
    $book->disconnectWorksheets();
    $captured = null;
    View::composer('reh::pdf.results', function ($view) use (&$captured): void {
        $captured = $view->getData();
    });
    $this->get(route('reh.export.pdf', $filters))->assertOk();
    expect($captured['rows'])->toHaveCount(2)->and(implode(' ', $captured['filterSummary']))->toContain('نعم، غير مسجل', 'نعم، غير متاح');
});

it('supports multiple parent survey states with multiple engineers and governors', function (): void {
    foreach (['COMPLETED', 'Not_Completed', null] as $index => $state) {
        $building = RehBuilding::factory()->create(['engineer' => 'eng'.($index + 1), 'source' => ['Field_status' => $state]]);
        RehUnit::factory()->create(['building_id' => $building->id]);
    }
    $this->getJson(route('reh.data', ['survey_status' => ['completed', 'not_completed'], 'engineer' => ['eng1', 'eng2'], 'governorate' => ['khan_younis', 'middle_area']]))->assertOk()->assertJsonPath('total_units', 2);
    $this->getJson(route('reh.data', ['survey_status' => ['all']]))->assertOk()->assertJsonPath('total_units', 3);
});

it('supports any selected stored token and missing while keeping exact token boundaries', function (): void {
    foreach (['see walk', 'hear', null, 'see_extra'] as $value) {
        RehUnit::factory()->create(['source' => ['owner_disability_types' => $value, 'owner_has_disability' => 'yes']]);
    }
    $this->getJson(route('reh.data', ['sf_owner_disability_types' => ['see', 'hear', '__missing'], 'owner_disability' => ['yes', 'no']]))->assertOk()->assertJsonPath('total_units', 3);
    $this->getJson(route('reh.data', ['sf_current_unit_occupancy' => ['owner_family', 'empty'], 'sf_vacancy_reason' => ['cant_afford', 'displaced_tent']]))->assertOk();
    $this->getJson(route('reh.data', ['sf_current_unit_occupancy' => ['owner_family'], 'sf_vacancy_reason' => ['cant_afford']]))->assertUnprocessable()->assertJsonValidationErrors('sf_vacancy_reason');
});

it('supports multiple work items with independent alternatives and shared quantity bounds', function (): void {
    $fields = array_keys(app(\App\Modules\Reh\Services\RehSurveyFilters::class)->workItems());
    RehUnit::factory()->create(['source' => [$fields[0] => 4.5, $fields[1] => 0]]);
    RehUnit::factory()->create(['source' => [$fields[0] => 0, $fields[1] => 5.5]]);
    RehUnit::factory()->create(['source' => [$fields[0] => null, $fields[1] => null]]);
    $this->getJson(route('reh.data', ['work_item' => array_slice($fields, 0, 2), 'work_state' => ['positive'], 'work_min' => 4, 'work_max' => 6]))->assertOk()->assertJsonPath('total_units', 2);
    $this->getJson(route('reh.data', ['work_item' => array_slice($fields, 0, 2), 'work_state' => ['positive', 'missing']]))->assertOk()->assertJsonPath('total_units', 3);
});

it('rejects malformed and invalid arrays without exposing sensitive filtering', function (): void {
    foreach (['sf_electricity_available' => ['yes', 'invalid'], 'work_item' => ['source->private'], 'status' => ['ready', 'invalid'], 'engineer' => [['nested']], 'decision' => ['selected', 'selected']] as $key => $value) {
        $this->getJson(route('reh.data', [$key => $value]))->assertUnprocessable();
    }
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($viewer)->getJson(route('reh.exports.data', ['sf_owner_age_min' => 0]))->assertForbidden();
    $this->getJson(route('reh.export.excel', ['owner_disability' => ['yes', 'no']]))->assertForbidden();
    $this->getJson(route('reh.export.pdf', ['priority' => ['high', 'low']]))->assertForbidden();
});

it('renders multiple select2 filters identically for the workspace and export page', function (): void {
    foreach (['reh.index', 'reh.exports'] as $route) {
        $html = $this->get(route($route, ['sf_electricity_available' => ['yes', 'no']]))->assertOk()->getContent();
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ((new DOMXPath($doc))->query('//form[@id="reh-filters"]//select') as $select) {
            expect($select->hasAttribute('multiple'))->toBeTrue()->and($select->getAttribute('data-control'))->toBe('select2');
        }
    }
});
