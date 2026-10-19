<?php

use App\Models\User;
use App\Modules\Reh\Jobs\SyncRehSurvey;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehSyncRun;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehArcgisClient;
use App\Modules\Reh\Services\RehSurvey;
use App\Modules\Reh\Services\RehSyncWorker;
use App\Support\Navigation\Sidebar;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
});

function rehReviewActionInputs(string $html): array
{
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $inputs = [];
    foreach ((new DOMXPath($document))->query('//form[@data-action-form]//input[@name="action"]') as $input) {
        $inputs[$input->getAttribute('value')] = ['disabled' => $input->hasAttribute('disabled'), 'checked' => $input->hasAttribute('checked')];
    }

    return $inputs;
}

it('explains review actions without a dropdown or a preselected decision and shows acceptance requirements', function (): void {
    $unit = RehUnit::factory()->create();
    $html = $this->actingAs($this->officer)->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->toContain('ماذا تريد أن تفعل بهذا الملف؟', 'إسناد الملف لك لمتابعة مراجعته.', 'لا يعتمد التكلفة.', 'اعتماد التدقيق الهندسي، اعتماد التدقيق الاجتماعي', 'اختر إجراءً للمتابعة')
        ->not->toContain('id="reh-review-action"');
    $actions = rehReviewActionInputs($html);
    expect($actions)->toHaveCount(7)->and($actions['select']['disabled'])->toBeTrue()
        ->and($actions['claim']['disabled'])->toBeFalse()
        ->and(collect($actions)->contains('checked', true))->toBeFalse();
    $this->postJson(route('reh.units.review', $unit), ['version' => 1, 'note' => 'ملاحظة دون اختيار إجراء'])->assertUnprocessable()->assertJsonValidationErrors('action');
    expect($unit->fresh()->reviews()->count())->toBe(0)->and($unit->fresh()->decision)->toBe('pending');
});

it('keeps acceptance unavailable until the actual issues are resolved even after both reviews', function (): void {
    $unit = RehUnit::factory()->create(['engineering_status' => 'approved', 'social_status' => 'approved', 'reviewer_id' => $this->officer->id]);
    $unit->update(['source' => [...$unit->source, 'ownership_proof_status' => 'no']]);
    $this->actingAs($this->officer);
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect(rehReviewActionInputs($html)['select']['disabled'])->toBeTrue();
    expect($html)->toContain('ما الذي يمنع القبول المبدئي؟', 'معالجة التنبيهات أعلاه', 'لا تعني استبعاد الوحدة تلقائيًا');
    $this->postJson(route('reh.units.review', $unit), ['action' => 'select', 'version' => 1, 'note' => 'الملكية لم تستكمل'])->assertUnprocessable();
    $unit->update(['source' => [...$unit->source, 'ownership_proof_status' => 'yes']]);
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    $actions = rehReviewActionInputs($html);
    expect($actions['select']['disabled'])->toBeFalse()
        ->and($actions['claim']['disabled'])->toBeTrue()
        ->and($actions['engineering_approve']['disabled'])->toBeTrue()
        ->and($actions['social_approve']['disabled'])->toBeTrue();
    $this->postJson(route('reh.units.review', $unit), ['action' => 'select', 'version' => 1, 'note' => 'اكتملت المراجعة والتصحيح'])->assertOk();
});

it('explains unavailable assignment and missing source reviews without hiding their reasons', function (): void {
    $otherReviewer = User::factory()->create();
    $unit = RehUnit::factory()->create(['reviewer_id' => $otherReviewer->id, 'source_missing' => true]);
    $html = $this->actingAs($this->officer)->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    $actions = rehReviewActionInputs($html);
    expect($html)->toContain('الملف مسند إلى مراجع آخر.', 'تحقق من السجل المفقود في المصدر أولًا.');
    expect($actions['claim']['disabled'])->toBeTrue()
        ->and($actions['engineering_approve']['disabled'])->toBeTrue()
        ->and($actions['social_approve']['disabled'])->toBeTrue()
        ->and($actions['return']['disabled'])->toBeFalse();
});

it('shows only permitted action cards and only reopening after a recorded decision', function (): void {
    $unit = RehUnit::factory()->create();
    $engineer = User::factory()->create();
    $engineer->givePermissionTo(['reh.view', 'reh.review.engineering']);
    $html = $this->actingAs($engineer)->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect(array_keys(rehReviewActionInputs($html)))->toBe(['engineering_approve', 'second_assessment']);
    $unit->update(['decision' => 'excluded', 'workflow_status' => 'decided']);
    $html = $this->actingAs($this->officer)->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect(array_keys(rehReviewActionInputs($html)))->toBe(['reopen']);
    expect($html)->toContain('إلغاء القرار السابق وإعادة التدقيقين إلى انتظار المراجعة');
});

