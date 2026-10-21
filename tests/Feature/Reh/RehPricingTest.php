<?php

use App\Models\User;
use App\Modules\Reh\Models\RehCostEstimate;
use App\Modules\Reh\Models\RehPriceBook;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehPriceImport;
use App\Modules\Reh\Services\RehPricing;
use App\Modules\Reh\Services\RehSurvey;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['reh.view', 'reh.pricing.view', 'reh.pricing.manage', 'reh.pricing.approve', 'reh.export']);
    $this->actingAs($this->user);
    Http::preventStrayRequests();
    \Illuminate\Support\Facades\Storage::fake('local');
});
function rehPricingWorkbook(bool $wrongReference = false): string
{
    $workbook = new Spreadsheet;
    $boq = $workbook->getActiveSheet()->setTitle('BoQ');
    $analysis = $workbook->createSheet()->setTitle('التحليل');
    $row = 8;
    $part = 1;
    foreach (app(RehSurvey::class)->dictionary()['fields'] as $field => $definition) {
        if (! preg_match('/^boq_(\\d+)_(\\d+)_/', $field, $match)) {
            continue;
        }
        $code = $match[1].'.'.$match[2];
        $unit = trim(str_replace('الوحدة: ', '', strtok($definition['hint'] ?? '', "\n")));
        $boq->fromArray([$code, $definition['label'], $unit, 1, 20, 3], null, 'A'.$row);
        $boq->setCellValueExplicit('A'.$row, $code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $analysis->setCellValue('A'.$part, ($code === '6.4' ? '' : 'بند ').$code.': تحليل');
        $analysis->fromArray(['مواد', 'مادة تجريبية', 'عدد', 2, 10, 20], null, 'A'.($part + 1));
        $analysis->setCellValue('B'.($part + 2), 'سعر الوحدة النهائي للبند');
        $analysis->setCellValue('F'.($part + 2), 20);
        if ($code === '3.3') {
            $boq->setCellValue('E'.$row, 'غير متوفر بالسوق');
        }
        if ($code === '8.3' && $wrongReference) {
            $boq->setCellValue('E'.$row, "='التحليل'!F3");
        }
        $row++;
        $part += 4;
    }
    $path = tempnam(sys_get_temp_dir(), 'reh-prices-');
    (new Xlsx($workbook))->save($path);
    $workbook->disconnectWorksheets();

    return $path;
}
function rehPricingBook(User $user): RehPriceBook
{
    $path = rehPricingWorkbook();
    try {
        $preview = app(RehPriceImport::class)->read($path);
    } finally {
        unlink($path);
    }

    return app(RehPricing::class)->import([...$preview, 'filename' => 'test.xlsx'], ['name' => 'أسعار اختبار', 'exchange_rate' => 3, 'effective_date' => now()->toDateString(), 'reason' => 'استيراد للاختبار'], $user);
}
function rehPricedUnit(): RehUnit
{
    $fields = collect(app(RehSurvey::class)->dictionary()['fields'])->keys()->filter(fn (string $key): bool => str_starts_with($key, 'boq_'))->mapWithKeys(fn (string $key): array => [$key => null])->all();

    return RehUnit::factory()->create(['engineering_status' => 'approved', 'source' => [...$fields, 'OldUnit_objectID' => 9208, 'unit_needs_intervention' => 'yes', 'boq_1_1_water_tank_repair_qty' => 2]]);
}

it('imports every survey code without turning market unavailability into zero and flags incorrect references', function (): void {
    $path = rehPricingWorkbook(true);
    try {
        $result = app(RehPriceImport::class)->read($path);
    } finally {
        unlink($path);
    }
    $items = collect($result['items'])->keyBy('code');
    expect($items)->toHaveCount(75)->and($items['3.3']['rate'])->toBeNull()
        ->and($items['3.3']['availability'])->toBe('unavailable')->and($items['8.3']['issue'])->toContain('مرجع السعر')
        ->and($items['1.1']['components'])->toHaveCount(1)->and($result['exchange_rate'])->toBe(3.0);
});

it('previews and imports via ajax without approving or consuming another users token', function (): void {
    $path = rehPricingWorkbook();
    try {
        $response = $this->postJson(route('reh.pricing.preview'), ['file' => new UploadedFile($path, 'prices.xlsx', null, null, true)])->assertOk();
        preg_match('/name="token" value="([^"]+)"/', $response->json('html'), $match);
        $data = ['token' => $match[1], 'name' => 'قائمة تجريبية', 'exchange_rate' => 3, 'effective_date' => now()->toDateString(), 'reason' => 'مراجعة أسعار ميدانية'];
        $other = User::factory()->create();
        $other->givePermissionTo(['reh.view', 'reh.pricing.view', 'reh.pricing.manage']);
        $this->actingAs($other)->postJson(route('reh.pricing.store'), $data)->assertUnprocessable();
        $this->actingAs($this->user)->postJson(route('reh.pricing.store'), $data)->assertOk();
        expect(RehPriceBook::query()->first()->status)->toBe('draft');
        $this->postJson(route('reh.pricing.store'), $data)->assertUnprocessable();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('requires explicit issue review, recomputes components, logs before and after and rejects stale writes', function (): void {
    $book = rehPricingBook($this->user);
    $item = $book->items()->where('code', '1.1')->first();
    $item->update(['issue' => 'مرجع يحتاج مراجعة']);
    $data = ['revision' => 1, 'availability' => 'available', 'reason' => 'تصحيح سعر المكون', 'components' => [['id' => $item->components()->first()->id, 'quantity' => 2, 'rate' => 15]]];
    $this->postJson(route('reh.pricing.item.update', $item), $data)->assertUnprocessable();
    $this->postJson(route('reh.pricing.item.update', $item), [...$data, 'resolve_issue' => true])->assertOk();
    expect((float) $item->fresh()->rate)->toBe(30.0)->and($book->logs()->where('action', 'item_updated')->first()->changes['before']['rate'])->toBe('20.000000');
    $this->postJson(route('reh.pricing.item.update', $item), [...$data, 'resolve_issue' => true])->assertConflict();
    $this->postJson(route('reh.pricing.approve', $book), ['revision' => 2, 'reason' => 'اعتماد قائمة الأسعار'])->assertOk();
    $this->postJson(route('reh.pricing.item.update', $item), [...$data, 'revision' => 3, 'resolve_issue' => true])->assertConflict();
    $this->postJson(route('reh.pricing.revise', $book), ['reason' => 'تحديث أسعار السوق'])->assertOk();
    expect(RehPriceBook::query()->latest('id')->first()->items()->count())->toBe(75);
});

it('preserves approved costs and rejects changed quantities and incomplete estimates', function (): void {
    $book = rehPricingBook($this->user);
    $this->postJson(route('reh.pricing.approve', $book), ['revision' => 1, 'reason' => 'اعتماد أسعار الاختبار'])->assertOk();
    $unit = rehPricedUnit();
    $service = app(RehPricing::class);
    $result = $service->calculate($unit, $book->fresh());
    expect($result['complete'])->toBeTrue()->and($result['total'])->toEqual(40)->and($result['usd'])->toEqual(13.33);
    $data = ['book_id' => $book->id, 'fingerprint' => $result['fingerprint'], 'reason' => 'اعتماد بعد التدقيق الهندسي'];
    $this->postJson(route('reh.pricing.estimate.approve', $unit), $data)->assertOk();
    $this->postJson(route('reh.pricing.estimate.approve', $unit), $data)->assertOk();
    expect(RehCostEstimate::query()->count())->toBe(1);
    $this->getJson(route('reh.pricing.unit', $unit))->assertOk()->assertSee('html');
    $unit->update(['source' => [...$unit->source, 'boq_1_1_water_tank_repair_qty' => 3]]);
    $this->postJson(route('reh.pricing.estimate.approve', $unit), $data)->assertConflict();
    expect((float) RehCostEstimate::query()->first()->total)->toBe(40.0);
    $unit->update(['source' => [...$unit->source, 'boq_3_3_wood_door_frame_qty' => 1]]);
    $partial = $service->calculate($unit->fresh(), $book->fresh());
    expect($partial['complete'])->toBeFalse()->and($partial['total'])->toEqual(60);
    $this->postJson(route('reh.pricing.estimate.approve', $unit), [...$data, 'fingerprint' => $partial['fingerprint']])->assertUnprocessable();
});

it('distinguishes zero price from unavailable and never accepts additional unpriced works', function (): void {
    $book = rehPricingBook($this->user);
    $book->items()->where('code', '1.1')->update(['rate' => 0]);
    $unit = rehPricedUnit();
    $result = app(RehPricing::class)->calculate($unit, $book);
    expect($result['complete'])->toBeTrue()->and($result['total'])->toEqual(0);
    $unit->update(['source' => [...$unit->source, 'additional_item_description_1' => 'عمل إضافي']]);
    expect(app(RehPricing::class)->calculate($unit->fresh(), $book)['complete'])->toBeFalse();
    $unpriced = app(RehPricing::class)->calculate($unit->fresh(), null);
    expect($unpriced['lines'][0]['label'])->not->toStartWith('1.1');
});

it('enforces pricing permissions and uses the same budget filters and exports', function (): void {
    $book = rehPricingBook($this->user);
    $book->update(['status' => 'approved', 'approved_at' => now()]);
    $unit = rehPricedUnit();
    $unit->building->update(['field_status' => 'COMPLETED']);
    $unit->update(['decision' => 'selected']);
    $other = rehPricedUnit();
    $other->building->update(['field_status' => 'COMPLETED']);
    $other->update(['decision' => 'excluded']);
    $this->getJson(route('reh.data', ['tab' => 'budget', 'decision' => 'selected']))->assertOk()->assertJsonPath('total_units', 1);
    $this->get(route('reh.pricing.index'))->assertOk()->assertSee('مكتبة الأسعار');
    $this->getJson(route('reh.pricing.unit', $unit))->assertOk()->assertSee('html');
    $this->get(route('reh.budget.excel', ['decision' => 'selected']))->assertOk()->assertDownload();
    $this->get(route('reh.budget.pdf', ['decision' => 'selected']))->assertOk()->assertDownload();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('reh.view');
    $this->actingAs($viewer);
    $this->getJson(route('reh.pricing.index'))->assertForbidden();
    $this->getJson(route('reh.data', ['tab' => 'budget']))->assertForbidden();
    $this->postJson(route('reh.pricing.approve', $book), ['revision' => 1, 'reason' => 'محاولة غير مسموحة'])->assertForbidden();
    $this->get(route('reh.budget.excel'))->assertForbidden();
});

it('keeps future editions out of live budgets and rejects financial approval by price editors', function (): void {
    $book = rehPricingBook($this->user);
    $service = app(RehPricing::class);
    expect($service->statusLabel())->toBe('الأسعار قيد المراجعة');
    $book->update(['status' => 'approved', 'approved_at' => now(), 'effective_date' => now()->addDay()]);
    expect($service->latest())->toBeNull()->and($service->statusLabel())->toBe('بانتظار سريان الأسعار');
    $unit = rehPricedUnit();
    $result = $service->calculate($unit, $book->fresh());
    $data = ['book_id' => $book->id, 'fingerprint' => $result['fingerprint'], 'reason' => 'مراجعة تكلفة الوحدة'];
    $this->postJson(route('reh.pricing.estimate.approve', $unit), $data)->assertUnprocessable();
    $editor = User::factory()->create();
    $editor->givePermissionTo(['reh.view', 'reh.pricing.view', 'reh.pricing.manage']);
    $this->actingAs($editor)->postJson(route('reh.pricing.estimate.approve', $unit), $data)->assertForbidden();
});

it('exports exact item codes and literal text while keeping amounts numeric', function (): void {
    $export = new \App\Modules\Reh\Services\RehBudgetExport([], []);
    $sheet = (new Spreadsheet)->getActiveSheet();
    foreach (['A1' => '3.10', 'A2' => '0010613', 'A3' => '=SUM(1,2)', 'A4' => 42.5] as $address => $value) {
        $export->bindValue($sheet->getCell($address), $value);
    }
    expect($sheet->getCell('A1')->getValue())->toBe('3.10')
        ->and($sheet->getCell('A2')->getValue())->toBe('0010613')
        ->and($sheet->getCell('A3')->getDataType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
        ->and($sheet->getCell('A4')->getDataType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
});

it('converts original prices without rounding dollar unit rates before multiplication', function (): void {
    $book = rehPricingBook($this->user);
    $unit = rehPricedUnit();
    $unit->update(['source' => [...$unit->source, 'boq_1_1_water_tank_repair_qty' => 7]]);
    $result = app(RehPricing::class)->calculate($unit->fresh(), $book);
    expect($result['total'])->toEqual(140)->and($result['usd'])->toEqual(46.67)
        ->and($result['lines'][0]['rate_usd'])->toEqual(6.666667)
        ->and($result['lines'][0]['amount_usd'])->toEqual(46.67);
    $book->items()->where('code', '1.1')->update(['availability' => 'unavailable', 'rate' => null]);
    $result = app(RehPricing::class)->calculate($unit->fresh(), $book->fresh());
    expect($result['complete'])->toBeFalse()->and($result['lines'][0]['rate_usd'])->toBeNull()
        ->and($result['lines'][0]['amount_usd'])->toBeNull();
});

it('reconciles dollar budget totals to converted units and separates partial costs in views and exports', function (): void {
    $book = rehPricingBook($this->user);
    $book->update(['status' => 'approved', 'approved_at' => now()]);
    $book->items()->where('code', '1.1')->update(['rate' => 1]);
    $first = rehPricedUnit();
    $second = rehPricedUnit();
    $partial = rehPricedUnit();
    foreach ([$first, $second, $partial] as $unit) {
        $unit->update(['building_id' => $first->building_id, 'source' => [...$unit->source, 'boq_1_1_water_tank_repair_qty' => 1]]);
    }
    $partial->update(['source' => [...$partial->source, 'boq_3_3_wood_door_frame_qty' => 1]]);
    $budget = app(RehPricing::class)->budget(RehUnit::query());
    expect($budget['complete'])->toBe(2)->and($budget['partial'])->toBe(1)
        ->and($budget['usd'])->toEqual(0.66)->and($budget['partialUsd'])->toEqual(0.33)
        ->and(array_values($budget['regions'])[0]['usd'])->toEqual(0.66)
        ->and(array_sum(array_column(array_filter($budget['units'], fn (array $row): bool => $row['complete']), 'usd')))->toEqual(0.66);
    $html = view('reh::pricing.budget', ['budget' => $budget, 'filters' => []])->render();
    expect($html)->toContain('0.66 USD', 'القيمة المحسوبة USD');
    $pdf = view('reh::pricing.pdf', ['budget' => $budget, 'filters' => []])->render();
    expect($pdf)->toContain('0.66 USD', 'القيمة USD', 'المقابل ₪');
    $rows = (new \App\Modules\Reh\Services\RehBudgetExport($budget, []))->array();
    expect(collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'تكلفة الوحدات مكتملة التسعير USD')[1])->toEqual(0.66);
    expect(collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'معرّف حصر الوحدة')[2])->toBe('التكلفة المحسوبة USD');
    $unpriced = app(RehPricing::class)->budget(RehUnit::query()->whereKey($partial->id));
    expect($unpriced['items'][1]['usd'])->toBeNull();
    $book->update(['status' => 'draft']);
    expect(app(RehPricing::class)->budget(RehUnit::query())['usd'])->toBeNull();
});

it('compares dollar costs using each editions own rate and preserves approved dollar snapshots', function (): void {
    $book = rehPricingBook($this->user);
    $this->postJson(route('reh.pricing.approve', $book), ['revision' => 1, 'reason' => 'اعتماد أسعار الاختبار'])->assertOk();
    $unit = rehPricedUnit();
    $service = app(RehPricing::class);
    $calculation = $service->calculate($unit, $book->fresh());
    $service->approveEstimate($unit, $book, ['fingerprint' => $calculation['fingerprint'], 'reason' => 'اعتماد نسخة اختبار'], $this->user);
    $copy = $service->revise($book, $this->user, 'مراجعة سعر الصرف');
    $copy->update(['exchange_rate' => 4]);
    $impact = $this->getJson(route('reh.pricing.impact', $copy))->assertOk()->json('html');
    expect($impact)->toContain('-3.33', 'فرق التكلفة USD');
    $html = $this->getJson(route('reh.pricing.unit', ['unit' => $unit, 'book' => $copy->id]))->assertOk()->json('html');
    expect($html)->toContain('10.00 USD', '13.33 USD');
    $catalog = $this->getJson(route('reh.pricing.index', ['book' => $copy->id]))->assertOk()->json('html');
    expect($catalog)->toContain('السعر USD', '-1.6667');
    expect((float) RehCostEstimate::query()->first()->total_usd)->toBe(13.33);
});

it('downloads the full filtered budget PDF beyond five hundred units', function (): void {
    $building = \App\Modules\Reh\Models\RehBuilding::factory()->create(['field_status' => 'COMPLETED']);
    RehUnit::factory()->count(501)->create(['building_id' => $building->id, 'decision' => 'selected', 'source' => ['unit_needs_intervention' => 'no']]);
    RehUnit::factory()->create(['building_id' => $building->id, 'decision' => 'excluded']);
    $budget = app(RehPricing::class)->budget(RehUnit::query()->where('decision', 'selected'));
    $html = view('reh::pricing.pdf', ['budget' => $budget, 'filters' => []])->render();
    expect($budget['units'])->toHaveCount(501)
        ->and(substr_count($html, 'معرّف حصر الوحدة'))->toBe(6)
        ->and($html)->toContain('501 وحدة');
    $response = $this->get(route('reh.budget.pdf', ['decision' => 'selected']))
        ->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

it('shows each listed units dollar cost and distinguishes partial and unpriced quantities', function (): void {
    $book = rehPricingBook($this->user);
    $book->update(['status' => 'approved', 'approved_at' => now()]);
    $complete = rehPricedUnit();
    $partial = rehPricedUnit();
    $unpriced = rehPricedUnit();
    $partial->update(['source' => [...$partial->source, 'boq_3_3_wood_door_frame_qty' => 1]]);
    $unpriced->update(['source' => [...$unpriced->source, 'boq_1_1_water_tank_repair_qty' => null]]);
    foreach ([$complete, $partial, $unpriced] as $unit) {
        $unit->building->update(['field_status' => 'COMPLETED']);
    }
    $html = $this->getJson(route('reh.data', ['tab' => 'units']))->assertOk()->json('html');
    $cells = [];
    foreach ([$complete, $partial, $unpriced] as $unit) {
        preg_match('/data-unit-pricing="'.$unit->id.'">(.*?)<\/div>/s', $html, $match);
        $cells[$unit->id] = preg_replace('/\s+/u', ' ', strip_tags($match[1]));
    }
    expect($cells[$complete->id])->toContain('13.33 USD', 'مكتمل التسعير', 'تفاصيل التكلفة')->not->toContain('مبلغ جزئي')
        ->and($cells[$partial->id])->toContain('13.33 USD', 'مبلغ جزئي', 'تسعير غير مكتمل', 'غير متوفر بالسوق')
        ->and($cells[$unpriced->id])->toContain('تسعير غير مكتمل', 'كمية غير مسجلة')->not->toContain('USD');
});

it('waits for an effective approved price book and hides row pricing without permission', function (): void {
    $book = rehPricingBook($this->user);
    $unit = rehPricedUnit();
    $unit->building->update(['field_status' => 'COMPLETED']);
    $html = $this->getJson(route('reh.data', ['tab' => 'overview']))->assertOk()->json('html');
    expect($html)->toContain('بانتظار قائمة أسعار معتمدة')->not->toContain('13.33 USD');
    $book->update(['status' => 'approved', 'approved_at' => now(), 'effective_date' => now()->addDay()]);
    $html = $this->getJson(route('reh.data', ['tab' => 'units']))->assertOk()->json('html');
    expect($html)->toContain('بانتظار قائمة أسعار معتمدة')->not->toContain('13.33 USD');
    $book->update(['effective_date' => now()->toDateString()]);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('reh.view');
    $html = $this->actingAs($viewer)->getJson(route('reh.data', ['tab' => 'units']))->assertOk()->json('html');
    expect($html)->toContain('تحتاج صلاحية عرض التسعير')->not->toContain('data-unit-pricing', '13.33 USD', 'تفاصيل التكلفة');
});
