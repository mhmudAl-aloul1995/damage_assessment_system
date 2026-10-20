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
    expect($html)->toContain('ماذا تريد أن تفعل بهذا الملف؟', 'دون إسناد', 'لا يعتمد التكلفة.', 'اعتماد التدقيق الهندسي، اعتماد التدقيق الاجتماعي', 'اختر إجراءً للمتابعة')
        ->not->toContain('id="reh-review-action"');
    $actions = rehReviewActionInputs($html);
    expect($actions)->toHaveCount(6)->and($actions['select']['disabled'])->toBeTrue()
        ->and(array_key_exists('claim', $actions))->toBeFalse()
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
        ->and(array_key_exists('claim', $actions))->toBeFalse()
        ->and($actions['engineering_approve']['disabled'])->toBeTrue()
        ->and($actions['social_approve']['disabled'])->toBeTrue();
    $this->postJson(route('reh.units.review', $unit), ['action' => 'select', 'version' => 1, 'note' => 'اكتملت المراجعة والتصحيح'])->assertOk();
});

it('removes assignment while explaining missing source review restrictions', function (): void {
    $otherReviewer = User::factory()->create();
    $unit = RehUnit::factory()->create(['reviewer_id' => $otherReviewer->id, 'source_missing' => true]);
    $html = $this->actingAs($this->officer)->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    $actions = rehReviewActionInputs($html);
    expect($html)->toContain('دون إسناد', 'تحقق من السجل المفقود في المصدر أولًا.');
    expect(array_key_exists('claim', $actions))->toBeFalse()
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
    $this->get(route('reh.index'))->assertOk()->assertSee('reh-filter-primary')->assertSee('reh-advanced-filters')->assertSee('حالة الاستبيان')->assertDontSee('الملفات المسندة لي')->assertSee('value="completed" selected', false);
});

function rehObjectIdRecords(): array
{
    $building = RehBuilding::factory()->create(['id' => 17, 'object_id' => 987650, 'source' => ['Field_status' => 'COMPLETED', 'Old_objectID' => 987654], 'name' => 'اسم المبنى الأصلي']);
    $otherBuilding = RehBuilding::factory()->create(['id' => 987654, 'object_id' => 987655, 'source' => ['Field_status' => 'COMPLETED', 'old_objectid' => '987655'], 'name' => 'مبنى مختلف']);
    $unit = RehUnit::factory()->create(['id' => 18, 'object_id' => 876540, 'source' => ['OldUnit_objectID' => '876543'], 'building_id' => $building->id, 'code' => 'SOURCE-CODE']);
    $otherUnit = RehUnit::factory()->create(['id' => 876543, 'object_id' => 876544, 'source' => ['old_objectid' => 876544], 'building_id' => $otherBuilding->id, 'code' => 'DECOY-CODE']);

    return [$building, $unit, $otherBuilding, $otherUnit];
}