it('defaults survey filtering to field complete buildings and their units using the parent survey state', function (): void {
    $complete = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED'], 'name' => 'FIELD-COMPLETE']);
    $pending = RehBuilding::factory()->create(['source' => ['Field_status' => 'Not_Completed'], 'name' => 'FIELD-PENDING']);
    RehUnit::factory()->create(['building_id' => $complete->id, 'code' => 'UNIT-COMPLETE', 'source' => ['unitFieldStatus' => 'Not_Completed']]);
    RehUnit::factory()->create(['building_id' => $pending->id, 'code' => 'UNIT-PENDING', 'source' => ['unitFieldStatus' => 'COMPLETED']]);
    $this->actingAs($this->officer);
    $this->get(route('reh.index'))->assertOk()->assertViewHas('filters', fn (array $filters): bool => $filters['survey_status'] === 'completed');
    $html = $this->getJson(route('reh.data', ['tab' => 'units']))->assertOk()->json('html');
    expect($html)->toContain('UNIT-COMPLETE')->not->toContain('UNIT-PENDING');
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings']))->assertOk()->json('html');
    expect($html)->toContain('FIELD-COMPLETE')->not->toContain('FIELD-PENDING');
    $csv = $this->get(route('reh.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('UNIT-COMPLETE')->not->toContain('UNIT-PENDING');
});

it('supports all survey states including legacy incomplete and unrecorded values', function (string $filter, array $included, array $excluded): void {
    foreach (['DONE' => 'COMPLETED', 'OPEN' => 'Not_Completed', 'LEGACY' => 'notcomplete', 'EMPTY' => null, 'UNKNOWN' => 'unexpected'] as $code => $status) {
        $building = RehBuilding::factory()->create(['source' => ['Field_status' => $status], 'name' => 'FIELD-'.$code]);
        RehUnit::factory()->create(['building_id' => $building->id, 'code' => 'UNIT-'.$code]);
    }
    $this->actingAs($this->officer);
    foreach (['units', 'buildings'] as $tab) {
        $html = $this->getJson(route('reh.data', ['tab' => $tab, 'survey_status' => $filter]))->assertOk()->json('html');
        $prefix = $tab === 'units' ? 'UNIT-' : 'FIELD-';
        foreach ($included as $code) {
            expect($html)->toContain($prefix.$code);
        }
        foreach ($excluded as $code) {
            expect($html)->not->toContain($prefix.$code);
        }
    }
    $csv = $this->get(route('reh.export', ['survey_status' => $filter]))->assertOk()->streamedContent();
    foreach ($included as $code) {
        expect($csv)->toContain('UNIT-'.$code);
    }
    foreach ($excluded as $code) {
        expect($csv)->not->toContain('UNIT-'.$code);
    }
})->with([
    ['completed', ['DONE'], ['OPEN', 'LEGACY', 'EMPTY', 'UNKNOWN']],
    ['not_completed', ['OPEN', 'LEGACY'], ['DONE', 'EMPTY', 'UNKNOWN']],
    ['unrecorded', ['EMPTY', 'UNKNOWN'], ['DONE', 'OPEN', 'LEGACY']],
    ['all', ['DONE', 'OPEN', 'LEGACY', 'EMPTY', 'UNKNOWN'], []],
]);

it('rejects invalid survey filters and renders the organized filter controls', function (): void {
    $this->actingAs($this->officer)->getJson(route('reh.data', ['survey_status' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('survey_status');
    $this->get(route('reh.index'))->assertOk()->assertSee('reh-filter-grid')->assertSee('حالة الاستبيان')->assertSee('الملفات المسندة لي')->assertSee('value="completed" selected', false);
});

function rehObjectIdRecords(): array
{
    $building = RehBuilding::factory()->create(['id' => 17, 'object_id' => 987654, 'name' => 'اسم المبنى الأصلي']);
    $otherBuilding = RehBuilding::factory()->create(['id' => 987654, 'object_id' => 987655, 'name' => 'مبنى مختلف']);
    $unit = RehUnit::factory()->create(['id' => 18, 'object_id' => 876543, 'building_id' => $building->id, 'code' => 'SOURCE-CODE']);
    $otherUnit = RehUnit::factory()->create(['id' => 876543, 'object_id' => 876544, 'building_id' => $otherBuilding->id, 'code' => 'DECOY-CODE']);

    return [$building, $unit, $otherBuilding, $otherUnit];
}

it('uses source object ids as the visible unit and building references throughout the module', function (): void {
    [$building, $unit] = rehObjectIdRecords();
    $this->actingAs($this->officer);
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->toContain('ملف الوحدة · OBJECTID 876543', 'مبنى 987654', 'SOURCE-CODE');
    $html = $this->getJson(route('reh.buildings.show', $building))->assertOk()->json('html');
    expect($html)->toContain('ملف المبنى · OBJECTID 987654', 'data-building-units="987654"');
    $html = $this->getJson(route('reh.data', ['tab' => 'units']))->assertOk()->json('html');
    expect($html)->toContain('وحدة 876543', 'مبنى 987654', 'SOURCE-CODE');
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings']))->assertOk()->json('html');
    preg_match('/data-buildings="([^"]+)"/', $html, $matches);
    $map = collect(json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true));
    expect($map->firstWhere('id', 987654)['name'])->toBe('مبنى 987654 · اسم المبنى الأصلي');
});

it('finds source object ids without confusing them with local primary keys', function (): void {
    rehObjectIdRecords();
    $reader = User::factory()->create();
    $reader->givePermissionTo('reh.view');
    $this->actingAs($reader);
    foreach (['876543', '987654', ' 876543 '] as $search) {
        $html = $this->getJson(route('reh.data', ['tab' => 'units', 'q' => $search]))->assertOk()->json('html');
        expect($html)->toContain('SOURCE-CODE')->not->toContain('DECOY-CODE');
    }
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings', 'q' => '987654']))->assertOk()->json('html');
    expect($html)->toContain('اسم المبنى الأصلي')->not->toContain('مبنى مختلف');
});

it('filters building units by object id and keeps legacy filters displaying the source reference', function (): void {
    rehObjectIdRecords();
    $this->actingAs($this->officer);
    foreach ([['building_object_id' => 987654], ['building_id' => 17]] as $filters) {
        $html = $this->getJson(route('reh.data', ['tab' => 'units', ...$filters]))->assertOk()->json('html');
        expect($html)->toContain('النتائج مقيّدة بالمبنى OBJECTID 987654', 'SOURCE-CODE')->not->toContain('DECOY-CODE');
    }
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings', 'building_object_id' => 987654]))->assertOk()->json('html');
    expect($html)->toContain('اسم المبنى الأصلي')->not->toContain('مبنى مختلف');
    $this->getJson(route('reh.data', ['building_object_id' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('building_object_id');
});

it('exports both source object ids as primary references and retains the code and name', function (): void {
    rehObjectIdRecords();
    $csv = $this->actingAs($this->officer)->get(route('reh.export', ['building_object_id' => 987654]))->assertOk()->streamedContent();
    $rows = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));
    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('OBJECTID الوحدة')
        ->and($rows[0][1])->toBe('OBJECTID المبنى')
        ->and($rows[1][0])->toBe('876543')->and($rows[1][1])->toBe('987654')
        ->and($rows[1][15])->toBe('SOURCE-CODE')->and($rows[1][16])->toBe('اسم المبنى الأصلي');
});

it('labels attachment downloads with their source object id', function (): void {
    [, $unit] = rehObjectIdRecords();
    $this->mock(RehArcgisClient::class, function ($mock) use ($unit): void {
        $mock->shouldReceive('attachments')->once()->with(1, $unit->object_id)->andReturn([
            ['id' => 2, 'name' => 'kitchen.jpg', 'keywords' => 'kitchen_photo', 'contentType' => 'image/jpeg'],
        ]);
        $mock->shouldReceive('download')->once()->with(1, $unit->object_id, 2)->andReturn('image-bytes');
    });
    $this->actingAs($this->officer)->get(route('reh.attachment', ['unit', $unit->id, 2]))
        ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="reh-unit-876543-attachment-2"');
});

it('registers the module and protects its page data mutations and export', function (): void {
    $this->get(route('reh.index'))->assertRedirect();
    $reader = User::factory()->create();
    $unit = RehUnit::factory()->create();
    $this->actingAs($reader)->get(route('reh.index'))->assertForbidden();
    $this->getJson(route('reh.data'))->assertForbidden();
    $this->postJson(route('reh.sync'))->assertForbidden();
    $this->get(route('reh.export'))->assertForbidden();
    $this->postJson(route('reh.units.review', $unit), ['action' => 'exclude'])->assertForbidden();
    expect(Sidebar::forUser($reader)->pluck('key'))->not->toContain('reh');
    expect(Sidebar::forUser($this->officer)->pluck('key'))->toContain('reh');
    $this->actingAs($this->officer)->get(route('reh.index'))->assertOk()->assertSee('reh-workspace')->assertSee('workspace.js');
});

it('renders ajax sections and filters without returning the full layout', function (): void {
    $building = RehBuilding::factory()->create();
    $unit = RehUnit::factory()->create(['building_id' => $building->id, 'code' => 'REH-NEEDLE', 'owner_name' => '<script>alert(1)</script>']);
    RehUnit::factory()->create(['code' => 'REH-OTHER']);
    $this->actingAs($this->officer);
    foreach (['overview', 'units', 'buildings', 'reports', 'sync'] as $tab) {
        $response = $this->getJson(route('reh.data', ['tab' => $tab]))->assertOk()->assertJsonStructure(['html', 'sync_active']);
        expect($response->json('html'))->not->toContain('<html');
    }
    $response = $this->getJson(route('reh.data', ['tab' => 'units', 'q' => 'NEEDLE']))->assertOk();
    expect($response->json('html'))->toContain('REH-NEEDLE', '&lt;script&gt;')->not->toContain('REH-OTHER', '<script>alert');
    $this->getJson(route('reh.data', ['status' => 'invented']))->assertUnprocessable();
    $this->getJson(route('reh.units.show', $unit))->assertOk()->assertJsonStructure(['html']);
    $this->get(route('reh.units.show', $unit))->assertOk();
    $this->getJson(route('reh.buildings.show', $building))->assertOk();
    $this->get(route('reh.buildings.show', $building))->assertOk();
});

it('keeps sensitive fields out of reader views searches and exports', function (): void {
    $unit = RehUnit::factory()->create(['source' => ['owner_identity_number' => '987654321', 'owner_chronic_disease_name' => 'SECRET-HEALTH'], 'owner_identity' => '987654321']);
    $reader = User::factory()->create();
    $reader->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($reader);
    expect($this->getJson(route('reh.units.show', $unit))->assertOk()->json('html'))->not->toContain('987654321', 'SECRET-HEALTH');
    expect($this->getJson(route('reh.data', ['q' => '987654321']))->assertOk()->json('html'))->not->toContain($unit->code);
    expect($this->get(route('reh.export'))->assertOk()->streamedContent())->not->toContain('987654321');
    $reader->givePermissionTo('reh.sensitive');
    expect($this->getJson(route('reh.units.show', $unit))->assertOk()->json('html'))->toContain('987654321', 'SECRET-HEALTH');
});

it('records independent reviews and a human decision with optimistic locking', function (): void {
    $unit = RehUnit::factory()->create();
    $this->actingAs($this->officer);
    $url = route('reh.units.review', $unit);
    $this->postJson($url, ['action' => 'select', 'version' => 1, 'note' => 'قرار مبكر'])->assertUnprocessable();
    $this->postJson($url, ['action' => 'engineering_approve', 'version' => 1, 'note' => 'تم تدقيق الكميات والصور'])->assertOk();
    $this->postJson($url, ['action' => 'social_approve', 'version' => 1, 'note' => 'نسخة قديمة'])->assertConflict();
    $this->postJson($url, ['action' => 'social_approve', 'version' => 2, 'note' => 'تم تدقيق بيانات الأسرة'])->assertOk();
    expect($unit->fresh()->workflow_status)->toBe('ready');
    $this->postJson($url, ['action' => 'select', 'version' => 3, 'note' => 'قبول مبدئي موثق'])->assertOk();
    expect($unit->fresh()->decision)->toBe('selected')->and($unit->reviews()->count())->toBe(3);
    $this->postJson($url, ['action' => 'exclude', 'version' => 4, 'note' => 'محاولة تغيير قرار مغلق'])->assertUnprocessable();
    $this->postJson($url, ['action' => 'reopen', 'version' => 4, 'note' => 'إعادة المراجعة بناء على طلب موثق'])->assertOk();
    expect($unit->fresh()->decision)->toBe('pending')->and($unit->fresh()->engineering_status)->toBe('pending');
});

it('requires the correct review permission and a recorded reason', function (): void {
    $unit = RehUnit::factory()->create();
    $engineer = User::factory()->create();
    $engineer->givePermissionTo(['reh.view', 'reh.review.engineering']);
    $this->actingAs($engineer)->postJson(route('reh.units.review', $unit), ['action' => 'social_approve', 'version' => 1, 'note' => 'غير مصرح'])->assertForbidden();
    $this->postJson(route('reh.units.review', $unit), ['action' => 'engineering_approve', 'version' => 1])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->postJson(route('reh.units.review', $unit), ['action' => 'engineering_approve', 'version' => 1, 'note' => 'مراجعة هندسية'])->assertOk();
});

it('keeps original quantities separates null from zero and invalidates engineering approval', function (): void {
    $unit = RehUnit::factory()->create(['engineering_status' => 'approved', 'social_status' => 'approved', 'workflow_status' => 'ready']);
    $url = route('reh.units.review', $unit);
    $this->actingAs($this->officer)->postJson($url, ['action' => 'quantities', 'version' => 1, 'note' => 'لا يحتاج صيانة خزان', 'quantities' => ['boq_1_1_water_tank_repair_qty' => 0]])->assertOk();
    $unit->refresh();
    expect($unit->source['boq_1_1_water_tank_repair_qty'])->toBe(2)
        ->and($unit->quantity_overrides['boq_1_1_water_tank_repair_qty'])->toEqual(0)
        ->and($unit->engineering_status)->toBe('pending')->and($unit->social_status)->toBe('approved');
    $this->postJson($url, ['action' => 'quantities', 'version' => 2, 'note' => 'استعادة الكمية الميدانية', 'quantities' => ['boq_1_1_water_tank_repair_qty' => null]])->assertOk();
    expect($unit->fresh()->quantity_overrides)->toBe([]);
    $this->postJson($url, ['action' => 'quantities', 'version' => 3, 'note' => 'كمية غير صحيحة', 'quantities' => ['boq_1_1_water_tank_repair_qty' => 1.5]])->assertUnprocessable();
    $this->postJson($url, ['action' => 'quantities', 'version' => 3, 'note' => 'حقل غير معروف', 'quantities' => ['owner_name' => 3]])->assertUnprocessable();
    expect($this->getJson(route('reh.units.show', $unit))->json('html'))->toContain('غير مسعّر', 'بانتظار الأسعار');
});

it('blocks selection for unverified safety without automatically excluding a unit', function (): void {
    $unit = RehUnit::factory()->create(['engineering_status' => 'approved', 'social_status' => 'approved']);
    $unit->update(['source' => [...$unit->source, 'unit_structural_classification' => 'needs_second_eval']]);
    $this->actingAs($this->officer)->postJson(route('reh.units.review', $unit), ['action' => 'select', 'version' => 1, 'note' => 'محاولة اختيار'])->assertUnprocessable();
    expect($unit->fresh()->decision)->toBe('pending');
    expect(app(RehSurvey::class)->issues($unit))->toContain('التصنيف الميداني يتطلب تقييمًا هندسيًا ثانيًا.');
});

it('detects duplicate apartments and household totals without combining hosted families', function (): void {
    $unit = RehUnit::factory()->create();
    RehUnit::factory()->create(['building_id' => $unit->building_id, 'floor_number' => 0, 'apartment_number' => 1]);
    expect(implode(' ', app(RehSurvey::class)->issues($unit)))->toContain('احتمال التكرار');
    $ages = array_fill_keys(['household_males_under5', 'household_males_age5_17', 'household_males_age18_60', 'household_males_over60', 'household_females_under5', 'household_females_age5_17', 'household_females_age18_60', 'household_females_over60'], 1);
    $unit->source = [...$unit->source, ...$ages, 'household_size' => 8, 'hosted_individuals_count' => 10];
    expect(implode(' ', app(RehSurvey::class)->issues($unit)))->not->toContain('مجموع الفئات');
    $unit->source = [...$unit->source, 'household_size' => 9];
    expect(implode(' ', app(RehSurvey::class)->issues($unit)))->toContain('مجموع الفئات');
});

it('queues one sync and keeps ajax requests responsive', function (): void {
    Queue::fake();
    $this->mock(RehSyncWorker::class)->shouldReceive('start')->twice()->andReturnTrue();
    $this->actingAs($this->officer)->postJson(route('reh.sync'))->assertAccepted();
    $this->postJson(route('reh.sync'))->assertAccepted();
    Queue::assertPushed(SyncRehSurvey::class, 1);
    Queue::assertPushed(SyncRehSurvey::class, fn (SyncRehSurvey $job): bool => $job->queue === 'reh' && $job->connection === 'reh');
    expect(config('queue.connections.reh.retry_after'))->toBeGreaterThan((new SyncRehSurvey(1))->timeout);
    expect(RehSyncRun::query()->count())->toBe(1);
    expect($this->getJson(route('reh.data', ['tab' => 'sync']))->json('sync_active'))->toBeTrue();
});

it('restarts an existing queued sync from the button without dispatching a duplicate', function (): void {
    Queue::fake();
    $run = RehSyncRun::query()->create();
    $this->mock(RehSyncWorker::class)->shouldReceive('start')->once()->andReturnTrue();

    $this->actingAs($this->officer)->postJson(route('reh.sync'))->assertAccepted()->assertJsonPath('run_id', $run->id);
    Queue::assertNothingPushed();
    expect(RehSyncRun::query()->count())->toBe(1);
});

it('does not start another worker when synchronization is already running', function (): void {
    Queue::fake();
    $run = RehSyncRun::query()->create(['status' => 'running']);
    $this->mock(RehSyncWorker::class)->shouldNotReceive('start');

    $this->actingAs($this->officer)->postJson(route('reh.sync'))->assertAccepted()->assertJsonPath('run_id', $run->id);
    Queue::assertNothingPushed();
    $html = $this->getJson(route('reh.data', ['tab' => 'sync']))->assertOk()->json('html');
    expect($html)->toContain('المزامنة قيد التنفيذ…', 'تبدأ المزامنة تلقائيًا')->not->toContain('يلزم أن يكون عامل');
});

it('preserves the queued job and supports retrying a failed automatic worker launch', function (): void {
    Queue::fake();
    $this->mock(RehSyncWorker::class)->shouldReceive('start')->twice()->andReturn(false, true);
    $this->actingAs($this->officer)->postJson(route('reh.sync'))->assertStatus(503);
    $run = RehSyncRun::query()->sole();
    expect($run->status)->toBe('queued')->and($run->message)->toContain('تعذر بدء التنفيذ التلقائي');

    $this->postJson(route('reh.sync'))->assertAccepted()->assertJsonPath('run_id', $run->id);
    Queue::assertPushed(SyncRehSurvey::class, 1);
    expect(RehSyncRun::query()->count())->toBe(1);
});

it('authorizes sensitive attachments independently from the gallery', function (): void {
    $unit = RehUnit::factory()->create();
    $reader = User::factory()->create();
    $reader->givePermissionTo('reh.view');
    $this->mock(RehArcgisClient::class, function ($mock): void {
        $mock->shouldReceive('attachments')->andReturn([
            ['id' => 1, 'name' => 'id.jpg', 'keywords' => 'owner_identity_photo', 'contentType' => 'image/jpeg'],
            ['id' => 2, 'name' => 'kitchen.jpg', 'keywords' => 'kitchen_photo', 'contentType' => 'image/jpeg'],
        ]);
        $mock->shouldNotReceive('download');
    });
    $this->actingAs($reader)->getJson(route('reh.attachments', ['unit', $unit->id]))->assertOk()->assertJsonCount(1, 'attachments')->assertJsonPath('attachments.0.name', 'kitchen.jpg');
    $this->getJson(route('reh.attachment', ['unit', $unit->id, 1]))->assertForbidden();
});

it('exports filtered records and neutralizes spreadsheet formula injection', function (): void {
    RehUnit::factory()->create(['code' => 'EXPORT-ONE', 'owner_name' => '=HYPERLINK("bad")']);
    RehUnit::factory()->create(['code' => 'NOT-EXPORTED']);
    $csv = $this->actingAs($this->officer)->get(route('reh.export', ['q' => 'EXPORT-ONE']))->assertOk()->streamedContent();
    expect($csv)->toContain('EXPORT-ONE', "'=HYPERLINK", 'بانتظار الأسعار')->not->toContain('NOT-EXPORTED');
});

it('loads the exact workbook catalog with 75 quantity definitions', function (): void {
    $fields = app(RehSurvey::class)->dictionary()['fields'];
    expect(count(array_filter(array_keys($fields), fn (string $field): bool => str_starts_with($field, 'boq_'))))->toBe(75);
    expect(app(RehSurvey::class)->value('governorate', 'khan_younis'))->toBe('خانيونس');
});
