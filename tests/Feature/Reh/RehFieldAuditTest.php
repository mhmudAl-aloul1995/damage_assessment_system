<?php

use App\Models\User;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehFieldAudit;
use App\Modules\Reh\Services\RehImporter;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->auditor = User::factory()->create();
    $this->auditor->assignRole('REH Module Manager');
    $this->actingAs($this->auditor);
    config(['reh.service_url' => 'https://reh.test/FeatureServer', 'reh.username' => 'test', 'reh.password' => 'test', 'reh.buildings_layer' => 0, 'reh.units_layer' => 1]);
    Cache::flush();
    Http::preventStrayRequests();
});

it('keeps unit and building files readable before the audit migration while safely blocking writes', function (): void {
    $unit = rehAuditUnit();
    $schema = (new RehAuditLog)->getConnection()->getSchemaBuilder();
    $schema->rename('reh_audit_logs', 'reh_audit_logs_pending_setup');
    Http::fake();
    try {
        foreach ([route('reh.units.show', $unit), route('reh.buildings.show', $unit->building)] as $url) {
            $html = $this->getJson($url)->assertOk()->json('html');
            expect($html)->toContain(RehFieldAudit::SETUP_MESSAGE)->not->toContain('data-audit-form', 'data-action-form');
        }
        $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit))->assertStatus(503)->assertJsonPath('message', RehFieldAudit::SETUP_MESSAGE);
        $this->postJson(route('reh.units.review', $unit), ['action' => 'engineering_approve', 'version' => 1, 'note' => 'مراجعة هندسية'])->assertStatus(503);
        $this->postJson(route('reh.audit.verify', ['log' => 1]))->assertStatus(503);
        expect($unit->fresh()->source['boq_1_1_water_tank_repair_qty'])->toBe(2)->and($unit->reviews()->count())->toBe(0);
        Http::assertNothingSent();
    } finally {
        $schema->rename('reh_audit_logs_pending_setup', 'reh_audit_logs');
    }
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->toContain('data-audit-form', 'data-action-form')->not->toContain(RehFieldAudit::SETUP_MESSAGE);
});

function rehAuditUnit(array $attributes = []): RehUnit
{
    $unit = RehUnit::factory()->create($attributes);
    $unit->update(['source' => [...$unit->source, 'parentglobalid' => '{'.strtoupper($unit->building->global_id).'}']]);

    return $unit;
}

function rehAuditPayload(RehUnit|RehBuilding $record, string $field = 'boq_1_1_water_tank_repair_qty', string $value = '3'): array
{
    return ['field' => $field, 'value' => $value, 'revision' => app(RehFieldAudit::class)->revision($record->fresh()), 'reason' => 'مطابقة الإجابة مع الزيارة الميدانية'];
}

function rehAuditFake(RehUnit|RehBuilding $record, string $field = 'boq_1_1_water_tank_repair_qty', array $metadata = []): stdClass
{
    $state = (object) ['source' => [...$record->source, $field => $record->source[$field] ?? null], 'writes' => 0, 'queries' => 0, 'mode' => 'success'];
    Http::fake(function ($request) use ($state, $record, $field, $metadata) {
        if (str_contains($request->url(), 'generateToken')) {
            return Http::response(['token' => 'fake-token']);
        }
        if (str_contains($request->url(), '/query')) {
            $state->queries++;
            if ($state->mode === 'uncertain' && $state->writes > 0) {
                return Http::failedConnection();
            }

            return Http::response(['features' => [['attributes' => $state->source]]]);
        }
        if (str_contains($request->url(), '/updateFeatures')) {
            $state->writes++;
            if ($state->mode === 'reject') {
                return Http::response(['updateResults' => [['objectId' => $record->object_id, 'success' => false, 'error' => ['description' => 'PRIVATE SERVICE MESSAGE']]]]);
            }
            $changes = json_decode($request['features'], true)[0]['attributes'];
            $state->source = [...$state->source, ...$changes];
            if (in_array($state->mode, ['uncertain', 'lost-response'], true)) {
                return Http::failedConnection();
            }

            return Http::response(['updateResults' => [['objectId' => $record->object_id, 'success' => true]]]);
        }

        return Http::response(['fields' => [['name' => 'objectid'], ['name' => 'globalid'], ['name' => 'parentglobalid'], ['name' => $field, 'editable' => true, 'type' => 'esriFieldTypeString', 'length' => 10000, ...$metadata]]]);
    });

    return $state;
}

