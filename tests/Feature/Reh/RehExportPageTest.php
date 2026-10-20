<?php

use App\Models\User;
use App\Modules\Reh\Models\RehUnit;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->exporter = User::factory()->create();
    $this->exporter->givePermissionTo(['reh.view', 'reh.export', 'reh.sensitive', 'reh.scores.view']);
    $this->actingAs($this->exporter);
});

function rehExportFilterControls(string $html): array
{
    $doc = new DOMDocument;
    $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($doc);
    $fields = [];
    foreach ($xpath->query('//form[@id="reh-filters"]//*[@name]') as $input) {
        $fields[] = [$input->getAttribute('name'), $input->getAttribute('class'), $input->getAttribute('type'), trim($input->textContent)];
    }

    return $fields;
}

it('uses identical filter controls and options on the separate export page', function (): void {
    $index = $this->get(route('reh.index'))->assertOk()->getContent();
    $export = $this->get(route('reh.exports', ['sf_electricity_available' => 'unknown']))->assertOk()->assertViewHas('exportPage', true)->assertViewHas('filters', fn (array $filters): bool => $filters['sf_electricity_available'] === 'unknown')->getContent();
    expect(rehExportFilterControls($index))->toBe(rehExportFilterControls($export));
    expect($export)->toContain('تصدير بيانات المشروع', 'data-export-page="true"')->not->toContain('data-export class=');
});

it('previews ten matching units but exports all matches as real xlsx with safe identifiers and strings', function (): void {
    RehUnit::factory()->count(13)->create(['decision' => 'selected', 'owner_name' => '=SUM(1,1)', 'owner_identity' => '001234567', 'source' => ['OldUnit_objectID' => '005087']]);
    RehUnit::factory()->create(['decision' => 'excluded', 'owner_name' => 'OUTSIDE-EXPORT']);
    $preview = $this->getJson(route('reh.exports.data', ['decision' => 'selected', 'page' => 2]))->assertOk()->assertJsonPath('total_units', 13)->json('html');
    expect($preview)->toContain('أول 10 وحدات من 13')->not->toContain('OUTSIDE-EXPORT');
    $response = $this->get(route('reh.export.excel', ['decision' => 'selected', 'page' => 2]))->assertOk()->assertDownload('REH-units-'.now()->format('Y-m-d').'.xlsx');
    \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    $sheet = $book->getActiveSheet();
    expect($sheet->getHighestRow())->toBe(14)->and($sheet->getRightToLeft())->toBeTrue()
        ->and($sheet->getCell('A2')->getValue())->toBe('005087')
        ->and($sheet->getCell('D2')->getValue())->toBe('001234567')
        ->and($sheet->getCell('C2')->getValue())->toBe('=SUM(1,1)')
        ->and($sheet->getCell('C2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getCell('K2')->getDataType())->toBe(DataType::TYPE_NUMERIC);
    $book->disconnectWorksheets();
});

it('shares survey filters across preview xlsx csv and pdf and enforces data permissions', function (): void {
    $unit = RehUnit::factory()->create(['code' => 'MATCH-XLSX', 'source' => ['current_unit_occupancy' => 'empty', 'vacancy_reason' => 'cant_afford']]);
    RehUnit::factory()->create(['code' => 'OTHER-XLSX', 'source' => ['current_unit_occupancy' => 'owner_family']]);
    $filters = ['sf_vacancy_reason' => 'cant_afford'];
    $this->getJson(route('reh.exports.data', $filters))->assertOk()->assertJsonPath('total_units', 1);
    $response = $this->get(route('reh.export.excel', $filters))->assertOk();
    \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    expect($book->getActiveSheet()->getHighestRow())->toBe(2)->and($book->getActiveSheet()->getCell('P2')->getValue())->toBe('MATCH-XLSX');
    $book->disconnectWorksheets();
    expect($this->get(route('reh.export', $filters))->assertOk()->streamedContent())->toContain('MATCH-XLSX')->not->toContain('OTHER-XLSX');
    $captured = null;
    View::composer('reh::pdf.results', function ($view) use (&$captured): void {
        $captured = $view->getData();
    });
    $this->get(route('reh.export.pdf', $filters))->assertOk();
    expect($captured['rows'])->toHaveCount(1);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($viewer)->getJson(route('reh.exports.data', ['sf_owner_age_min' => 0]))->assertForbidden();
    $this->getJson(route('reh.export.excel', ['priority' => 'high']))->assertForbidden();
    $response = $this->get(route('reh.export.excel', ['q' => 'MATCH-XLSX']))->assertOk();
    \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder);
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    expect($book->getActiveSheet()->getCell('D2')->getValue())->toBe('محجوب')->and($book->getActiveSheet()->getCell('M2')->getValue())->toBe('محجوب');
    $book->disconnectWorksheets();
});

it('protects the export page preview and xlsx routes with export permission', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('reh.view');
    $this->actingAs($viewer);
    foreach (['reh.exports', 'reh.exports.data', 'reh.export.excel'] as $name) {
        $this->getJson(route($name))->assertForbidden();
    }
});

it('shows an empty preview with downloading disabled', function (): void {
    $html = $this->getJson(route('reh.exports.data'))->assertOk()->assertJsonPath('total_units', 0)->json('html');
    expect($html)->toContain('لا توجد نتائج مطابقة للتصدير', 'data-download-filtered disabled');
});