it('uses source object ids as the visible unit and building references throughout the module', function (): void {
    [$building, $unit] = rehObjectIdRecords();
    $this->actingAs($this->officer);
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->toContain('ملف الوحدة · المعرّف 876543', 'مبنى 987654', 'SOURCE-CODE');
    $html = $this->getJson(route('reh.buildings.show', $building))->assertOk()->json('html');
    expect($html)->toContain('ملف المبنى · المعرّف 987654', 'data-building-units="17"');
    $html = $this->getJson(route('reh.data', ['tab' => 'units']))->assertOk()->json('html');
    expect($html)->toContain('وحدة 876543', 'مبنى 987654', 'SOURCE-CODE');
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings']))->assertOk()->json('html');
    preg_match('/data-buildings="([^"]+)"/', $html, $matches);
    $map = collect(json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true));
    expect($map->firstWhere('id', 17)['name'])->toBe('مبنى 987654 · اسم المبنى الأصلي');
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
    foreach ([['building_old_objectid' => '987654'], ['building_id' => 17]] as $filters) {
        $html = $this->getJson(route('reh.data', ['tab' => 'units', ...$filters]))->assertOk()->json('html');
        expect($html)->toContain('النتائج مقيّدة بالمبنى 987654', 'SOURCE-CODE')->not->toContain('DECOY-CODE');
    }
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings', 'building_old_objectid' => '987654']))->assertOk()->json('html');
    expect($html)->toContain('اسم المبنى الأصلي')->not->toContain('مبنى مختلف');
    $this->getJson(route('reh.data', ['building_object_id' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('building_object_id');
});

it('exports both source object ids as primary references and retains the code and name', function (): void {
    rehObjectIdRecords();
    $csv = $this->actingAs($this->officer)->get(route('reh.export', ['building_old_objectid' => '987654']))->assertOk()->streamedContent();
    $rows = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));
    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('معرّف الوحدة')
        ->and($rows[0][1])->toBe('معرّف المبنى')
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
        ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=reh-unit-876543-attachment-2');
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

it('retires local-only quantity edits and assignment without changing existing data', function (): void {
    $unit = RehUnit::factory()->create(['engineering_status' => 'approved', 'social_status' => 'approved', 'workflow_status' => 'ready']);
    foreach (['claim', 'quantities'] as $action) {
        $this->actingAs($this->officer)->postJson(route('reh.units.review', $unit), ['action' => $action, 'version' => 1, 'note' => 'مراجعة اختبارية', 'quantities' => ['boq_1_1_water_tank_repair_qty' => 0]])->assertForbidden();
    }
    expect($unit->fresh()->source['boq_1_1_water_tank_repair_qty'])->toBe(2)
        ->and($unit->fresh()->engineering_status)->toBe('approved')->and($unit->fresh()->reviews()->count())->toBe(0);
    expect($this->getJson(route('reh.units.show', $unit))->json('html'))->toContain('data-audit-field="boq_1_1_water_tank_repair_qty"', 'بانتظار الأسعار');
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

it('links map buildings to filtered unit object ids without serializing sensitive source data', function (): void {
    $building = RehBuilding::factory()->create(['object_id' => 701, 'geometry' => ['rings' => [[[34.3, 31.4], [34.31, 31.4], [34.31, 31.41], [34.3, 31.4]]]]]);
    $unit = RehUnit::factory()->create(['building_id' => $building->id, 'object_id' => 801, 'workflow_status' => 'ready', 'owner_identity' => 'PRIVATE-IDENTITY']);
    RehUnit::factory()->create(['building_id' => $building->id, 'object_id' => 802, 'workflow_status' => 'returned']);
    $other = RehBuilding::factory()->create(['object_id' => 702]);
    RehUnit::factory()->create(['building_id' => $other->id, 'workflow_status' => 'returned']);
    $html = $this->actingAs($this->officer)->getJson(route('reh.data', ['tab' => 'buildings', 'status' => 'ready']))->assertOk()->json('html');
    preg_match('/data-buildings="([^"]+)"/', $html, $matches);
    $map = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);
    expect($map)->toHaveCount(1)
        ->and($map[0]['id'])->toBe($building->id)
        ->and($map[0]['total_units'])->toBe(2)
        ->and($map[0]['priority'])->toBe('unclassified')
        ->and($map[0]['unclassified_units'])->toBe(1)
        ->and($map[0]['survey'])->toBe('completed')
        ->and($map[0]['units'])->toHaveCount(1)
        ->and($map[0]['units'][0])->toMatchArray(['old_objectid' => 'غير متوفر', 'status' => 'ready', 'url' => route('reh.units.show', $unit), 'priority' => 'unclassified'])
        ->and(json_encode($map))->not->toContain('PRIVATE-IDENTITY', 'owner_full_name', 'owner_identity_number', 'source_hash')
        ->and($html)->toContain('data-map-mode="priority"', 'data-map-selection', 'data-map-building="'.$building->id.'"');
});

it('keeps map priorities private and supports survey and priority filtering', function (): void {
    $complete = RehBuilding::factory()->create(['object_id' => 711]);
    $incomplete = RehBuilding::factory()->create(['object_id' => 712, 'source' => ['Field_status' => 'notcomplete']]);
    RehUnit::factory()->create(['building_id' => $complete->id]);
    RehUnit::factory()->create(['building_id' => $incomplete->id]);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('reh.view');
    $this->actingAs($viewer);
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings', 'survey_status' => 'not_completed']))->assertOk()->json('html');
    preg_match('/data-buildings="([^"]+)"/', $html, $matches);
    $map = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);
    expect($map)->toHaveCount(1)->and($map[0]['id'])->toBe($incomplete->id)
        ->and($map[0]['survey'])->toBe('not_completed')
        ->and($map[0])->not->toHaveKeys(['priority', 'unclassified_units', 'missing_units'])
        ->and($map[0]['units'][0])->not->toHaveKey('priority')
        ->and($html)->not->toContain('data-map-mode="priority"');
    $this->getJson(route('reh.data', ['tab' => 'buildings', 'priority' => 'high']))->assertForbidden();
    $html = $this->actingAs($this->officer)->getJson(route('reh.data', ['tab' => 'buildings', 'priority' => 'high']))->assertOk()->json('html');
    preg_match('/data-buildings="([^"]+)"/', $html, $matches);
    expect(json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true))->toBe([]);
    $html = $this->getJson(route('reh.data', ['tab' => 'buildings', 'priority' => 'unclassified']))->assertOk()->json('html');
    preg_match('/data-buildings="([^"]+)"/', $html, $matches);
    expect(json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true))->toHaveCount(1);
});

