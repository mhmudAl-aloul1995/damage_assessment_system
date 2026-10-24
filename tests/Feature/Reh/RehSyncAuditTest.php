<?php

use App\Models\User;
use App\Modules\Reh\Jobs\SyncRehSurvey;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehSyncRun;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehArcgisClient;
use App\Modules\Reh\Services\RehFieldAudit;
use App\Modules\Reh\Services\RehImporter;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->manager = User::factory()->create(['name' => 'منفذ المزامنة التجريبي']);
    $this->manager->assignRole('REH Module Manager');
    $this->actingAs($this->manager);
    $this->buildingFeature = ['attributes' => ['objectid' => 10, 'globalid' => '{11111111-1111-4111-8111-111111111111}', 'Old_objectID' => 1337, 'building_name' => 'مبنى اختبار السجل', 'building_structurally_safe' => 'yes']];
    $this->unitFeature = ['attributes' => ['objectid' => 20, 'globalid' => '{22222222-2222-4222-8222-222222222222}', 'parentglobalid' => '{11111111-1111-4111-8111-111111111111}', 'OldUnit_objectID' => 10613, 'owner_full_name' => 'مالك تجريبي', 'household_size' => 5, 'boq_1_1_water_tank_repair_qty' => 2]];
    $this->importer = app(RehImporter::class);
    $this->building = $this->importer->building($this->buildingFeature);
    $this->unit = $this->importer->unit($this->unitFeature);
    $this->run = RehSyncRun::factory()->create(['user_id' => $this->manager->id]);
    Cache::flush();
    Http::preventStrayRequests();
});

it('records changed survey answers atomically in the existing audit table with both actors and links the review', function (): void {
    $feature = $this->unitFeature;
    $feature['attributes'] = [...$feature['attributes'], 'household_size' => 7, 'boq_1_1_water_tank_repair_qty' => 3, 'Editor' => 'arcgis.collector', 'EditDate' => 1792660200000];
    $this->unit->update(['quantity_overrides' => ['boq_1_1_water_tank_repair_qty' => 8]]);
    $this->importer->unit($feature, $this->run);
    $logs = RehAuditLog::query()->get()->keyBy('field');
    expect($logs)->toHaveCount(2)
        ->and($logs['household_size']->values['before'])->toBe(5)
        ->and($logs['household_size']->values['after'])->toBe(7)
        ->and($logs['household_size']->sensitive)->toBeTrue()
        ->and($logs['boq_1_1_water_tank_repair_qty']->values['before'])->toBe(2)
        ->and($logs['boq_1_1_water_tank_repair_qty']->sensitive)->toBeFalse()
        ->and($logs['household_size']->user_id)->toBe($this->manager->id)
        ->and($logs['household_size']->values)->toMatchArray(['origin' => 'sync', 'sync_run_id' => $this->run->id, 'remote_editor' => 'arcgis.collector', 'remote_edit_date' => 1792660200000, 'reference_after' => '10613'])
        ->and($this->unit->fresh()->quantity_overrides)->toBe(['boq_1_1_water_tank_repair_qty' => 8]);
    $review = $this->unit->reviews()->sole();
    expect($review->changes['audit_log_ids'])->toEqualCanonicalizing($logs->pluck('id')->all())
        ->and($review->changes['sync_run_id'])->toBe($this->run->id)
        ->and($review->changes)->toHaveKey('scoring');
    $this->importer->unit($feature, $this->run);
    expect(RehAuditLog::query()->count())->toBe(2)->and($this->unit->reviews()->count())->toBe(1);
    Http::assertNothingSent();
});

it('records building changes once and links each affected unit review to the same logs', function (): void {
    $second = RehUnit::factory()->create(['building_id' => $this->building->id]);
    $feature = $this->buildingFeature;
    $feature['attributes']['building_structurally_safe'] = 'no';
    $this->importer->building($feature, $this->run);
    $log = RehAuditLog::query()->sole();
    expect($log->record_kind)->toBe('building')->and($log->values['before'])->toBe('yes')->and($log->values['after'])->toBe('no');
    foreach ([$this->unit, $second] as $unit) {
        expect($unit->reviews()->sole()->changes)->toMatchArray(['sync_run_id' => $this->run->id, 'audit_record_kind' => 'building', 'audit_record_id' => $this->building->id, 'audit_log_ids' => [$log->id]]);
    }
});