it('saves a selected survey answer using its code and displays the Arabic label in the audit history', function (): void {
    $unit = rehAuditUnit();
    $state = rehAuditFake($unit, 'refusal_reason', ['domain' => ['type' => 'codedValue', 'codedValues' => [['code' => 'other_reason', 'name' => 'سبب آخر']]]]);
    $html = $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit, 'refusal_reason', 'other_reason'))->assertOk()->json('html');
    expect($unit->fresh()->source['refusal_reason'])->toBe('other_reason')
        ->and($state->source['refusal_reason'])->toBe('other_reason')
        ->and(RehAuditLog::query()->sole()->values['after'])->toBe('other_reason')
        ->and($html)->toContain('سبب آخر', 'حُفظ في المصدرين');
});

it('saves a correction in the base source and ArcGIS without assignment and keeps an attributed field log', function (): void {
    $unit = rehAuditUnit(['reviewer_id' => User::factory()->create()->id, 'engineering_status' => 'approved', 'social_status' => 'approved', 'quantity_overrides' => ['boq_1_1_water_tank_repair_qty' => 9]]);
    $state = rehAuditFake($unit);
    $payload = rehAuditPayload($unit);
    $response = $this->postJson(route('reh.audit.save', ['unit', $unit->id]), $payload)->assertOk();
    expect($unit->fresh()->source['boq_1_1_water_tank_repair_qty'])->toBe(3)
        ->and($unit->fresh()->quantity_overrides)->toBe([])
        ->and($unit->fresh()->engineering_status)->toBe('pending')
        ->and($unit->fresh()->social_status)->toBe('pending')
        ->and($unit->reviews()->count())->toBe(1)
        ->and($state->writes)->toBe(1)
        ->and($state->source['boq_1_1_water_tank_repair_qty'])->toBe(3);
    $log = RehAuditLog::query()->sole();
    expect($log->status)->toBe('completed')->and($log->user_id)->toBe($this->auditor->id)
        ->and($log->object_id)->toBe($unit->object_id)->and($log->values)->toBe(['before' => 2, 'after' => 3, 'previous_override' => 9]);
    expect($response->json('html'))->toContain('حُفظ في المصدرين', 'data-audit-log="'.$log->id.'"');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/1/updateFeatures') && array_keys(json_decode($request['features'], true)[0]['attributes']) === ['objectid', 'boq_1_1_water_tank_repair_qty']);
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), $payload)->assertConflict();
    expect($state->writes)->toBe(1);
    $version = $unit->fresh()->version;
    app(RehImporter::class)->unit(['attributes' => $state->source]);
    expect($unit->fresh()->version)->toBe($version);
});

it('updates projected owner columns and restricts social corrections and logs to sensitive reviewers', function (): void {
    $unit = rehAuditUnit();
    $user = User::factory()->create();
    $user->assignRole('مدقق اجتماعي REH');
    $state = rehAuditFake($unit, 'owner_full_name');
    $this->actingAs($user)->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit, 'owner_full_name', 'TEST-CORRECTED-OWNER'))->assertOk();
    expect($unit->fresh()->owner_name)->toBe('TEST-CORRECTED-OWNER')->and(RehAuditLog::query()->sole()->sensitive)->toBeTrue();
    $engineer = User::factory()->create();
    $engineer->assignRole('مدقق هندسي REH');
    $this->actingAs($engineer)->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit->fresh(), 'owner_full_name', 'blocked'))->assertForbidden();
    $html = $this->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->not->toContain('data-audit-log="', 'data-audit-field="owner_full_name"');
    expect($state->writes)->toBe(1);
});

