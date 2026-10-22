<?php

use App\Models\User;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehCostEstimate;
use App\Modules\Reh\Models\RehPriceBook;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehSurvey;
use App\Modules\Reh\Services\RehUnitMaintenance;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    Role::findOrCreate('Database Officer', 'web');
    $this->officer = User::factory()->create(['preferred_locale' => 'ar']);
    $this->officer->assignRole('Database Officer');
    $this->actingAs($this->officer);
    Cache::flush();
    Http::preventStrayRequests();
    config(['reh.username' => 'test-user', 'reh.password' => 'test-password']);
});

function rehMaintenanceUnit(?string $reference = '10613'): RehUnit
{
    return RehUnit::factory()->create(['source' => ['OldUnit_objectID' => $reference]]);
}

function rehDeletionPayload(RehUnit $unit, string $destination = 'database'): array
{
    return ['destination' => $destination, 'revision' => app(RehUnitMaintenance::class)->revision($unit->fresh()), 'reason' => 'حذف سجل مكرر بعد مراجعة البيانات', 'confirm' => true];
}

function rehDeletionRemote(RehUnit $unit, string $result = 'success'): object
{
    $state = (object) ['absent' => $result === 'absent'];
    Http::fake(function ($request) use ($unit, $result, $state) {
        if (str_contains($request->url(), 'generateToken')) {
            return Http::response(['token' => 'fake-token']);
        }
        if (str_contains($request->url(), '/query')) {
            return Http::response(['features' => $state->absent ? [] : [['attributes' => ['objectid' => $unit->object_id, 'globalid' => $result === 'mismatch' ? '99999999-9999-4999-8999-999999999999' : '{'.$unit->global_id.'}']]]]);
        }
        if (str_contains($request->url(), '/deleteFeatures')) {
            if ($result === 'unknown') {
                $state->absent = true;

                return Http::response([]);
            }

            return Http::response(['deleteResults' => [['objectId' => $unit->object_id, 'success' => $result !== 'denied']]]);
        }

        return Http::response([], 500);
    });

    return $state;
}

it('limits the page and deletion endpoints to the database officer role', function (): void {
    $unit = rehMaintenanceUnit();
    $payload = rehDeletionPayload($unit);
    $response = $this->get(route('reh.maintenance.index'))->assertOk()->assertSee('مراجعة معرّفات الوحدات');
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $options = (new DOMXPath($document))->query('//form[@data-unit-delete-form]//input[@name="destination"]');
    expect(array_map(fn (DOMElement $option): string => $option->getAttribute('value'), iterator_to_array($options)))->toBe(['database', 'arcgis', 'both']);
    $user = User::factory()->create();
    $user->givePermissionTo(['reh.view', 'reh.review.engineering', 'reh.sync']);
    $this->actingAs($user)->get(route('reh.maintenance.index'))->assertForbidden();
    $this->deleteJson(route('reh.maintenance.destroy', $unit), $payload)->assertForbidden();
    $this->get(route('reh.index'))->assertOk()->assertDontSee(route('reh.maintenance.index'));
    expect($unit->fresh())->not->toBeNull();
    Http::assertNothingSent();
});

it('groups repeated canonical references separately from null and empty values and sorts numerically', function (): void {
    $ten = rehMaintenanceUnit('10');
    $nine = rehMaintenanceUnit('9');
    $tenAgain = rehMaintenanceUnit('10');
    $nineAgain = rehMaintenanceUnit('9');
    $hundred = rehMaintenanceUnit('100');
    $missing = rehMaintenanceUnit(null);
    $empty = rehMaintenanceUnit('');
    $whitespace = rehMaintenanceUnit(null);
    $whitespace->update(['reference_id' => '   ']);
    $this->get(route('reh.maintenance.index'))->assertOk()->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === collect([$nine, $nineAgain])->sortBy('object_id')->pluck('id')->concat(collect([$ten, $tenAgain])->sortBy('object_id')->pluck('id'))->all())
        ->assertViewHas('statistics', fn (array $statistics): bool => $statistics === ['all' => 8, 'duplicate' => 4, 'groups' => 2, 'missing' => 3]);
    $this->get(route('reh.maintenance.index', ['kind' => 'missing']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->count() === 3 && $units->pluck('id')->diff([$missing->id, $empty->id, $whitespace->id])->isEmpty());
    $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->pluck('id')->search($hundred->id) === 4 && $units->total() === 8);
    $this->get(route('reh.maintenance.index', ['q' => '10']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->count() === 2);
    $row = app(RehUnitMaintenance::class)->rows([])->first();
    expect(app(RehUnitMaintenance::class)->revision($row))->toBe(app(RehUnitMaintenance::class)->revision(RehUnit::query()->find($row->id)));
    $this->getJson(route('reh.maintenance.index', ['kind' => 'invalid']))->assertUnprocessable();
});

