<?php

use App\Models\User;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehSurvey;
use App\Modules\Reh\Services\RehSurveyFilters;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->reviewer = User::factory()->create();
    $this->reviewer->givePermissionTo(['reh.view', 'reh.export', 'reh.sensitive', 'reh.scores.view']);
    $this->actingAs($this->reviewer);
    Http::preventStrayRequests();
});

it('covers every survey field with an existing database column and a condition control', function (): void {
    $definitions = app(RehSurveyFilters::class)->definitions();
    $fields = app(RehSurvey::class)->dictionary()['fields'];
    expect(array_keys($definitions))->toEqual(array_map(fn (string $field): string => 'sf_'.$field, array_keys($fields)));
    $schema = (new RehUnit)->getConnection()->getSchemaBuilder();
    $columns = ['building' => $schema->getColumnListing('reh_buildings'), 'unit' => $schema->getColumnListing('reh_units')];
    foreach ($definitions as $definition) {
        expect($columns[$definition['scope']])->toContain($definition['column']);
    }
    foreach (['reh.index', 'reh.exports'] as $route) {
        $html = $this->get(route($route))->assertOk()->getContent();
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        expect((new DOMXPath($doc))->query('//*[@data-survey-condition]')->length)->toBe(count($fields));
    }
});

it('distinguishes missing unknown and negative categorical answers', function (): void {
    foreach (['yes', 'no', 'unknown', null, '   '] as $value) {
        $building = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED', 'electricity_available' => $value]]);
        RehUnit::factory()->create(['building_id' => $building->id]);
    }
    foreach ([['is', ['yes', 'no'], 2], ['is_not', ['yes'], 2], ['is_not', ['yes', 'no'], 1], ['blank', null, 2], ['not_blank', null, 3]] as [$operator, $value, $count]) {
        $filters = ['sf_electricity_available_op' => $operator];
        if ($value !== null) {
            $filters['sf_electricity_available'] = $value;
        }
        $this->getJson(route('reh.data', $filters))->assertOk()->assertJsonPath('total_units', $count);
    }
});

it('excludes exact multiple choice tokens without treating missing answers as no', function (): void {
    foreach (['see walk', 'hear,see', 'see_extra', 'walk', null] as $value) {
        RehUnit::factory()->create(['source' => ['owner_has_disability' => 'yes', 'owner_disability_types' => $value]]);
    }
    $this->getJson(route('reh.data', ['sf_owner_disability_types_op' => 'excludes', 'sf_owner_disability_types' => ['see']]))->assertOk()->assertJsonPath('total_units', 2);
    $this->getJson(route('reh.data', ['sf_owner_disability_types_op' => 'blank']))->assertOk()->assertJsonPath('total_units', 1);
});

it('compares quantities negative floors dates and timestamps using typed columns', function (): void {
    foreach ([[0, -1, '2026-10-01', '2026-10-01 08:30:00'], [4.5, 0, '2026-10-10', '2026-10-10 09:45:00'], [10.5, 1, '2026-10-20', '2026-10-20 10:00:00'], [null, 2, null, null]] as [$quantity, $floor, $date, $time]) {
        $building = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED', 'visit_date' => $date, 'submission_date' => $time]]);
        RehUnit::factory()->create(['building_id' => $building->id, 'source' => ['floor_number' => $floor, 'boq_3_8_tarp_wood_door_qty' => $quantity]]);
    }
    foreach ([
        ['sf_boq_3_8_tarp_wood_door_qty_op' => 'gt', 'sf_boq_3_8_tarp_wood_door_qty' => 4.5],
        ['sf_floor_number_op' => 'is', 'sf_floor_number' => -1],
        ['sf_visit_date_op' => 'between', 'sf_visit_date_min' => '2026-10-02', 'sf_visit_date_max' => '2026-10-15'],
        ['sf_submission_date_op' => 'is', 'sf_submission_date' => '2026-10-10T09:45'],
        ['sf_boq_3_8_tarp_wood_door_qty_op' => 'is', 'sf_boq_3_8_tarp_wood_door_qty' => 0],
    ] as $filters) {
        $this->getJson(route('reh.data', $filters))->assertOk()->assertJsonPath('total_units', 1);
    }
});