it('updates building projections and invalidates all its units', function (): void {
    $building = RehBuilding::factory()->create();
    $units = RehUnit::factory()->count(2)->create(['building_id' => $building->id, 'engineering_status' => 'approved', 'social_status' => 'approved']);
    $state = rehAuditFake($building, 'building_name');
    $this->postJson(route('reh.audit.save', ['building', $building->id]), rehAuditPayload($building, 'building_name', 'UPDATED BUILDING'))->assertOk();
    expect($building->fresh()->name)->toBe('UPDATED BUILDING')->and($state->writes)->toBe(1);
    foreach ($units as $unit) {
        expect($unit->fresh()->engineering_status)->toBe('pending')->and($unit->fresh()->social_status)->toBe('pending');
    }
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/0/updateFeatures'));
});

it('forbids protected fields and wrong reviewer scopes before contacting ArcGIS', function (string $role, string $field): void {
    $unit = rehAuditUnit();
    $user = User::factory()->create();
    $user->assignRole($role);
    Http::fake();
    $this->actingAs($user)->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit, $field))->assertForbidden();
    Http::assertNothingSent();
})->with([
    ['REH Viewer', 'boq_1_1_water_tank_repair_qty'], ['REH Project Officer', 'boq_1_1_water_tank_repair_qty'],
    ['مدقق اجتماعي REH', 'boq_1_1_water_tank_repair_qty'], ['مدقق هندسي REH', 'owner_identity_number'],
    ['REH Module Manager', 'globalid'], ['REH Module Manager', 'objectid'], ['REH Module Manager', 'parentglobalid'],
]);

it('requires a reason and a nonblank typed value', function (array $changes): void {
    $unit = rehAuditUnit();
    Http::fake();
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), [...rehAuditPayload($unit), ...$changes])->assertUnprocessable();
    Http::assertNothingSent();
})->with([[['reason' => '']], [['value' => '']], [['value' => ['3']]], [['revision' => 'old']]]);

it('rejects fractional integer quantities invalid choices and remote constraints before writes', function (string $field, string $value, array $metadata): void {
    $unit = rehAuditUnit();
    $state = rehAuditFake($unit, $field, $metadata);
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit, $field, $value))->assertUnprocessable();
    expect($state->writes)->toBe(0)->and(RehAuditLog::query()->count())->toBe(0);
})->with([
    ['boq_1_1_water_tank_repair_qty', '1.5', []], ['boq_1_1_water_tank_repair_qty', '-1', []],
    ['owner_sex', 'unknown-choice', []], ['owner_full_name', 'too long', ['length' => 3]],
    ['boq_1_1_water_tank_repair_qty', '3', ['domain' => ['type' => 'range', 'range' => [0, 2]]]],
    ['boq_1_1_water_tank_repair_qty', '3', ['editable' => false]],
]);

it('rejects stale local and remote changes and wrong identities without writing', function (string $case): void {
    $unit = rehAuditUnit();
    $payload = rehAuditPayload($unit);
    $state = rehAuditFake($unit);
    match ($case) {
        'local' => $unit->update(['version' => 2]),
        'remote' => $state->source['boq_1_1_water_tank_repair_qty'] = 8,
        'identity' => $state->source['globalid'] = fake()->uuid(),
        'parent' => $state->source['parentglobalid'] = fake()->uuid(),
    };
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), $payload)->assertConflict();
    expect($state->writes)->toBe(0)->and(RehAuditLog::query()->count())->toBe(0);
})->with(['local', 'remote', 'identity', 'parent']);

it('does not save local values when ArcGIS rejects a feature and keeps a failed log', function (): void {
    $unit = rehAuditUnit();
    $state = rehAuditFake($unit);
    $state->mode = 'reject';
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit))->assertStatus(502)->assertDontSee('PRIVATE SERVICE MESSAGE');
    expect($unit->fresh()->source['boq_1_1_water_tank_repair_qty'])->toBe(2)
        ->and(RehAuditLog::query()->sole()->status)->toBe('failed')->and($unit->fresh()->reviews()->count())->toBe(0);
});

