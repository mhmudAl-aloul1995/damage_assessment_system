<?php

use App\Models\User;
use App\Modules\Reh\Jobs\RestoreRehUnit;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehCostEstimate;
use App\Modules\Reh\Models\RehCostLine;
use App\Modules\Reh\Models\RehPriceBook;
use App\Modules\Reh\Models\RehReview;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehDeletionSnapshot;
use App\Modules\Reh\Services\RehSyncWorker;
use App\Modules\Reh\Services\RehUnitMaintenance;
use App\Modules\Reh\Services\RehUnitRestoration;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    Role::findOrCreate('Database Officer', 'web');
    $this->officer = User::factory()->create();
    $this->officer->assignRole('Database Officer');
    $this->actingAs($this->officer);
    Queue::fake();
    Cache::flush();
    Storage::fake('local');
    Http::preventStrayRequests();
    config(['reh.username' => 'test', 'reh.password' => 'test']);
    $this->mock(RehSyncWorker::class)->shouldReceive('start')->andReturn(true);
});

function rehRestoreDelete(RehUnit $unit, string $destination = 'database'): RehAuditLog
{
    return app(RehUnitMaintenance::class)->delete($unit, test()->officer, ['destination' => $destination, 'revision' => app(RehUnitMaintenance::class)->revision($unit->fresh()), 'reason' => 'حذف سجل مكرر بعد المقارنة']);
}

function rehRestoreRemote(RehUnit $unit, bool $uncertain = false, bool $badAttachment = false): object
{
    $feature = ['attributes' => [...$unit->source, 'objectid' => $unit->object_id, 'globalid' => $unit->global_id, 'parentglobalid' => $unit->building->global_id], 'geometry' => ['x' => 34.5, 'y' => 31.5, 'spatialReference' => ['wkid' => 4326]]];
    $content = 'archived-test-image';
    $attachment = ['id' => 9, 'globalId' => fake()->uuid(), 'name' => 'photo.jpg', 'contentType' => 'image/jpeg', 'size' => strlen($content)];
    $state = (object) ['feature' => $feature, 'attachments' => [$attachment], 'adds' => 0, 'attachmentAdds' => 0, 'deletes' => 0, 'uncertain' => $uncertain, 'supportsRestore' => true];
    Http::fake(function ($request) use ($unit, $state, $content, $badAttachment) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if (str_contains($path, 'generateToken')) {
            return Http::response(['token' => 'test-token']);
        }
        if (str_ends_with($path, '/'.config('reh.units_layer'))) {
            return Http::response(['fields' => array_map(fn (string $name): array => ['name' => $name, 'editable' => $name !== 'objectid'], array_values(array_unique(['objectid', 'globalid', 'parentglobalid', ...array_keys($unit->source)]))), 'supportsApplyEditsWithGlobalIds' => $state->supportsRestore, 'hasAttachments' => true]);
        }
        if (str_ends_with($path, '/query')) {
            if (str_contains($path, '/'.config('reh.buildings_layer').'/query')) {
                return Http::response(['features' => [['attributes' => ['objectid' => $unit->building->object_id, 'globalid' => $unit->building->global_id]]]]);
            }

            return Http::response(['features' => $state->feature ? [$state->feature] : []]);
        }
        if (str_ends_with($path, '/attachments')) {
            return Http::response(['attachmentInfos' => $state->attachments]);
        }
        if (str_contains($path, '/attachments/')) {
            return Http::response($badAttachment ? 'short' : $content, 200, ['Content-Type' => 'image/jpeg']);
        }
        if (str_ends_with($path, '/deleteFeatures')) {
            $state->feature = null;
            $state->attachments = [];
            $state->deletes++;

            return Http::response(['deleteResults' => [['objectId' => $unit->object_id, 'success' => true]]]);
        }
        if (str_ends_with($path, '/applyEdits')) {
            if (isset($request['adds'])) {
                $state->adds++;
                $state->feature = json_decode($request['adds'], true)[0];
                $state->feature['attributes']['objectid'] = 99001;
                if ($state->uncertain) {
                    $state->uncertain = false;

                    return Http::response([]);
                }

                return Http::response(['addResults' => [['objectId' => 99001, 'globalId' => $unit->global_id, 'success' => true]]]);
            }
            $attachment = json_decode($request['attachments'], true)['adds'][0];
            $state->attachmentAdds++;
            $state->attachments[] = ['id' => 55, 'globalId' => $attachment['globalId'], 'name' => $attachment['name'], 'contentType' => $attachment['contentType'], 'size' => strlen(base64_decode($attachment['data']))];

            return Http::response(['attachments' => ['addResults' => [['objectId' => 55, 'globalId' => $attachment['globalId'], 'success' => true]]]]);
        }

        return Http::response([], 500);
    });

    return $state;
}