it('displays unavailable for absent old references without substituting current arcgis ids', function (): void {
    foreach ([[], ['old_objectid' => null], ['old_objectid' => '   '], ['old_objectid' => []]] as $source) {
        expect(RehSurvey::reference($source))->toBe('غير متوفر');
    }
    expect(RehSurvey::reference(['old_objectid' => 0]))->toBe('0')
        ->and(RehSurvey::reference(['old_objectid' => '00123']))->toBe('00123');
    $building = RehBuilding::factory()->create(['object_id' => 991122]);
    $unit = RehUnit::factory()->create(['building_id' => $building->id, 'object_id' => 991133]);
    $this->actingAs($this->officer);
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->toContain('ملف الوحدة · المعرّف غير متوفر', 'مبنى غير متوفر')->not->toContain('OBJECTID 991133', 'وحدة 991133', 'مبنى 991122');
    $html = $this->getJson(route('reh.buildings.show', $building))->assertOk()->json('html');
    expect($html)->toContain('ملف المبنى · المعرّف غير متوفر');
    $csv = $this->get(route('reh.export'))->assertOk()->streamedContent();
    $rows = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));
    expect($rows[1][0])->toBe('غير متوفر')->and($rows[1][1])->toBe('غير متوفر');
    $html = $this->getJson(route('reh.data', ['tab' => 'units', 'q' => '991133']))->assertOk()->json('html');
    expect($html)->not->toContain(route('reh.units.show', $unit));
});

it('keeps records with duplicate old references independently linked on the map', function (): void {
    $first = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED', 'old_objectid' => 321]]);
    $second = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED', 'old_objectid' => '321']]);
    RehUnit::factory()->create(['building_id' => $first->id, 'source' => ['old_objectid' => 123]]);
    RehUnit::factory()->create(['building_id' => $second->id, 'source' => ['old_objectid' => 123]]);
    $html = $this->actingAs($this->officer)->getJson(route('reh.data', ['tab' => 'buildings', 'q' => '321']))->assertOk()->json('html');
    preg_match('/data-buildings="([^"]+)"/', $html, $matches);
    $map = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);
    expect($map)->toHaveCount(2)->and(array_column($map, 'id'))->toBe([$first->id, $second->id])
        ->and($map[0]['units'][0]['old_objectid'])->toBe('123')
        ->and($map[0]['units'][0]['url'])->not->toBe($map[1]['units'][0]['url']);
});