it('keeps direct and manual importer calls out of the synchronization log', function (): void {
    $this->unitFeature['attributes']['household_size'] = 9;
    $this->importer->unit($this->unitFeature);
    $this->buildingFeature['attributes']['building_name'] = 'تصحيح يدوي';
    $this->importer->building($this->buildingFeature);
    expect(RehAuditLog::query()->count())->toBe(0);
});

it('compares calendar dates without false changes caused by local timezone conversion', function (): void {
    config(['app.timezone' => 'Asia/Hebron']);
    $this->buildingFeature['attributes']['visit_date'] = \Carbon\CarbonImmutable::parse('2026-10-20', 'UTC')->getTimestampMs();
    $this->importer->building($this->buildingFeature);
    $this->buildingFeature['attributes']['visit_date'] = '2026-10-20';
    $this->importer->building($this->buildingFeature, $this->run);
    expect(RehAuditLog::query()->count())->toBe(0);
    $this->buildingFeature['attributes']['visit_date'] = '2026-10-21';
    $this->importer->building($this->buildingFeature, $this->run);
    expect(RehAuditLog::query()->sole()->values)->toMatchArray(['before' => '2026-10-20', 'after' => '2026-10-21']);
});

it('keeps successive runs distinct and supports system initiated syncs without attributing edits to a local user', function (): void {
    $systemRun = RehSyncRun::query()->create();
    $this->unitFeature['attributes']['household_size'] = 7;
    $this->importer->unit($this->unitFeature, $systemRun);
    $this->unitFeature['attributes']['household_size'] = 8;
    $this->importer->unit($this->unitFeature, $this->run);
    $logs = RehAuditLog::query()->orderBy('id')->get();
    expect($logs)->toHaveCount(2)->and($logs[0]->user_id)->toBeNull()
        ->and($logs[0]->values)->toMatchArray(['before' => 5, 'after' => 7, 'sync_run_id' => $systemRun->id])
        ->and($logs[1]->values)->toMatchArray(['before' => 7, 'after' => 8, 'sync_run_id' => $this->run->id]);
    $html = $this->getJson(route('reh.units.show', $this->unit))->assertOk()->json('html');
    expect($html)->toContain('مزامنة النظام', 'data-audit-run="'.$systemRun->id.'"', 'data-audit-run="'.$this->run->id.'"');
});

it('records explicit removal and zero without treating unanswered values as zero', function (): void {
    $feature = $this->unitFeature;
    unset($feature['attributes']['household_size']);
    $feature['attributes']['boq_1_1_water_tank_repair_qty'] = 0;
    $this->importer->unit($feature, $this->run);
    $logs = RehAuditLog::query()->get()->keyBy('field');
    expect($logs['household_size']->values)->toMatchArray(['before' => 5, 'after' => null, 'after_present' => false])
        ->and($logs['boq_1_1_water_tank_repair_qty']->values['after'])->toBe(0);
});

it('avoids false changes from metadata numeric formatting and multiple choice ordering', function (): void {
    $this->unitFeature['attributes']['owner_disability_types'] = 'visual hearing';
    $this->unitFeature['attributes']['visit_date'] = 1792540800000;
    $this->importer->unit($this->unitFeature);
    $feature = $this->unitFeature;
    $feature['attributes'] = [...$feature['attributes'], 'boq_1_1_water_tank_repair_qty' => '2.000000', 'household_size' => '5', 'owner_disability_types' => 'hearing visual', 'Editor' => 'another.editor', 'EditDate' => 1792660200000, 'Creator' => 'another.creator', 'objectid' => 21];
    $this->importer->unit($feature, $this->run);
    expect(RehAuditLog::query()->count())->toBe(0);
});

