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
        foreach (array_keys(\App\Modules\Reh\Services\RehAdvancedFilters::conditions()) as $key) {
            expect((new DOMXPath($doc))->query('//*[@data-filter-condition="'.$key.'"]//*[@data-condition-trigger]')->length)->toBe(1);
        }
        expect($html)->not->toContain('استخدم كلمة محددة بدل «هل».', 'reh-filter-search-help');
        $xpath = new DOMXPath($doc);
        $quantity = $xpath->query('//*[@data-survey-condition="sf_boq_1_1_water_tank_repair_qty"]')->item(0);
        expect($quantity->hasAttribute('data-condition-lazy'))->toBeTrue()
            ->and($quantity->getAttribute('data-condition-expanded'))->toBe('false')
            ->and($xpath->query('.//*[@data-condition-label]', $quantity)->item(0)->textContent)->toBe('إضافة شرط')
            ->and($xpath->query('.//*[@data-condition-range and @hidden]//input[@disabled]', $quantity)->length)->toBe(2)
            ->and($xpath->query('.//small[contains(@class,"reh-condition-context")]', $quantity)->length)->toBe(0);
    }
});

it('restores compact quantity controls for saved single range and presence filters', function (): void {
    $key = 'sf_boq_1_1_water_tank_repair_qty';
    foreach (['reh.index', 'reh.exports'] as $route) {
        foreach ([
            [[$key.'_min' => 0, $key.'_max' => 5], 'بين قيمتين', false, true],
            [[$key.'_op' => 'gt', $key => 0], 'أكبر من', true, false],
            [[$key.'_op' => 'blank'], 'فارغ', true, true],
        ] as [$filters, $label, $rangeHidden, $singleHidden]) {
            $html = $this->get(route($route, $filters))->assertOk()->getContent();
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $field = $xpath->query('//*[@data-filter-condition="'.$key.'"]')->item(0);
            expect($field->getAttribute('data-condition-expanded'))->toBe('true')
                ->and($xpath->query('.//*[@data-condition-label]', $field)->item(0)->textContent)->toBe($label)
                ->and($xpath->query('.//*[@data-condition-range]', $field)->item(0)->hasAttribute('hidden'))->toBe($rangeHidden)
                ->and($xpath->query('.//*[@data-condition-single]', $field)->item(0)->hasAttribute('hidden'))->toBe($singleHidden)
                ->and($xpath->query('.//*[@data-condition-clear]', $field)->item(0)->hasAttribute('hidden'))->toBeFalse();
        }
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
    $filters = ['q' => 'CONDITION-MATCH', 'q_op' => 'is', 'sf_engineer_technical_notes_op' => 'is_not', 'sf_engineer_technical_notes' => 'replace'];
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

it('applies main search operators literally across unit and building fields and map results', function (): void {
    foreach ([['بيت اول', 2345, 'CODE-PART-100'], ['بيت ثان', 3456, 'CODE-PART-200'], ['literal%_', 5555, null]] as [$name, $reference, $code]) {
        $building = RehBuilding::factory()->create(['name' => $name, 'source' => ['Field_status' => 'COMPLETED', 'Old_objectID' => $reference]]);
        RehUnit::factory()->create(['building_id' => $building->id, 'code' => $code, 'owner_name' => null, 'owner_identity' => null]);
    }
    foreach ([['contains', 'CODE-PART', 2], ['is', 'CODE-PART-100', 1], ['not_contains', 'CODE-PART', 1], ['contains', '%_', 1], ['contains', '345', 2], ['is', '3456', 1]] as [$operator, $value, $count]) {
        foreach (['units', 'buildings'] as $tab) {
            $this->getJson(route('reh.data', ['tab' => $tab, 'q_op' => $operator, 'q' => $value]))->assertOk()->assertJsonPath('total_units', $count)->assertJsonPath('total_buildings', $count);
        }
    }
});

it('supports legacy field operators for text choices missing values numbers and dates', function (): void {
    foreach ([[1234, 'COMPLETED', 'ready', 0, 0, '2026-10-01'], [1256, 'Not_Completed', 'returned', 5, 1, '2026-10-20'], [null, null, 'new', null, null, null]] as [$reference, $status, $workflow, $size, $chronic, $visit]) {
        $building = RehBuilding::factory()->create(['source' => ['Old_objectID' => $reference, 'Field_status' => $status, 'visit_date' => $visit]]);
        RehUnit::factory()->create(['building_id' => $building->id, 'workflow_status' => $workflow, 'source' => ['household_size' => $size, 'household_chronic_count' => $chronic]]);
    }
    foreach ([
        [['survey_status_op' => 'is_not', 'survey_status' => ['completed']], 2],
        [['survey_status_op' => 'is_not', 'survey_status' => ['all']], 0],
        [['status_op' => 'is_not', 'status' => ['ready', 'returned']], 1],
        [['building_old_objectid_op' => 'contains', 'building_old_objectid' => '12'], 2],
        [['building_old_objectid_op' => 'not_contains', 'building_old_objectid' => '34'], 1],
        [['building_old_objectid_op' => 'blank'], 1],
        [['household_op' => 'is', 'household' => 0], 1],
        [['household_op' => 'gt', 'household' => 0], 1],
        [['household_op' => 'blank'], 1],
        [['family_chronic_op' => 'is_not', 'family_chronic' => ['yes']], 1],
        [['family_chronic_op' => 'blank'], 1],
        [['visit_op' => 'gt', 'visit' => '2026-10-01'], 1],
        [['visit_op' => 'blank'], 1],
    ] as [$filters, $count]) {
        $response = $this->getJson(route('reh.data', ['survey_status' => 'all', ...$filters]))->assertOk()->assertJsonPath('total_units', $count);
        if (isset($filters['building_old_objectid_op']) && $filters['building_old_objectid_op'] !== 'is') {
            expect($response->json('html'))->not->toContain('النتائج مقيّدة بالمبنى');
        }
    }
});

it('keeps buildings with matching units visible when a different unit contains the excluded search text', function (): void {
    $building = RehBuilding::factory()->create(['name' => 'المنزل']);
    RehUnit::factory()->create(['building_id' => $building->id, 'code' => 'FIND-ME', 'owner_name' => null]);
    $matching = RehUnit::factory()->create(['building_id' => $building->id, 'code' => 'KEEP-ME', 'owner_name' => null]);
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings', 'q_op' => 'not_contains', 'q' => 'FIND-ME']))->assertOk()->assertJsonPath('total_units', 1)->assertJsonPath('total_buildings', 1)->json('html');
    preg_match('/data-buildings="([^"]+)"/', $html, $matches);
    $map = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);
    expect($map[0]['units'])->toHaveCount(1)
        ->and($map[0]['units'][0]['url'])->toBe(route('reh.units.show', $matching));
});

it('rejects unsupported core operators conflicting bounds and sensitive operator probes', function (): void {
    foreach ([['q_op' => 'gt', 'q' => '1'], ['q_op' => 'is'], ['status_op' => 'contains', 'status' => ['new']], ['household_op' => 'gt', 'household' => 1, 'household_min' => 2], ['visit_op' => 'blank', 'visit_from' => '2026-10-01']] as $filters) {
        $this->getJson(route('reh.data', $filters))->assertUnprocessable();
    }
    $reader = User::factory()->create();
    $reader->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($reader);
    foreach (['reh.data', 'reh.exports.data', 'reh.export', 'reh.export.excel', 'reh.export.pdf'] as $route) {
        foreach (['household_op', 'family_chronic_op', 'score_op', 'priority_op'] as $key) {
            $this->getJson(route($route, [$key => 'blank']))->assertForbidden();
        }
    }
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