it('snapshots every raw field and linked review and financial row before local deletion and restores them exactly once', function (): void {
    $unit = RehUnit::factory()->create(['source' => ['OldUnit_objectID' => 10613, 'household_size' => 5, 'household_chronic_count' => 0, 'unmapped_field' => 'keep all source values'], 'quantity_overrides' => ['boq_1_1_water_tank_repair_qty' => 3], 'engineering_status' => 'approved', 'decision' => 'selected']);
    $unit->update(['household_size' => 9]);
    $review = $unit->reviews()->create(['user_id' => $this->officer->id, 'action' => 'engineering_approve', 'note' => 'مراجعة محفوظة', 'changes' => ['before' => null, 'after' => 0]]);
    $estimate = RehCostEstimate::query()->create(['unit_id' => $unit->id, 'book_id' => RehPriceBook::factory()->create()->id, 'fingerprint' => str_repeat('b', 64), 'total' => 60, 'exchange_rate' => 3, 'total_usd' => 20, 'approved_by' => $this->officer->id, 'reason' => 'اعتماد تكلفة']);
    $line = $estimate->lines()->create(['code' => '1.1', 'field' => 'boq_1_1_water_tank_repair_qty', 'label' => 'صيانة خزان', 'unit' => 'عدد', 'quantity' => 2, 'rate' => 30, 'amount' => 60]);
    $before = $unit->fresh()->getAttributes();
    $reviewBefore = $review->fresh()->getAttributes();
    $estimateBefore = $estimate->fresh()->getAttributes();
    $lineBefore = $line->fresh()->getAttributes();
    $deletion = rehRestoreDelete($unit);
    expect($unit->fresh())->toBeNull()->and(app(RehDeletionSnapshot::class)->read($deletion)['unit'])->toBe($before);
    $this->get(route('reh.maintenance.snapshot', $deletion))->assertOk()->assertJsonPath('unit.household_size', 9)->assertHeader('Cache-Control', 'no-store, private');
    $payload = ['reason' => 'استرجاع الحذف غير المقصود', 'confirm' => true];
    $this->postJson(route('reh.maintenance.restore', $deletion), $payload)->assertAccepted();
    $this->postJson(route('reh.maintenance.restore', $deletion), $payload)->assertAccepted();
    $restore = RehAuditLog::query()->where('record_kind', 'unit_restore')->sole();
    Queue::assertPushed(RestoreRehUnit::class, 1);
    (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    expect($restore->fresh()->status)->toBe('completed')->and($unit->fresh()->getAttributes())->toBe($before)
        ->and($review->fresh()->getAttributes())->toBe($reviewBefore)->and($estimate->fresh()->getAttributes())->toBe($estimateBefore)->and($line->fresh()->getAttributes())->toBe($lineBefore);
    (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    expect(RehUnit::query()->count())->toBe(1)->and(RehReview::query()->count())->toBe(1)->and(RehCostEstimate::query()->count())->toBe(1)->and(RehCostLine::query()->count())->toBe(1);
    $this->get(route('reh.maintenance.index'))->assertOk()->assertSee('نسخة كاملة محفوظة')->assertSee('فتح الوحدة المسترجعة');
    Http::assertNothingSent();
});

it('requires explicit restoration confirmation and protects snapshots and restoration endpoints by role', function (): void {
    $deletion = rehRestoreDelete(RehUnit::factory()->create());
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => '', 'confirm' => false])->assertUnprocessable()->assertJsonValidationErrors(['reason', 'confirm']);
    $user = User::factory()->create();
    $user->givePermissionTo('reh.view');
    $this->actingAs($user);
    $this->get(route('reh.maintenance.snapshot', $deletion))->assertForbidden();
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'طلب استرجاع', 'confirm' => true])->assertForbidden();
    Queue::assertNothingPushed();
});

it('refuses corrupted snapshots and occupied identifiers before queuing restoration', function (string $conflict): void {
    $unit = RehUnit::factory()->create();
    $deletion = rehRestoreDelete($unit);
    if ($conflict === 'checksum') {
        $values = $deletion->values;
        $values['snapshot']['unit']['owner_name'] = 'tampered';
        $deletion->update(['values' => $values]);
    } else {
        RehUnit::factory()->create(['global_id' => $unit->global_id]);
    }
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'طلب استرجاع', 'confirm' => true])->assertConflict();
    Queue::assertNothingPushed();
})->with(['checksum', 'existing_global_id']);