it('shows the linked building submission date and status from survey columns alongside the unit editor', function (): void {
    $unit = RehUnit::factory()->create(['source' => ['OldUnit_objectID' => '10613', 'Creator' => 'survey.creator', 'Editor' => 'survey.editor']]);
    $unit->building->update(['source' => ['submission_date' => 1792310400000, 'Field_status' => 'Not_Completed']]);
    $unit->building->update(['submission_date' => '2026-10-22 09:35:00', 'field_status' => 'COMPLETED']);

    $response = $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $row = '//tr[@data-maintenance-unit="'.$unit->id.'"]';
    $buildingCell = $xpath->query($row.'/td[3]')->item(0);
    $teamCell = $xpath->query($row.'/td[6]')->item(0);

    expect($buildingCell->textContent)->toContain('تاريخ الرفع:', '2026-10-22 09:35', 'حالة استبيان المبنى:', 'مكتمل')->not->toContain('غير مكتمل')
        ->and($xpath->query($row.'/td[3]//time')->item(0)->getAttribute('datetime'))->toStartWith('2026-10-22T09:35:00')
        ->and($teamCell->textContent)->toContain('منشئ السجل:', 'survey.creator', 'آخر معدّل للسجل:', 'survey.editor');
    Http::assertNothingSent();
});

it('distinguishes incomplete and unavailable building statuses and handles missing dates and editor values', function (?string $status, string $expectedStatus, ?string $editorField, ?string $editor): void {
    $source = ['OldUnit_objectID' => '10613'];
    if ($editorField !== null) {
        $source[$editorField] = $editor;
    }
    $unit = RehUnit::factory()->create(['source' => $source]);
    $unit->building->update(['source' => ['Field_status' => $status]]);

    $response = $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $row = '//tr[@data-maintenance-unit="'.$unit->id.'"]';
    $buildingCell = $xpath->query($row.'/td[3]')->item(0);

    expect($xpath->query($row.'/td[3]//time')->length)->toBe(0)
        ->and($buildingCell->textContent)->toMatch('/تاريخ الرفع:\s*غير متوفر/')
        ->and(trim($xpath->query($row.'/td[3]//span')->item(0)->textContent))->toBe($expectedStatus)
        ->and($xpath->query($row.'/td[6]//small[2]/bdi')->item(0)->textContent)->toBe(trim((string) $editor) ?: 'غير متوفر');
})->with([
    ['Not_Completed', 'غير مكتمل', 'editor', 'lowercase.editor'],
    ['notcomplete', 'غير مكتمل', 'Editor', '   '],
    [null, 'غير متوفر', null, null],
    ['', 'غير متوفر', 'Editor', null],
    ['unknown', 'غير متوفر', 'Editor', 'survey.editor'],
]);

it('counts populated unit survey answers including zero no and photos without counting metadata or building answers', function (): void {
    $source = [
        'OldUnit_objectID' => '10613', 'Creator' => 'creator', 'Editor' => 'editor', 'globalid' => fake()->uuid(),
        'building_name' => 'لا يدخل في عدّ الوحدة', 'unknown_field' => 'غير معروف', 'breadwinner_child_age' => 12,
        'household_size' => 0, 'consent_given' => 'no', 'unit_overview_photo' => 'photo.jpg',
        'owner_full_name' => 'اسم المالك', 'engineer_technical_notes' => '   ', 'owner_identity_number' => '',
        'household_chronic_count' => null, 'boq_1_1_water_tank_repair_qty' => 0,
    ];
    $survey = app(RehSurvey::class);
    expect($survey->unitFieldPopulation($source)['filled'])->toBe(5)
        ->and($survey->unitFieldPopulation([])['filled'])->toBe(0)
        ->and($survey->unitFieldPopulation($source)['total'])->toBeGreaterThan(200)
        ->and($survey->unitFieldPopulation($source)['total'])->toBe($survey->unitFieldPopulation([])['total']);

    $unit = RehUnit::factory()->create(['source' => $source]);
    $unit->update(['household_chronic_count' => 2]);
    $population = $survey->unitFieldPopulation($unit->fresh()->surveyData());
    expect($population['filled'])->toBe(8);
    $response = $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $counter = $xpath->query('//tr[@data-maintenance-unit="'.$unit->id.'"]//small[contains(@class,"reh-field-population")]')->item(0);
    expect($counter->textContent)->toBe('الحقول المعبّأة: 8 من '.$population['total'])
        ->and($counter->getAttribute('title'))->toContain('العدد لا يحدد حالة اكتمال الاستبيان');
    Http::assertNothingSent();
});

