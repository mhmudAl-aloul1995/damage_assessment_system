<?php

use App\Models\User;
use App\Modules\Reh\Jobs\DeleteRehUnits;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehBulkUnitDeletion;
use App\Modules\Reh\Services\RehSyncWorker;
use App\Modules\Reh\Services\RehUnitMaintenance;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    Role::findOrCreate('Database Officer', 'web');
    $this->officer = User::factory()->create();
    $this->officer->assignRole('Database Officer');
    $this->actingAs($this->officer);
    Queue::fake();
    Cache::flush();
    Http::preventStrayRequests();
    $this->mock(RehSyncWorker::class)->shouldReceive('start')->andReturn(true);
});

function rehBulkPreview(array $units): array
{
    return ['scope' => 'selected', 'filters' => ['kind' => 'all'], 'units' => array_map(fn (RehUnit $unit): array => ['id' => $unit->id, 'revision' => app(RehUnitMaintenance::class)->revision($unit->fresh())], $units)];
}

function rehBulkConfirm(string $token, string $destination = 'database'): array
{
    return ['selection_token' => $token, 'destination' => $destination, 'reason' => 'تنظيف السجلات المحددة بعد المراجعة', 'confirm' => true];
}

it('freezes all matching units across pages and respects exclusions without selecting later additions', function (): void {
    $building = RehBuilding::factory()->create();
    $units = RehUnit::factory()->count(52)->create(['building_id' => $building->id, 'source' => ['OldUnit_objectID' => null]]);
    $outside = RehUnit::factory()->create(['building_id' => $building->id, 'source' => ['OldUnit_objectID' => 900]]);
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), ['scope' => 'filtered', 'filters' => ['kind' => 'missing'], 'excluded' => [$units->last()->id]])->assertOk()->assertJsonPath('total', 51)->json();
    $later = RehUnit::factory()->create(['building_id' => $building->id, 'source' => ['OldUnit_objectID' => null]]);
    $response = $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertAccepted()->assertJsonPath('operation.total', 51);
    $log = RehAuditLog::query()->find($response->json('operation.id'));
    expect(array_column($log->values['units'], 'id'))->not->toContain($outside->id, $later->id, $units->last()->id)
        ->and(array_column($log->values['units'], 'id'))->toContain($units[50]->id);
    expect(RehUnit::query()->count())->toBe(54);
    Queue::assertPushed(DeleteRehUnits::class, 1);
    Http::assertNothingSent();
});

it('queues only selected units and reuses the confirmed request without enqueuing duplicate deletion jobs', function (): void {
    $units = RehUnit::factory()->count(3)->create();
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), rehBulkPreview([$units[0], $units[1]]))->assertOk()->assertJsonPath('total', 2)->json();
    $data = rehBulkConfirm($preview['selection_token']);
    $first = $this->postJson(route('reh.maintenance.bulk.destroy'), $data)->assertAccepted()->json('operation.id');
    $this->postJson(route('reh.maintenance.bulk.destroy'), $data)->assertAccepted()->assertJsonPath('operation.id', $first);
    Queue::assertPushed(DeleteRehUnits::class, 1);
    (new DeleteRehUnits($first))->handle(app(RehBulkUnitDeletion::class));
    (new DeleteRehUnits($first))->handle(app(RehBulkUnitDeletion::class));
    expect(RehUnit::query()->pluck('id')->all())->toBe([$units[2]->id])
        ->and(RehAuditLog::query()->where('record_kind', 'unit_deletion')->count())->toBe(2);
    $this->getJson(route('reh.maintenance.bulk.status', $first))->assertOk()->assertJsonPath('operation.status', 'completed')->assertJsonPath('operation.counts.completed', 2)->assertJsonPath('operation.processed', 2);
});

it('deletes the frozen duplicate group even when deleting the first member changes filter membership', function (): void {
    RehUnit::factory()->count(2)->create(['source' => ['OldUnit_objectID' => 10613]]);
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), ['scope' => 'filtered', 'filters' => ['kind' => 'duplicate']])->assertOk()->assertJsonPath('total', 2)->json();
    $id = $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertAccepted()->json('operation.id');
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    expect(RehUnit::query()->count())->toBe(0)->and(RehAuditLog::query()->find($id)->status)->toBe('completed');
});

it('skips changed units while continuing with unchanged selected units', function (): void {
    $units = RehUnit::factory()->count(2)->create();
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), rehBulkPreview($units->all()))->assertOk()->json();
    $id = $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertAccepted()->json('operation.id');
    $units[0]->update(['owner_name' => 'تعديل جديد بعد التأكيد']);
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    expect($units[0]->fresh())->not->toBeNull()->and($units[1]->fresh())->toBeNull();
    $this->getJson(route('reh.maintenance.bulk.status', $id))->assertJsonPath('operation.status', 'partial')->assertJsonPath('operation.counts.skipped', 1)->assertJsonPath('operation.counts.completed', 1);
    $this->getJson(route('reh.maintenance.bulk.status', $id))->assertJsonPath('operation.issue_count', 1)->assertJsonPath('operation.issues.0.object_id', $units[0]->object_id);
});

