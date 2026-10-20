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