it('shows each unit code and marks null empty or whitespace codes as unavailable', function (): void {
    $units = collect(['PE2 - PR2--F0-1', null, '', '   '])->map(fn (?string $code): RehUnit => RehUnit::factory()->create(['code' => $code]));
    $response = $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk()->assertSee('كود الوحدة:');
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    foreach ($units as $unit) {
        $code = $xpath->query('//tr[@data-maintenance-unit="'.$unit->id.'"]//bdi[@data-unit-code]')->item(0);
        expect($code->textContent)->toBe(trim((string) $unit->code) !== '' ? $unit->code : 'غير متوفر');
    }
    Http::assertNothingSent();
});

it('archives local reviews and approved cost snapshots before deleting only from the database', function (): void {
    $unit = rehMaintenanceUnit();
    $unit->reviews()->create(['user_id' => $this->officer->id, 'action' => 'engineering_approve', 'note' => 'تدقيق سابق']);
    $book = RehPriceBook::factory()->create();
    $estimate = RehCostEstimate::query()->create(['unit_id' => $unit->id, 'book_id' => $book->id, 'fingerprint' => str_repeat('a', 64), 'total' => 60, 'exchange_rate' => 3, 'total_usd' => 20, 'approved_by' => $this->officer->id, 'reason' => 'اعتماد سابق']);
    $estimate->lines()->create(['code' => '1.1', 'field' => 'boq_1_1_water_tank_repair_qty', 'label' => 'صيانة خزان', 'unit' => 'عدد', 'quantity' => 2, 'rate' => 30, 'amount' => 60]);
    $this->deleteJson(route('reh.maintenance.destroy', $unit), rehDeletionPayload($unit))->assertOk();
    $log = RehAuditLog::query()->where('record_kind', 'unit_deletion')->sole();
    expect(RehUnit::query()->find($unit->id))->toBeNull()
        ->and($log->status)->toBe('completed')->and($log->values['reference'])->toBe('10613')
        ->and($log->values['before']['object_id'])->toBe($unit->object_id)
        ->and($log->values['reviews'][0]['note'])->toBe('تدقيق سابق')
        ->and((float) $log->values['cost_estimates'][0]['total_usd'])->toBe(20.0)
        ->and($log->values['cost_estimates'][0]['lines'][0]['code'])->toBe('1.1')
        ->and(RehCostEstimate::query()->whereKey($estimate->id)->exists())->toBeFalse()
        ->and($log->values['local_deleted'])->toBeTrue()->and($log->values['remote_deleted'])->toBeFalse();
    $this->get(route('reh.maintenance.index'))->assertOk()->assertSee('10613')->assertSee('حذف سجل مكرر');
    Http::assertNothingSent();
});