it('waits for the shared synchronization lock without counting units as skipped or sending remote deletes', function (): void {
    $unit = RehUnit::factory()->create();
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), rehBulkPreview([$unit]))->assertOk()->json();
    $id = $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertAccepted()->json('operation.id');
    $lock = Cache::lock('reh:sync:execution', 600);
    $lock->get();
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    $this->getJson(route('reh.maintenance.bulk.status', $id))->assertJsonPath('operation.active', true)->assertJsonPath('operation.processed', 0)->assertJsonPath('operation.counts.queued', 1);
    Queue::assertPushed(DeleteRehUnits::class, 2);
    $lock->release();
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    expect($unit->fresh())->toBeNull();
    Http::assertNothingSent();
});

it('retains an uncertain ArcGIS result for per-unit verification and never retries its delete automatically', function (): void {
    config(['reh.username' => 'test', 'reh.password' => 'test']);
    $unit = RehUnit::factory()->create();
    $untouched = RehUnit::factory()->create();
    Http::fake(function ($request) use ($unit) {
        if (str_contains($request->url(), 'generateToken')) {
            return Http::response(['token' => 'fake']);
        }
        if (str_contains($request->url(), '/query')) {
            return Http::response(['features' => [['attributes' => ['objectid' => $unit->object_id, 'globalid' => $unit->global_id]]]]);
        }

        return Http::response([]);
    });
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), rehBulkPreview([$unit, $untouched]))->assertOk()->json();
    $id = $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token'], 'both'))->assertAccepted()->json('operation.id');
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    expect($unit->fresh())->not->toBeNull();
    expect($untouched->fresh())->not->toBeNull();
    $this->getJson(route('reh.maintenance.bulk.status', $id))->assertJsonPath('operation.counts.needs_review', 1)->assertJsonPath('operation.status', 'partial')->assertJsonPath('operation.counts.queued', 1);
    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), '/deleteFeatures')))->toHaveCount(1);
    expect(RehAuditLog::query()->where('record_kind', 'unit_deletion')->sole()->values['bulk_operation_id'])->toBe($id);
});

it('binds previews to their owner requires confirmation and rejects expired or changed selections', function (): void {
    $unit = RehUnit::factory()->create();
    $data = rehBulkPreview([$unit]);
    $unit->update(['owner_name' => 'تغير قبل المعاينة']);
    $this->postJson(route('reh.maintenance.bulk.preview'), $data)->assertConflict();
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), rehBulkPreview([$unit]))->assertOk()->json();
    $this->postJson(route('reh.maintenance.bulk.destroy'), [...rehBulkConfirm($preview['selection_token']), 'confirm' => false])->assertUnprocessable();
    $other = User::factory()->create();
    $other->assignRole('Database Officer');
    $this->actingAs($other)->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertConflict();
    $this->actingAs($this->officer);
    $this->travel(11)->minutes();
    $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertConflict();
    Queue::assertNothingPushed();
});

it('enforces the officer role on every bulk route and stops jobs if the actor loses the role', function (): void {
    $unit = RehUnit::factory()->create();
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), rehBulkPreview([$unit]))->assertOk()->json();
    $id = $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertAccepted()->json('operation.id');
    $this->officer->removeRole('Database Officer');
    $this->officer->givePermissionTo('reh.view');
    $this->postJson(route('reh.maintenance.bulk.preview'), rehBulkPreview([$unit]))->assertForbidden();
    $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertForbidden();
    $this->getJson(route('reh.maintenance.bulk.status', $id))->assertForbidden();
    $this->postJson(route('reh.maintenance.bulk.start', $id))->assertForbidden();
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    expect($unit->fresh())->not->toBeNull()->and(RehAuditLog::query()->find($id)->status)->toBe('failed');
});

it('continues in bounded background chunks and keeps progress durable', function (): void {
    $units = RehUnit::factory()->count(7)->create();
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), rehBulkPreview($units->all()))->assertOk()->json();
    $id = $this->postJson(route('reh.maintenance.bulk.destroy'), rehBulkConfirm($preview['selection_token']))->assertAccepted()->json('operation.id');
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    $this->getJson(route('reh.maintenance.bulk.status', $id))->assertJsonPath('operation.counts.completed', 5)->assertJsonPath('operation.active', true);
    Queue::assertPushed(DeleteRehUnits::class, 2);
    expect(Queue::pushed(DeleteRehUnits::class)->every(fn (DeleteRehUnits $job): bool => $job->delay === null))->toBeTrue();
    (new DeleteRehUnits($id))->handle(app(RehBulkUnitDeletion::class));
    $this->getJson(route('reh.maintenance.bulk.status', $id))->assertJsonPath('operation.counts.completed', 7)->assertJsonPath('operation.active', false);
    $this->get(route('reh.maintenance.index'))->assertOk()->assertSee('متابعة طلب الحذف الجماعي');
});
