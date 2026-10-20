<?php

use App\Models\User;
use App\Modules\Reh\Models\RehUnit;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->exporter = User::factory()->create();
    $this->exporter->givePermissionTo(['reh.view', 'reh.export', 'reh.sensitive', 'reh.scores.view']);
    $this->actingAs($this->exporter);
    Http::preventStrayRequests();
});

it('exports only the requested columns in order with matching headings and spreadsheet styling', function (): void {
    RehUnit::factory()->create(['owner_identity' => '001234567', 'code' => 'COLUMNS-MATCH', 'source' => ['OldUnit_objectID' => '005087']]);
    RehUnit::factory()->create(['code' => 'COLUMNS-OUTSIDE']);
    $filters = ['q' => 'COLUMNS-MATCH', 'columns' => ['identity', 'unit_reference', 'unit_code']];
    $response = $this->get(route('reh.export.excel', $filters))->assertOk();
    Cell::setValueBinder(new DefaultValueBinder);
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    $sheet = $book->getActiveSheet();
    expect($sheet->getHighestColumn())->toBe('C')->and($sheet->getHighestRow())->toBe(2)
        ->and($sheet->rangeToArray('A1:C2'))->toBe([['الهوية', 'معرّف الوحدة', 'كود الوحدة'], ['001234567', '005087', 'COLUMNS-MATCH']])
        ->and($sheet->getAutoFilter()->getRange())->toBe('A1:C2')->and($sheet->getRightToLeft())->toBeTrue();
    $book->disconnectWorksheets();
    $csv = $this->get(route('reh.export', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain('COLUMNS-MATCH', '005087')->not->toContain('COLUMNS-OUTSIDE', 'التسعير');
});

it('uses identical selected columns in preview and pdf and splits wide reports into readable tables', function (): void {
    RehUnit::factory()->create(['code' => 'PDF-COLUMNS', 'owner_identity' => 'PRIVATE-IDENTITY']);
    $columns = ['unit_reference', 'building_reference', 'unit_code', 'workflow', 'decision', 'governorate'];
    $preview = $this->getJson(route('reh.exports.data', ['columns' => $columns]))->assertOk()->json('html');
    $doc = new DOMDocument;
    $doc->loadHTML('<?xml encoding="UTF-8">'.$preview, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($doc);
    expect($xpath->query('//th[@data-export-column-key and not(@hidden)]')->length)->toBe(6)
        ->and($xpath->query('//select[@data-export-columns]/optgroup/option[@selected]')->length)->toBe(6)
        ->and($xpath->query('//div[@role="region" and @aria-label="معاينة أعمدة التصدير" and @tabindex="0"]/table')->length)->toBe(1);
    $captured = null;
    View::composer('reh::pdf.results', function ($view) use (&$captured): void {
        $captured = $view->getData();
    });
    $this->get(route('reh.export.pdf', ['columns' => $columns]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($captured['columnGroups'])->toHaveCount(2)->and(array_keys($captured['columnRows']->first()))->toBe($columns);
    $html = view('reh::pdf.results', $captured)->render();
    expect($html)->toContain('PDF-COLUMNS', 'الجزء 2 من 2')->not->toContain('PRIVATE-IDENTITY', '<th>الهوية</th>', '<th>الأولوية</th>');
});

it('rejects empty invalid duplicated nested and non list column selections', function (mixed $columns): void {
    foreach (['reh.exports.data', 'reh.export.excel', 'reh.export.pdf', 'reh.export'] as $route) {
        $this->getJson(route($route, ['columns' => $columns]))->assertUnprocessable();
    }
})->with([
    'empty' => [''], 'unknown' => [['source']], 'duplicate' => [['owner', 'owner']],
    'nested' => [[['owner']]], 'associative' => [['unexpected' => 'owner']],
]);

it('hides restricted column choices and prevents selecting them directly for all formats', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($viewer);
    $html = $this->getJson(route('reh.exports.data'))->assertOk()->json('html');
    expect($html)->not->toContain('value="identity"', 'value="total_score"', 'value="priority"', 'data-export-column-key="identity"');
    foreach (['identity', 'total_score', 'priority'] as $column) {
        foreach (['reh.exports.data', 'reh.export.excel', 'reh.export.pdf', 'reh.export'] as $route) {
            $this->getJson(route($route, ['columns' => [$column]]))->assertForbidden();
        }
    }
    $this->get(route('reh.export.excel', ['columns' => ['unit_reference']]))->assertOk();
});

it('offers every survey field as a selectable export column while keeping the summary defaults compact', function (): void {
    $definitions = \App\Modules\Reh\Services\RehExportColumns::definitions();
    $fields = app(\App\Modules\Reh\Services\RehSurvey::class)->dictionary()['fields'];
    $surveyColumns = array_filter($definitions, fn (array $column): bool => isset($column['field']));
    expect(array_column($surveyColumns, 'field'))->toBe(array_keys($fields))->and($surveyColumns)->toHaveCount(257);
    $html = $this->getJson(route('reh.exports.data'))->assertOk()->json('html');
    $doc = new DOMDocument;
    $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($doc);
    expect($xpath->query('//select[@data-export-columns]/optgroup/option')->length)->toBe(count($definitions))
        ->and($xpath->query('//select[@data-export-columns]/optgroup/option[@selected]')->length)->toBe(17);
});

it('exports typed survey values and labels from their proper building and unit records', function (): void {
    $unit = RehUnit::factory()->create(['source' => ['household_size' => 0, 'owner_mobile' => '0599000123', 'kitchen_photo' => 'kitchen-file.jpg', 'boq_3_8_tarp_wood_door_qty' => 4.5, 'owner_disability_types' => 'see walk']]);
    $unit->building->update(['source' => [...$unit->building->source, 'building_structurally_safe' => 'yes', 'visit_date' => '2026-10-18'], 'geometry' => ['x' => 34, 'y' => 31]]);
    $columns = ['sf_household_size', 'sf_owner_mobile', 'sf_building_structurally_safe', 'sf_boq_3_8_tarp_wood_door_qty', 'sf_kitchen_photo', 'sf_visit_date', 'sf_owner_disability_types', 'sf_location'];
    $response = $this->get(route('reh.export.excel', ['columns' => $columns]))->assertOk();
    Cell::setValueBinder(new DefaultValueBinder);
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    $sheet = $book->getActiveSheet();
    expect($sheet->getCell('A2')->getValue())->toBe(0)->and($sheet->getCell('A2')->getDataType())->toBe('n')
        ->and($sheet->getCell('B2')->getValue())->toBe('0599000123')
        ->and($sheet->getCell('C2')->getValue())->toBe('نعم')
        ->and($sheet->getCell('D2')->getValue())->toBe(4.5)
        ->and($sheet->getCell('E2')->getValue())->toBe('kitchen-file.jpg')
        ->and($sheet->getCell('F2')->getValue())->toBe('2026-10-18')
        ->and($sheet->getCell('G2')->getValue())->toContain('بصرية', 'حركية')
        ->and(json_decode($sheet->getCell('H2')->getValue(), true))->toBe(['x' => 34, 'y' => 31]);
    $book->disconnectWorksheets();
    $captured = null;
    View::composer('reh::pdf.results', function ($view) use (&$captured): void {
        $captured = $view->getData();
    });
    $this->get(route('reh.export.pdf', ['columns' => $columns]))->assertOk();
    expect($captured['columnRows']->first()['sf_owner_mobile'])->toBe('0599000123')->and($captured['columnRows']->first()['sf_household_size'])->toBe(0);
});

function rehEncodedExportColumns(array $keys): string
{
    $catalog = array_flip(array_keys(\App\Modules\Reh\Services\RehExportColumns::definitions()));
    $digits = array_fill(0, (int) ceil(count($catalog) / 4), 0);
    foreach ($keys as $key) {
        $index = $catalog[$key];
        $digits[intdiv($index, 4)] |= 1 << ($index % 4);
    }

    return \App\Modules\Reh\Services\RehExportColumns::version().'.'.implode('', array_map(dechex(...), $digits));
}

it('uses a compact column selection to export every column without an oversized request url', function (): void {
    RehUnit::factory()->create();
    $keys = array_keys(\App\Modules\Reh\Services\RehExportColumns::definitions());
    $encoded = rehEncodedExportColumns($keys);
    expect(strlen($encoded))->toBeLessThan(100)->and(\App\Modules\Reh\Services\RehExportColumns::decode($encoded))->toBe($keys);
    $this->getJson(route('reh.exports.data', ['column_set' => $encoded]))->assertOk()->assertJsonPath('total_units', 1);
    $response = $this->get(route('reh.export.excel', ['column_set' => $encoded]))->assertOk();
    Cell::setValueBinder(new DefaultValueBinder);
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    expect(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($book->getActiveSheet()->getHighestColumn()))->toBe(count($keys));
    $book->disconnectWorksheets();
    $captured = null;
    View::composer('reh::pdf.results', function ($view) use (&$captured): void {
        $captured = $view->getData();
    });
    $this->get(route('reh.export.pdf', ['column_set' => $encoded]))->assertOk();
    expect(array_sum(array_map(count(...), $captured['columnSections'])))->toBe(count($keys));
});

it('keeps long geometry and file references readable in pdf without altering excel data', function (): void {
    $geometry = json_encode(['rings' => [[[34.386839123456, 31.440497123456], [34.386850234567, 31.440410234567]]]]);
    $value = \App\Modules\Reh\Services\RehPdf::columnValue('sf_location', $geometry);
    expect(preg_match('/\S{41}/u', $value))->toBe(0)->and(json_decode($value, true))->toBe(json_decode($geometry, true))
        ->and(\App\Modules\Reh\Services\RehPdf::columnValue('sf_household_size', 0))->toBe('0');
});

it('rejects stale malformed and unauthorized compact survey selections', function (): void {
    foreach (['invalid', \App\Modules\Reh\Services\RehExportColumns::version().'.'.str_repeat('0', 69)] as $encoded) {
        $this->getJson(route('reh.export.excel', ['column_set' => $encoded]))->assertUnprocessable();
    }
    $encoded = rehEncodedExportColumns(['sf_owner_identity_number']);
    $this->getJson(route('reh.export.excel', ['columns' => ['unit_reference'], 'column_set' => $encoded]))->assertUnprocessable();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($viewer);
    foreach (['reh.exports.data', 'reh.export.excel', 'reh.export.pdf', 'reh.export'] as $route) {
        $this->getJson(route($route, ['column_set' => $encoded]))->assertForbidden();
    }
    $html = $this->getJson(route('reh.exports.data'))->assertOk()->json('html');
    expect($html)->not->toContain('value="sf_owner_identity_number"', 'value="sf_owner_chronic_disease_name"');
});
