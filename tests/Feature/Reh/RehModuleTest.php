<?php

use App\Models\User;
use App\Modules\Reh\Jobs\SyncRehSurvey;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehSyncRun;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehArcgisClient;
use App\Modules\Reh\Services\RehSurvey;
use App\Support\Navigation\Sidebar;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
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
    $this->actingAs($this->officer)->postJson(route('reh.sync'))->assertAccepted();
    $this->postJson(route('reh.sync'))->assertAccepted();
    Queue::assertPushed(SyncRehSurvey::class, 1);
    Queue::assertPushed(SyncRehSurvey::class, fn (SyncRehSurvey $job): bool => $job->queue === 'reh' && $job->connection === 'reh');
    expect(config('queue.connections.reh.retry_after'))->toBeGreaterThan((new SyncRehSurvey(1))->timeout);
    expect(RehSyncRun::query()->count())->toBe(1);
    expect($this->getJson(route('reh.data', ['tab' => 'sync']))->json('sync_active'))->toBeTrue();
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