it('reads the original ArcGIS reference casing and retains compatibility with earlier source keys', function (): void {
    foreach (['OldUnit_objectID', 'Old_objectID', 'old_objectID', 'old_objectid'] as $field) {
        expect(RehSurvey::reference([$field => 2328]))->toBe('2328')
            ->and(RehSurvey::reference([$field => ' 00123 ']))->toBe('00123')
            ->and(RehSurvey::reference([$field => null]))->toBe('غير متوفر');
    }
    expect(RehSurvey::reference(['Old_objectID' => 2328, 'old_objectid' => 999]))->toBe('2328')
        ->and(RehSurvey::reference(['OldUnit_objectID' => 456, 'Old_objectID' => 2328]))->toBe('456');
});

it('shows previous and current identifiers as read only general data for each record type', function (bool $missingPrevious): void {
    $building = RehBuilding::factory()->create(['object_id' => 3770]);
    $building->update(['source' => [...$building->source, 'Old_objectID' => $missingPrevious ? null : 2328]]);
    $unit = RehUnit::factory()->create(['building_id' => $building->id, 'object_id' => 4621]);
    $unit->update(['source' => [...$unit->source, 'OldUnit_objectID' => $missingPrevious ? null : 5087]]);
    $reader = User::factory()->create();
    $reader->givePermissionTo('reh.view');
    $this->actingAs($reader);
    $expectedBuilding = $missingPrevious ? 'غير متوفر' : '2328';
    $expectedUnit = $missingPrevious ? 'غير متوفر' : '5087';
    foreach (['unit' => route('reh.units.show', $unit), 'building' => route('reh.buildings.show', $building)] as $kind => $url) {
        $html = $this->getJson($url)->assertOk()->json('html');
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new DOMXPath($document);
        $section = $xpath->query('//section[@data-record-identifiers]')->item(0);
        expect($section)->not->toBeNull();
        $expected = ['building-previous' => ['معرّف المبنى السابق', $expectedBuilding], 'building-current' => ['معرّف المبنى الحالي', '3770']];
        if ($kind === 'unit') {
            $expected += ['unit-previous' => ['معرّف الوحدة السابق', $expectedUnit], 'unit-current' => ['معرّف الوحدة الحالي', '4621']];
            expect($xpath->query('ancestor::section[@data-detail-panel="data"]', $section)->length)->toBe(1);
        }
        expect($xpath->query('.//*[@data-reference]', $section)->length)->toBe(count($expected))
            ->and($xpath->query('.//input | .//button | .//select | .//textarea | .//*[@contenteditable]', $section)->length)->toBe(0);
        foreach ($expected as $key => [$label, $value]) {
            $field = $xpath->query('.//*[@data-reference="'.$key.'"]', $section)->item(0);
            expect(trim($xpath->query('./dt', $field)->item(0)->textContent))->toBe($label)
                ->and(trim($xpath->query('./dd', $field)->item(0)->textContent))->toBe($value);
        }
    }
})->with([false, true]);

it('uses primary reference labels throughout the interface while preserving general data identifier labels', function (): void {
    [$building, $unit] = rehObjectIdRecords();
    $this->actingAs($this->officer);
    foreach ([route('reh.units.show', $unit), route('reh.buildings.show', $building), route('reh.data', ['tab' => 'units']), route('reh.data', ['tab' => 'buildings'])] as $url) {
        $html = $this->getJson($url)->assertOk()->json('html');
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//script | //section[@data-record-identifiers]') as $node) {
            $node->parentNode->removeChild($node);
        }
        expect($document->textContent)->not->toContain('old_objectid', 'Old_objectID', 'OldUnit_objectID', 'معرّف المبنى السابق', 'معرّف الوحدة السابق');
    }
    $this->get(route('reh.index'))->assertOk()->assertSee('اكتب معرّف الوحدة أو المبنى، الاسم أو الكود…');
});
