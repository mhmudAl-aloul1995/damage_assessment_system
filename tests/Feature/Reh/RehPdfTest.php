<?php

use App\Models\User;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehPdf;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->exporter = User::factory()->create();
    $this->exporter->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($this->exporter);
    Http::preventStrayRequests();
});

it('downloads a real arabic unit pdf with old references and redacts restricted information', function (): void {
    $unit = RehUnit::factory()->create(['source' => ['OldUnit_objectID' => 5087, 'owner_identity_number' => 'SECRET-IDENTITY', 'owner_chronic_disease_name' => 'SECRET-HEALTH']]);
    $unit->building->update(['source' => [...$unit->building->source, 'Old_objectID' => 2328]]);
    $data = app(RehPdf::class)->unitData($unit->fresh('building'), $this->exporter);
    $html = view('reh::pdf.unit', $data)->render();
    expect($html)->toContain('5087', '2328', 'معرّف حصر المبنى', 'معرّف تأهيل المبنى', 'معرّف حصر الوحدة', 'معرّف تأهيل الوحدة')->not->toContain('SECRET-IDENTITY', 'SECRET-HEALTH', 'التقييم الاجتماعي · أسرة المالك', 'معرّف المبنى السابق', 'معرّف الوحدة الحالي');
    expect($data['total'])->toBe('محجوب')->and($data['social'])->toBe('محجوب');
    $response = $this->get(route('reh.units.pdf', $unit))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload('REH-unit-5087.pdf');
    expect($response->getContent())->toStartWith('%PDF-')->toContain('DroidArabicKufi', 'DejaVuSans');
    $this->exporter->givePermissionTo(['reh.sensitive', 'reh.scores.view']);
    $html = view('reh::pdf.unit', app(RehPdf::class)->unitData($unit->fresh('building'), $this->exporter))->render();
    expect($html)->toContain('SECRET-IDENTITY', 'SECRET-HEALTH', 'التقييم الاجتماعي · أسرة المالك');
});

it('exports all filtered records rather than the current page and preserves the filter summary', function (): void {
    RehUnit::factory()->count(21)->create(['decision' => 'selected']);
    RehUnit::factory()->create(['decision' => 'excluded', 'owner_name' => 'EXCLUDED-OWNER']);
    $captured = null;
    View::composer('reh::pdf.results', function ($view) use (&$captured): void {
        $captured = $view->getData();
    });
    $response = $this->get(route('reh.export.pdf', ['decision' => 'selected', 'page' => 2]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-')->toContain('DroidArabicKufi', 'DejaVuSans')->and($captured['rows'])->toHaveCount(21)
        ->and($captured['filterSummary']['القرار'])->toBe('مقبول مبدئيًا')
        ->and($captured['rows']->pluck('owner')->all())->not->toContain('EXCLUDED-OWNER')
        ->and($captured['rows']->pluck('score')->unique()->all())->toBe(['محجوب']);
});

it('requires export permission for both pdf routes', function (): void {
    $unit = RehUnit::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('reh.view');
    $this->actingAs($viewer)->getJson(route('reh.units.pdf', $unit))->assertForbidden();
    $this->getJson(route('reh.export.pdf'))->assertForbidden();
});