it('backs up remote fields geometry and attachment bytes and restores to the original destination with a new current object id', function (string $destination): void {
    $unit = RehUnit::factory()->create(['source' => ['OldUnit_objectID' => 10613, 'household_size' => 5]]);
    $state = rehRestoreRemote($unit);
    $deletion = rehRestoreDelete($unit, $destination);
    $snapshot = app(RehDeletionSnapshot::class)->read($deletion);
    expect($snapshot['remote']['feature']['attributes']['household_size'])->toBe(5)->and($snapshot['remote']['feature']['geometry']['x'])->toBe(34.5);
    Storage::disk('local')->assertExists($snapshot['remote']['attachments'][0]['path']);
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'استرجاع النسخة كاملة', 'confirm' => true])->assertAccepted();
    $restore = RehAuditLog::query()->where('record_kind', 'unit_restore')->sole();
    (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    expect($restore->fresh()->status)->toBe('completed')->and($state->adds)->toBe(1)->and($state->attachmentAdds)->toBe(1)
        ->and($unit->fresh()->global_id)->toBe($unit->global_id)->and($unit->fresh()->object_id)->toBe(99001)->and($unit->fresh()->source['objectid'])->toBe(99001)
        ->and($unit->fresh()->old_objectid)->toBe('10613')->and($unit->fresh()->source_missing)->toBeFalse();
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/applyEdits') && $request['useGlobalIds'] === 'true' && $request['rollbackOnFailure'] === 'true');
})->with(['both', 'arcgis']);

it('reconciles an interrupted remote restore without adding the feature twice', function (): void {
    $unit = RehUnit::factory()->create();
    $state = rehRestoreRemote($unit, true);
    $deletion = rehRestoreDelete($unit, 'both');
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'استرجاع النسخة كاملة', 'confirm' => true])->assertAccepted();
    $restore = RehAuditLog::query()->where('record_kind', 'unit_restore')->sole();
    (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    expect($restore->fresh()->status)->toBe('needs_review')->and($unit->fresh())->toBeNull()->and($state->adds)->toBe(1);
    $this->postJson(route('reh.maintenance.restore.resume', $restore))->assertAccepted();
    (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    expect($restore->fresh()->status)->toBe('completed')->and($unit->fresh())->not->toBeNull()->and($state->adds)->toBe(1)->and($state->attachmentAdds)->toBe(1);
});

it('keeps both sources untouched if a complete attachment backup cannot be made', function (): void {
    $unit = RehUnit::factory()->create();
    $state = rehRestoreRemote($unit, false, true);
    $this->deleteJson(route('reh.maintenance.destroy', $unit), ['destination' => 'both', 'revision' => app(RehUnitMaintenance::class)->revision($unit->fresh()), 'reason' => 'مراجعة قبل الحذف', 'confirm' => true])->assertUnprocessable();
    expect($unit->fresh())->not->toBeNull()->and($state->deletes)->toBe(0)->and(RehAuditLog::query()->sole()->status)->toBe('failed');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/deleteFeatures'));
});

it('does not overwrite an ArcGIS-only local record changed after deletion', function (): void {
    $unit = RehUnit::factory()->create();
    rehRestoreRemote($unit);
    $deletion = rehRestoreDelete($unit, 'arcgis');
    $unit->fresh()->update(['owner_name' => 'تعديل أحدث']);
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'استرجاع النسخة', 'confirm' => true])->assertConflict();
    Queue::assertNothingPushed();
    expect($unit->fresh()->owner_name)->toBe('تعديل أحدث');
});

it('stops queued restoration if the requesting user loses the database officer role', function (): void {
    $deletion = rehRestoreDelete(RehUnit::factory()->create());
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'استرجاع النسخة', 'confirm' => true])->assertAccepted();
    $restore = RehAuditLog::query()->where('record_kind', 'unit_restore')->sole();
    $this->officer->removeRole('Database Officer');
    (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    expect($restore->fresh()->status)->toBe('failed')->and(RehUnit::query()->count())->toBe(0);
});

it('refuses remote deletion when the layer cannot preserve global ids on restoration', function (): void {
    $unit = RehUnit::factory()->create();
    $state = rehRestoreRemote($unit);
    $state->supportsRestore = false;
    $this->deleteJson(route('reh.maintenance.destroy', $unit), ['destination' => 'both', 'revision' => app(RehUnitMaintenance::class)->revision($unit->fresh()), 'reason' => 'حذف سجل مكرر', 'confirm' => true])->assertUnprocessable();
    expect($unit->fresh())->not->toBeNull()->and($state->deletes)->toBe(0);
});