it('rolls back both the imported answers and partial logs when logging fails', function (): void {
    $feature = $this->unitFeature;
    $feature['attributes'] = [...$feature['attributes'], 'household_size' => 7, 'boq_1_1_water_tank_repair_qty' => 3];
    $insertions = 0;
    RehAuditLog::creating(function () use (&$insertions): void {
        if (++$insertions === 2) {
            throw new RuntimeException('Simulated audit storage failure');
        }
    });
    try {
        expect(fn () => $this->importer->unit($feature, $this->run))->toThrow(RuntimeException::class);
    } finally {
        RehAuditLog::flushEventListeners();
    }
    expect($insertions)->toBe(2)->and(RehAuditLog::query()->count())->toBe(0)
        ->and($this->unit->fresh()->household_size)->toBe(5)
        ->and($this->unit->fresh()->version)->toBe(1)
        ->and($this->unit->reviews()->count())->toBe(0);
});

it('passes synchronization context through the job and records new surveys without copying their sensitive answers', function (): void {
    $building = $this->buildingFeature;
    $building['attributes']['globalid'] = '{33333333-3333-4333-8333-333333333333}';
    $building['attributes']['objectid'] = 30;
    $unit = $this->unitFeature;
    $unit['attributes'] = [...$unit['attributes'], 'globalid' => '{44444444-4444-4444-8444-444444444444}', 'parentglobalid' => $building['attributes']['globalid'], 'objectid' => 40, 'owner_chronic_disease_name' => 'PRIVATE-INITIAL-ANSWER'];
    $this->mock(RehArcgisClient::class)->shouldReceive('records')->twice()->andReturnUsing(function (int $layer) use ($building, $unit): Generator {
        yield $layer === (int) config('reh.buildings_layer') ? $building : $unit;
    });
    app()->call([new SyncRehSurvey($this->run->id), 'handle']);
    $logs = RehAuditLog::query()->get();
    expect($this->run->fresh()->status)->toBe('completed')->and($logs)->toHaveCount(2)
        ->and($logs->pluck('field')->unique()->all())->toBe(['__created'])
        ->and(json_encode($logs->toArray()))->not->toContain('PRIVATE-INITIAL-ANSWER');
    foreach ($logs as $log) {
        expect($log->values['sync_run_id'])->toBe($this->run->id)->and($log->user_id)->toBe($this->manager->id);
    }
    Http::assertNothingSent();
});

it('renders grouped sync and legacy manual entries with role filtered counts and separate actor names', function (): void {
    $feature = $this->unitFeature;
    $feature['attributes'] = [...$feature['attributes'], 'household_size' => 7, 'boq_1_1_water_tank_repair_qty' => 3, 'owner_chronic_disease_name' => 'PRIVATE-SYNC-ANSWER', 'Editor' => 'arcgis.collector'];
    $this->importer->unit($feature, $this->run);
    $manual = RehAuditLog::query()->create(['record_kind' => 'unit', 'record_id' => $this->unit->id, 'object_id' => $this->unit->object_id, 'global_id' => $this->unit->global_id, 'layer_id' => (int) config('reh.units_layer'), 'user_id' => $this->manager->id, 'field' => 'floor_number', 'sensitive' => false, 'values' => ['before' => 0, 'after' => 1], 'reason' => 'تصحيح يدوي سابق', 'status' => 'completed']);
    $html = $this->getJson(route('reh.units.show', $this->unit))->assertOk()->json('html');
    expect($html)->toContain('data-audit-origin="sync"', 'data-audit-origin="manual"', 'منفذ المزامنة التجريبي', 'arcgis.collector', 'PRIVATE-SYNC-ANSWER', 'استُقبل من ArcGIS', 'حُفظ في المصدرين', 'data-audit-run-history="'.$this->run->id.'"');
    $engineer = User::factory()->create();
    $engineer->assignRole('مدقق هندسي REH');
    $this->actingAs($engineer);
    $html = $this->getJson(route('reh.units.show', $this->unit))->assertOk()->json('html');
    $visible = app(RehFieldAudit::class)->history($this->unit, $engineer);
    expect($visible->pluck('field')->sort()->values()->all())->toBe(['boq_1_1_water_tank_repair_qty', 'floor_number'])
        ->and($html)->not->toContain('PRIVATE-SYNC-ANSWER', 'data-audit-log-field="household_size"')
        ->and($html)->toContain('data-audit-group-count>1 تغيير', 'data-audit-log="'.$manual->id.'"');
    $this->actingAs($this->manager);
    $response = $this->get(route('reh.units.show', $this->unit))->assertOk();
    file_put_contents(sys_get_temp_dir().'/phc-reh-sync-audit-preview.html', $response->getContent());
});