it('searches text literally and filters presence of photos and geometry', function (): void {
    foreach ([['literal%_note', 'photo.jpg', ['x' => 1, 'y' => 2]], ['literal-other-note', null, null], [null, null, []]] as [$note, $photo, $geometry]) {
        $building = RehBuilding::factory()->create(['geometry' => $geometry]);
        RehUnit::factory()->create(['building_id' => $building->id, 'source' => ['engineer_technical_notes' => $note, 'kitchen_photo' => $photo]]);
    }
    foreach ([
        ['sf_engineer_technical_notes_op' => 'contains', 'sf_engineer_technical_notes' => '%_'],
        ['sf_engineer_technical_notes_op' => 'not_contains', 'sf_engineer_technical_notes' => '%_'],
        ['sf_kitchen_photo_op' => 'not_blank'], ['sf_location_op' => 'not_blank'],
    ] as $filters) {
        $this->getJson(route('reh.data', $filters))->assertOk()->assertJsonPath('total_units', 1);
    }
    $this->getJson(route('reh.data', ['sf_location_op' => 'blank']))->assertOk()->assertJsonPath('total_units', 2);
});

it('shares conditions across map preview excel csv and pdf and preserves their descriptions', function (): void {
    RehUnit::factory()->create(['code' => 'CONDITION-MATCH', 'source' => ['engineer_technical_notes' => 'repair']]);
    RehUnit::factory()->create(['code' => 'CONDITION-OTHER', 'source' => ['engineer_technical_notes' => 'replace']]);
    $filters = ['sf_engineer_technical_notes_op' => 'is_not', 'sf_engineer_technical_notes' => 'replace'];
    $this->getJson(route('reh.data', [...$filters, 'tab' => 'buildings']))->assertOk()->assertJsonPath('total_units', 1);
    $this->getJson(route('reh.exports.data', $filters))->assertOk()->assertJsonPath('total_units', 1);
    $csv = $this->get(route('reh.export', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain('CONDITION-MATCH')->not->toContain('CONDITION-OTHER');
    $response = $this->get(route('reh.export.excel', $filters))->assertOk();
    \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    expect($book->getActiveSheet()->getHighestRow())->toBe(2)->and($book->getActiveSheet()->getCell('P2')->getValue())->toBe('CONDITION-MATCH');
    $book->disconnectWorksheets();
    $captured = null;
    View::composer('reh::pdf.results', function ($view) use (&$captured): void {
        $captured = $view->getData();
    });
    $this->get(route('reh.export.pdf', $filters))->assertOk();
    expect($captured['rows'])->toHaveCount(1)->and(implode(' ', $captured['filterSummary']))->toContain('لا يساوي', 'replace');
});

it('rejects invalid operators incomplete conditions conflicting values and hidden sensitive probes', function (): void {
    foreach ([['sf_unit_safe_to_enter_op' => 'gt', 'sf_unit_safe_to_enter' => 'yes'], ['sf_owner_age_op' => 'gt'], ['sf_owner_age_op' => 'blank', 'sf_owner_age_min' => 1], ['sf_unit_safe_to_enter_op' => 'blank', 'sf_unit_safe_to_enter' => ['yes']], ['sf_kitchen_photo_op' => 'contains', 'sf_kitchen_photo' => 'x'], ['sf_owner_age_op' => 'between', 'sf_owner_age_min' => 50, 'sf_owner_age_max' => 20]] as $filters) {
        $this->getJson(route('reh.data', $filters))->assertUnprocessable();
    }
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($viewer);
    foreach (['reh.data', 'reh.exports.data', 'reh.export.excel', 'reh.export.pdf'] as $route) {
        $this->getJson(route($route, ['sf_owner_identity_number_op' => 'blank']))->assertForbidden();
    }
    $this->get(route('reh.exports'))->assertOk()->assertDontSee('name="sf_owner_identity_number"', false)->assertDontSee('name="sf_owner_identity_number_op"', false);
});