it('refuses to restore if an archived attachment is missing or has been changed', function (): void {
    $unit = RehUnit::factory()->create();
    rehRestoreRemote($unit);
    $deletion = rehRestoreDelete($unit, 'both');
    $path = $deletion->values['snapshot']['remote']['attachments'][0]['path'];
    Storage::disk('local')->put($path, 'changed bytes');
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'استرجاع النسخة', 'confirm' => true])->assertConflict();
    Queue::assertNothingPushed();
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/applyEdits'));
});

it('refuses a conflicting related row before restoring any data', function (): void {
    $unit = RehUnit::factory()->create();
    $review = $unit->reviews()->create(['action' => 'engineering_approve', 'note' => 'original']);
    $deletion = rehRestoreDelete($unit);
    $other = RehUnit::factory()->create();
    $other->reviews()->create(['id' => $review->id, 'action' => 'engineering_approve', 'note' => 'newer record']);
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'استرجاع النسخة', 'confirm' => true])->assertConflict();
    expect($unit->fresh())->toBeNull()->and($review->fresh()->note)->toBe('newer record');
    Queue::assertNothingPushed();
});

it('rolls back the entire local restoration if a related row fails database validation', function (): void {
    $unit = RehUnit::factory()->create();
    $unit->reviews()->create(['action' => 'engineering_approve', 'note' => 'original']);
    $deletion = rehRestoreDelete($unit);
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'استرجاع النسخة', 'confirm' => true])->assertAccepted();
    $restore = RehAuditLog::query()->where('record_kind', 'unit_restore')->sole();
    $connection = (new RehReview)->getConnection();
    $connection->statement("CREATE TRIGGER reject_restored_review BEFORE INSERT ON reh_reviews BEGIN SELECT RAISE(ABORT, 'test restoration failure'); END");
    try {
        (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    } finally {
        $connection->statement('DROP TRIGGER reject_restored_review');
    }
    expect($restore->fresh()->status)->toBe('failed')->and($unit->fresh())->toBeNull()->and(RehReview::query()->count())->toBe(0)
        ->and(app(RehDeletionSnapshot::class)->read($deletion)['unit']['id'])->toBe($unit->id);
});

it('waits for other synchronization or audit work before restoring and exposes progress only to the officer', function (): void {
    $unit = RehUnit::factory()->create();
    $deletion = rehRestoreDelete($unit);
    $this->postJson(route('reh.maintenance.restore', $deletion), ['reason' => 'استرجاع النسخة', 'confirm' => true])->assertAccepted();
    $restore = RehAuditLog::query()->where('record_kind', 'unit_restore')->sole();
    $lock = Cache::lock('reh:sync:execution', 1900);
    $lock->get();
    (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    expect($unit->fresh())->toBeNull()->and($restore->fresh()->status)->toBe('queued');
    $lock->release();
    (new RestoreRehUnit($restore->id))->handle(app(RehUnitRestoration::class));
    $this->getJson(route('reh.maintenance.restore.status', $restore))->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('url', route('reh.units.show', $unit));
    $user = User::factory()->create();
    $user->givePermissionTo('reh.view');
    $this->actingAs($user)->getJson(route('reh.maintenance.restore.status', $restore))->assertForbidden();
    $this->postJson(route('reh.maintenance.restore.resume', $restore))->assertForbidden();
});

it('keeps older deletion archives accessible through history pages and clearly identifies archives without a full snapshot', function (): void {
    $unit = RehUnit::factory()->create();
    $oldest = null;
    for ($index = 0; $index < 21; $index++) {
        $log = RehAuditLog::query()->create(['record_kind' => 'unit_deletion', 'record_id' => $unit->id, 'object_id' => $unit->object_id, 'global_id' => $unit->global_id,
            'layer_id' => config('reh.units_layer'), 'field' => 'database', 'status' => 'completed', 'reason' => 'حذف سابق', 'values' => ['before' => $unit->attributesToArray()]]);
        $oldest ??= $log;
    }
    $this->get(route('reh.maintenance.index', ['history_page' => 2]))->assertOk()->assertSee('أرشيف سابق: لا تتوفر نسخة كاملة للاسترجاع الآلي.')
        ->assertViewHas('logs', fn ($logs): bool => $logs->pluck('id')->all() === [$oldest->id]);
    $this->postJson(route('reh.maintenance.restore', $oldest), ['reason' => 'استرجاع النسخة', 'confirm' => true])->assertConflict();
    Queue::assertNothingPushed();
});