it('recovers a lost remote response by reading back without resending the write', function (): void {
    $unit = rehAuditUnit();
    $state = rehAuditFake($unit);
    $state->mode = 'lost-response';
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit))->assertOk();
    expect($state->writes)->toBe(1)->and($state->queries)->toBe(2)->and(RehAuditLog::query()->sole()->status)->toBe('completed');
});

it('blocks further edits and approval for uncertain outcomes then reconciles without a second remote write', function (): void {
    $unit = rehAuditUnit();
    $state = rehAuditFake($unit);
    $state->mode = 'uncertain';
    $payload = rehAuditPayload($unit);
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), $payload)->assertStatus(502);
    expect($unit->fresh()->source['boq_1_1_water_tank_repair_qty'])->toBe(2);
    $log = RehAuditLog::query()->sole();
    expect($log->status)->toBe('needs_review');
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), $payload)->assertConflict();
    $this->postJson(route('reh.units.review', $unit), ['action' => 'engineering_approve', 'version' => 1, 'note' => 'اعتماد المراجعة'])->assertConflict();
    $state->mode = 'success';
    $this->postJson(route('reh.audit.verify', $log))->assertOk();
    expect($unit->fresh()->source['boq_1_1_water_tank_repair_qty'])->toBe(3)
        ->and($log->fresh()->status)->toBe('completed')->and($state->writes)->toBe(1);
});

it('keeps remote success visible for reconciliation if local persistence fails', function (): void {
    $unit = rehAuditUnit();
    $state = rehAuditFake($unit);
    RehUnit::updating(function () use ($state): void {
        if ($state->writes > 0) {
            throw new RuntimeException('SIMULATED LOCAL FAILURE');
        }
    });
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit))->assertStatus(502)->assertDontSee('SIMULATED LOCAL FAILURE');
    expect(RehAuditLog::query()->sole()->status)->toBe('needs_review')
        ->and($unit->fresh()->source['boq_1_1_water_tank_repair_qty'])->toBe(2)->and($state->source['boq_1_1_water_tank_repair_qty'])->toBe(3);
});

it('blocks writes during synchronization and for decided units or their building', function (): void {
    $unit = rehAuditUnit(['decision' => 'selected']);
    Http::fake();
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit))->assertConflict();
    $this->postJson(route('reh.audit.save', ['building', $unit->building_id]), rehAuditPayload($unit->building, 'building_name', 'new'))->assertConflict();
    $unit->update(['decision' => 'pending']);
    $lock = Cache::lock('reh:sync:execution', 60);
    $lock->get();
    try {
        $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit))->assertConflict();
    } finally {
        $lock->release();
    }
    Http::assertNothingSent();
});

it('reconciles a rejected or superseded uncertain attempt without rewriting the remote record', function (bool $superseded): void {
    $unit = rehAuditUnit();
    $state = rehAuditFake($unit);
    $state->mode = 'uncertain';
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit))->assertStatus(502);
    $log = RehAuditLog::query()->sole();
    $state->mode = 'success';
    $state->source['boq_1_1_water_tank_repair_qty'] = $superseded ? 5 : 2;
    if ($superseded) {
        $this->postJson(route('reh.audit.verify', $log))->assertConflict();
        app(RehImporter::class)->unit(['attributes' => $state->source]);
    }
    $this->postJson(route('reh.audit.verify', $log))->assertOk();
    expect($log->fresh()->status)->toBe($superseded ? 'superseded' : 'failed')->and($state->writes)->toBe(1);
})->with([true, false]);

it('allows zero and fractional decimal quantities in the source while preserving their scoring inputs', function (string $field, string $value, int|float $expected): void {
    $unit = rehAuditUnit();
    $state = rehAuditFake($unit, $field);
    $this->postJson(route('reh.audit.save', ['unit', $unit->id]), rehAuditPayload($unit, $field, $value))->assertOk();
    expect($unit->fresh()->source[$field])->toBe($expected)->and($state->source[$field])->toBe($expected);
})->with([['boq_1_1_water_tank_repair_qty', '0', 0], ['boq_2_3_partition_wall_qty', '4.5', 4.5]]);