it('requires an explicit destination reason confirmation and a current revision before deleting', function (): void {
    $unit = rehMaintenanceUnit();
    $payload = rehDeletionPayload($unit);
    $this->deleteJson(route('reh.maintenance.destroy', $unit), [...$payload, 'confirm' => false, 'reason' => '', 'destination' => 'all-layers'])->assertUnprocessable()->assertJsonValidationErrors(['confirm', 'reason', 'destination']);
    $unit->update(['owner_name' => 'تغيّر الملف']);
    $this->deleteJson(route('reh.maintenance.destroy', $unit), $payload)->assertConflict();
    $lock = Cache::lock('reh:sync:execution', 600);
    $lock->get();
    $this->deleteJson(route('reh.maintenance.destroy', $unit), rehDeletionPayload($unit))->assertConflict();
    $lock->release();
    expect($unit->fresh())->not->toBeNull();
    expect(RehAuditLog::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('deletes the exact rehabilitation object id while retaining the local record for ArcGIS only', function (): void {
    $unit = rehMaintenanceUnit('10613');
    $unit->update(['engineering_status' => 'approved', 'social_status' => 'approved', 'decision' => 'selected']);
    rehDeletionRemote($unit);
    $this->deleteJson(route('reh.maintenance.destroy', $unit), rehDeletionPayload($unit, 'arcgis'))->assertOk();
    expect($unit->fresh()->source_missing)->toBeTrue()->and($unit->fresh()->decision)->toBe('pending')
        ->and($unit->fresh()->engineering_status)->toBe('pending')->and($unit->fresh()->version)->toBe(2);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/'.config('reh.units_layer').'/deleteFeatures') && $request['objectIds'] === (string) $unit->object_id && $request['rollbackOnFailure'] === 'true');
    expect(RehAuditLog::query()->sole()->values['remote_deleted'])->toBeTrue();
});

it('deletes locally only after confirmed remote deletion and accepts an already absent source', function (string $result): void {
    $unit = rehMaintenanceUnit();
    rehDeletionRemote($unit, $result);
    $this->deleteJson(route('reh.maintenance.destroy', $unit), rehDeletionPayload($unit, 'both'))->assertOk();
    expect($unit->fresh())->toBeNull()->and(RehAuditLog::query()->sole()->status)->toBe('completed');
    if ($result === 'absent') {
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/deleteFeatures'));
    }
})->with(['success', 'absent']);

it('keeps the database unit when ArcGIS refuses deletion or the current object id belongs to another global id', function (string $result): void {
    $unit = rehMaintenanceUnit();
    rehDeletionRemote($unit, $result);
    $this->deleteJson(route('reh.maintenance.destroy', $unit), rehDeletionPayload($unit, 'both'))->assertUnprocessable();
    expect($unit->fresh())->not->toBeNull()->and(RehAuditLog::query()->sole()->status)->toBe('failed');
    if ($result === 'mismatch') {
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/deleteFeatures'));
    }
})->with(['denied', 'mismatch']);

it('keeps an uncertain deletion visible and reconciles it without sending a second delete', function (): void {
    $unit = rehMaintenanceUnit();
    rehDeletionRemote($unit, 'unknown');
    $payload = rehDeletionPayload($unit, 'both');
    $this->deleteJson(route('reh.maintenance.destroy', $unit), $payload)->assertStatus(502);
    $log = RehAuditLog::query()->sole();
    expect($unit->fresh())->not->toBeNull()->and($log->status)->toBe('needs_review');
    $this->get(route('reh.maintenance.index'))->assertOk()->assertSee('التحقق من نتيجة الحذف');
    $this->deleteJson(route('reh.maintenance.destroy', $unit), $payload)->assertConflict();
    $this->postJson(route('reh.maintenance.verify', $log))->assertOk();
    expect($unit->fresh())->toBeNull()->and($log->fresh()->status)->toBe('completed');
    $deletions = Http::recorded(fn ($request): bool => str_contains($request->url(), '/deleteFeatures'));
    expect($deletions)->toHaveCount(1);
});

it('preserves changed local data after remote deletion and allows a newly reviewed request', function (): void {
    $unit = rehMaintenanceUnit();
    rehDeletionRemote($unit, 'unknown');
    $this->deleteJson(route('reh.maintenance.destroy', $unit), rehDeletionPayload($unit, 'both'))->assertStatus(502);
    $unit->update(['owner_name' => 'اسم راجعه المدقق أثناء التحقق']);
    $log = RehAuditLog::query()->sole();
    $this->postJson(route('reh.maintenance.verify', $log))->assertOk();
    expect($unit->fresh()->owner_name)->toBe('اسم راجعه المدقق أثناء التحقق')->and($log->fresh()->status)->toBe('partial')
        ->and($log->fresh()->values['remote_deleted'])->toBeTrue();
    $this->deleteJson(route('reh.maintenance.destroy', $unit), rehDeletionPayload($unit))->assertOk();
    expect($unit->fresh())->toBeNull();
});

it('resolves an uncertain unsuccessful deletion without dropping the local record', function (): void {
    $unit = rehMaintenanceUnit();
    $state = rehDeletionRemote($unit, 'unknown');
    $this->deleteJson(route('reh.maintenance.destroy', $unit), rehDeletionPayload($unit, 'both'))->assertStatus(502);
    $state->absent = false;
    $log = RehAuditLog::query()->sole();
    $this->postJson(route('reh.maintenance.verify', $log))->assertOk();
    expect($unit->fresh())->not->toBeNull()->and($log->fresh()->status)->toBe('failed');
});
