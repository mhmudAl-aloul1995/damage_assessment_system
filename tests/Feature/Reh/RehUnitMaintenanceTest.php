<?php

use App\Models\User;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehBuilding;
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

it('filters each building and unit identifier independently using exact canonical identifiers', function (string $key, string $value, array $expected): void {
    $building = RehBuilding::factory()->create(['object_id' => 80001, 'source' => ['Old_objectID' => 999]]);
    $building->update(['reference_id' => '1337']);
    $otherBuilding = RehBuilding::factory()->create(['object_id' => 1337, 'source' => ['Old_objectID' => 9000]]);
    $nearBuilding = RehBuilding::factory()->create(['object_id' => 800010, 'source' => ['Old_objectID' => 13370]]);
    $first = RehUnit::factory()->create(['building_id' => $building->id, 'object_id' => 48400, 'source' => ['OldUnit_objectID' => 999]]);
    $first->update(['reference_id' => '10613']);
    $second = RehUnit::factory()->create(['building_id' => $building->id, 'object_id' => 48402, 'source' => ['OldUnit_objectID' => 10614]]);
    $other = RehUnit::factory()->create(['building_id' => $otherBuilding->id, 'object_id' => 48401, 'source' => ['OldUnit_objectID' => 10613]]);
    $near = RehUnit::factory()->create(['building_id' => $nearBuilding->id, 'object_id' => 484003, 'source' => ['OldUnit_objectID' => 106130]]);
    $records = compact('first', 'second', 'other', 'near');
    $ids = collect($expected)->map(fn (string $name): int => $records[$name]->id)->sort()->values()->all();
    $this->get(route('reh.maintenance.index', ['kind' => 'all', $key => $value]))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->pluck('id')->sort()->values()->all() === $ids);
    Http::assertNothingSent();
})->with([
    ['building_reference', '1337', ['first', 'second']],
    ['building_object_id', '80001', ['first', 'second']],
    ['unit_reference', '10613', ['first', 'other']],
    ['unit_object_id', '48400', ['first']],
]);

it('combines all identifier filters with existing filters and keeps their values in navigation and bulk selection', function (): void {
    $building = RehBuilding::factory()->create(['object_id' => 80001, 'source' => ['Old_objectID' => 1337]]);
    $unit = RehUnit::factory()->create(['building_id' => $building->id, 'object_id' => 48400, 'creator' => 'alice', 'code' => 'CODE-A', 'source' => ['OldUnit_objectID' => 10613]]);
    $otherBuilding = RehBuilding::factory()->create(['object_id' => 80002, 'source' => ['Old_objectID' => 1338]]);
    $outside = RehUnit::factory()->create(['building_id' => $otherBuilding->id, 'source' => ['OldUnit_objectID' => 10613]]);
    $identifiers = ['building_reference' => '1337', 'building_object_id' => '80001', 'unit_reference' => '10613', 'unit_object_id' => '48400'];
    $filters = ['kind' => 'duplicate', 'creator' => 'alice', 'code_status' => 'available', 'pricing_status' => 'incomplete', ...$identifiers];
    $response = $this->get(route('reh.maintenance.index', $filters))->assertOk()->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$unit->id]);
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $bulkFilters = json_decode($xpath->query('//*[@id="reh-workspace"]')->item(0)->getAttribute('data-bulk-filters'), true);
    foreach ($identifiers as $key => $value) {
        expect($xpath->query('//fieldset[@class="reh-maintenance-identifiers"]//input[@name="'.$key.'"]')->item(0)->getAttribute('value'))->toBe($value)
            ->and($bulkFilters[$key])->toBe($value);
        foreach ($xpath->query('//nav[@class="reh-maintenance-stats"]/a') as $link) {
            expect($link->getAttribute('href'))->toContain($key.'='.$value);
        }
        expect($xpath->query('//a[text()="مسح الفلاتر"]')->item(0)->getAttribute('href'))->not->toContain($key.'=');
    }
    foreach (['building_object_id' => 80002, 'unit_reference' => 106130, 'creator' => 'bob', 'code_status' => 'missing', 'pricing_status' => 'complete'] as $key => $value) {
        $this->get(route('reh.maintenance.index', [...$filters, $key => $value]))->assertOk()->assertViewHas('units', fn ($units): bool => $units->total() === 0);
    }
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), ['scope' => 'filtered', 'filters' => $filters])->assertOk()->assertJsonPath('total', 1)->json();
    $snapshot = Cache::get('reh:bulk-preview:'.$preview['selection_token']);
    expect(array_column($snapshot['units'], 'id'))->toBe([$unit->id])
        ->and(array_intersect_key($snapshot['filters'], $identifiers))->toBe($identifiers);
    $this->postJson(route('reh.maintenance.bulk.preview'), ['scope' => 'selected', 'filters' => $filters, 'units' => [['id' => $outside->id, 'revision' => app(RehUnitMaintenance::class)->revision($outside)]]])->assertUnprocessable();
    Http::assertNothingSent();
});

it('preserves identifier filters across pages and accepts zero identifiers without matching missing values', function (): void {
    $building = RehBuilding::factory()->create(['object_id' => 0, 'source' => ['Old_objectID' => 0]]);
    $units = RehUnit::factory()->count(26)->sequence(fn (\Illuminate\Database\Eloquent\Factories\Sequence $sequence): array => ['object_id' => $sequence->index])->create(['building_id' => $building->id, 'source' => ['OldUnit_objectID' => 0]]);
    RehUnit::factory()->create(['source' => ['OldUnit_objectID' => null]]);
    $filters = ['kind' => 'all', 'building_reference' => '0', 'building_object_id' => '0', 'unit_reference' => '0', 'per_page' => 25];
    $this->get(route('reh.maintenance.index', $filters))->assertOk()->assertViewHas('units', fn ($rows): bool => $rows->total() === 26 && $rows->count() === 25
        && str_contains($rows->nextPageUrl(), 'building_reference=0') && str_contains($rows->nextPageUrl(), 'building_object_id=0') && str_contains($rows->nextPageUrl(), 'unit_reference=0'));
    $this->get(route('reh.maintenance.index', [...$filters, 'page' => 2]))->assertOk()->assertViewHas('units', fn ($rows): bool => $rows->pluck('id')->all() === [$units->last()->id]);
    $this->get(route('reh.maintenance.index', [...$filters, 'unit_object_id' => '0']))->assertOk()->assertViewHas('units', fn ($rows): bool => $rows->pluck('id')->all() === [$units->first()->id]);
    Http::assertNothingSent();
});

it('validates identifier filters on the maintenance page and bulk preview', function (): void {
    $invalid = ['building_reference' => str_repeat('a', 101), 'unit_reference' => ['10613'], 'building_object_id' => '1337 OR 1=1', 'unit_object_id' => -1];
    $this->getJson(route('reh.maintenance.index', $invalid))->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
    $this->postJson(route('reh.maintenance.bulk.preview'), ['scope' => 'filtered', 'filters' => ['kind' => 'all', ...$invalid]])->assertUnprocessable()->assertJsonValidationErrors(array_map(fn (string $key): string => 'filters.'.$key, array_keys($invalid)));
    Http::assertNothingSent();
});

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
        if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/'.config('reh.units_layer'))) {
            return Http::response(['fields' => array_map(fn (string $field): array => ['name' => $field, 'editable' => true], array_unique(['globalid', 'objectid', 'parentglobalid', ...array_keys($unit->source)])), 'hasAttachments' => false, 'supportsApplyEditsWithGlobalIds' => true]);
        }
        if (str_contains($request->url(), '/query')) {
            return Http::response(['features' => $state->absent ? [] : [['attributes' => [...$unit->source, 'objectid' => $unit->object_id, 'globalid' => $result === 'mismatch' ? '99999999-9999-4999-8999-999999999999' : '{'.$unit->global_id.'}', 'parentglobalid' => $unit->building->global_id]]]]);
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
    $counter = $xpath->query('//tr[@data-maintenance-unit="'.$unit->id.'"]//button[contains(@class,"reh-field-population")]')->item(0);
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

it('filters unit code availability together with reference filters and search and preserves it in navigation and bulk selection', function (): void {
    $available = RehUnit::factory()->create(['code' => 'PE2-PR2-F0-1', 'source' => ['OldUnit_objectID' => 10613]]);
    $missing = collect([null, '', '   '])->map(fn (?string $code): RehUnit => RehUnit::factory()->create(['code' => $code, 'source' => ['OldUnit_objectID' => 10613]]));
    $zero = RehUnit::factory()->create(['code' => '0', 'source' => ['OldUnit_objectID' => null]]);

    $response = $this->get(route('reh.maintenance.index', ['code_status' => 'available']))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$available->id]);
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//select[@name="code_status"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('available')
        ->and(json_decode($xpath->query('//*[@id="reh-workspace"]')->item(0)->getAttribute('data-bulk-filters'), true)['code_status'])->toBe('available');
    foreach ($xpath->query('//nav[@class="reh-maintenance-stats"]/a') as $link) {
        expect($link->getAttribute('href'))->toContain('code_status=available');
    }

    $this->get(route('reh.maintenance.index', ['code_status' => 'missing']))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->pluck('id')->sort()->values()->all() === $missing->pluck('id')->sort()->values()->all());
    $this->get(route('reh.maintenance.index', ['kind' => 'missing', 'code_status' => 'available']))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$zero->id]);
    $this->get(route('reh.maintenance.index', ['kind' => 'all', 'code_status' => 'available', 'q' => $available->object_id]))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$available->id]);
    $this->get(route('reh.maintenance.index', ['kind' => 'all', 'code_status' => 'all']))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->total() === 5);
    $this->getJson(route('reh.maintenance.index', ['code_status' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('code_status');
    Http::assertNothingSent();
});

it('lists and filters effective unit creators including archived spellings and canonical values', function (): void {
    $upper = RehUnit::factory()->create(['creator' => null, 'code' => 'CODE-A', 'source' => ['Creator' => 'alice', 'OldUnit_objectID' => 10613]]);
    $lower = RehUnit::factory()->create(['creator' => null, 'source' => ['creator' => 'bob', 'OldUnit_objectID' => 10613]]);
    $canonical = RehUnit::factory()->create(['creator' => 'carol', 'source' => ['Creator' => 'obsolete']]);
    $trimmed = RehUnit::factory()->create(['creator' => '   ', 'source' => ['Creator' => '  alice  ']]);
    RehUnit::factory()->create(['creator' => null, 'source' => ['Creator' => null, 'creator' => '']]);
    $response = $this->get(route('reh.maintenance.index', ['kind' => 'all', 'creator' => 'alice']))->assertOk()
        ->assertViewHas('creators', ['alice', 'bob', 'carol'])
        ->assertViewHas('units', fn ($units): bool => $units->pluck('id')->sort()->values()->all() === [$upper->id, $trimmed->id]);
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//select[@name="creator"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('alice')
        ->and(json_decode($xpath->query('//*[@id="reh-workspace"]')->item(0)->getAttribute('data-bulk-filters'), true)['creator'])->toBe('alice');
    foreach ($xpath->query('//nav[@class="reh-maintenance-stats"]/a') as $link) {
        expect($link->getAttribute('href'))->toContain('creator=alice');
    }
    $this->get(route('reh.maintenance.index', ['creator' => 'bob']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$lower->id]);
    $this->get(route('reh.maintenance.index', ['kind' => 'all', 'creator' => 'carol']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$canonical->id]);
    $this->get(route('reh.maintenance.index', ['kind' => 'all', 'creator' => 'alice', 'code_status' => 'available', 'q' => $upper->object_id]))->assertOk()->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$upper->id]);
    $this->getJson(route('reh.maintenance.index', ['creator' => str_repeat('a', 256)]))->assertUnprocessable()->assertJsonValidationErrors('creator');
    Http::assertNothingSent();
});

it('paginates matching creators at the requested row count while preserving all filters', function (): void {
    $building = \App\Modules\Reh\Models\RehBuilding::factory()->create();
    RehUnit::factory()->count(101)->create(['building_id' => $building->id, 'creator' => 'alice', 'code' => 'CODE-A']);
    RehUnit::factory()->create(['building_id' => $building->id, 'creator' => 'bob', 'code' => 'CODE-B']);
    foreach ([25, 50, 100] as $size) {
        $this->get(route('reh.maintenance.index', ['kind' => 'all', 'creator' => 'alice', 'code_status' => 'available', 'per_page' => $size]))->assertOk()
            ->assertViewHas('units', fn ($units): bool => $units->count() === $size && $units->total() === 101 && $units->perPage() === $size
                && str_contains($units->nextPageUrl(), 'creator=alice') && str_contains($units->nextPageUrl(), 'code_status=available') && str_contains($units->nextPageUrl(), 'per_page='.$size));
    }
    $this->get(route('reh.maintenance.index', ['kind' => 'all', 'creator' => 'alice', 'per_page' => 100, 'page' => 2]))->assertOk()->assertViewHas('units', fn ($units): bool => $units->count() === 1);
    $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->perPage() === 50);
    $this->getJson(route('reh.maintenance.index', ['per_page' => 1000]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('filters available survey unit references independently of duplicate membership and combines existing filters', function (): void {
    $available = RehUnit::factory()->create(['creator' => 'alice', 'code' => 'CODE-A', 'source' => ['OldUnit_objectID' => 10613]]);
    $zero = RehUnit::factory()->create(['source' => ['OldUnit_objectID' => 0]]);
    RehUnit::factory()->create(['source' => ['OldUnit_objectID' => null]]);
    RehUnit::factory()->create(['source' => ['OldUnit_objectID' => '']]);
    $blank = RehUnit::factory()->create(['source' => []]);
    $blank->update(['reference_id' => '   ']);
    $response = $this->get(route('reh.maintenance.index', ['kind' => 'available', 'creator' => 'alice', 'code_status' => 'available']))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$available->id]);
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    expect((new DOMXPath($document))->query('//select[@name="kind"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('available');
    $this->get(route('reh.maintenance.index', ['kind' => 'available']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$zero->id, $available->id]);
    $this->get(route('reh.maintenance.index', ['kind' => 'missing']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->total() === 3);
});

it('shows only populated canonical survey fields with translated values and escaped content in the small field viewer', function (): void {
    $unit = RehUnit::factory()->create(['source' => ['OldUnit_objectID' => 10613, 'household_size' => 5, 'consent_given' => 'no', 'owner_full_name' => '<script>alert(1)</script>',
        'boq_1_1_water_tank_repair_qty' => 0, 'unit_overview_photo' => 'photo.jpg', 'engineer_technical_notes' => '   ', 'Creator' => 'metadata only', 'unknown_field' => 'not a survey field']]);
    $unit->update(['household_size' => 9]);
    $response = $this->get(route('reh.maintenance.filled-fields', $unit))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('metadata only')->assertDontSee('not a survey field');
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-populated-field]')->length)->toBe(7)
        ->and($xpath->query('//*[@data-field="household_size"]/dd')->item(0)->textContent)->toBe('9')
        ->and($xpath->query('//*[@data-field="consent_given"]/dd')->item(0)->textContent)->toBe('لا')
        ->and($xpath->query('//*[@data-field="boq_1_1_water_tank_repair_qty"]/dd')->item(0)->textContent)->toBe('0')
        ->and($xpath->query('//*[@data-field="unit_overview_photo"]/dd')->item(0)->textContent)->toBe('photo.jpg')
        ->and($xpath->query('//*[@data-field="engineer_technical_notes"]')->length)->toBe(0);
    $user = User::factory()->create();
    $user->givePermissionTo('reh.view');
    $this->actingAs($user)->get(route('reh.maintenance.filled-fields', $unit))->assertForbidden();
    Http::assertNothingSent();
});

it('shows the source edit timestamp without substituting database modification dates', function (?string $field, mixed $value, ?string $expected): void {
    config(['app.timezone' => 'UTC']);
    $source = ['OldUnit_objectID' => 10613];
    if ($field !== null) {
        $source[$field] = $value;
    }
    $unit = RehUnit::factory()->create(['source' => $source, 'updated_at' => '2020-01-01 01:00:00']);
    $response = $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $cell = '//tr[@data-maintenance-unit="'.$unit->id.'"]/td[6]';
    $time = $xpath->query($cell.'//time')->item(0);
    expect($time?->textContent)->toBe($expected);
    if ($expected === null) {
        expect($xpath->query($cell)->item(0)->textContent)->toMatch('/آخر تحديث في الطبقة:\s*غير متوفر/');
    }
})->with([
    ['EditDate', 1792660200000, '2026-10-22 09:10'],
    ['editdate', '2026-10-22T09:10:00+00:00', '2026-10-22 09:10'],
    ['EditDate', null, null], ['EditDate', '', null], ['EditDate', 'not-a-date', null], [null, null, null],
]);

it('shows current dollar pricing with distinct complete partial zero and unpriced amounts in the maintenance table', function (string $scenario, string $amount, string $status): void {
    $book = RehPriceBook::factory()->create(['status' => 'approved', 'effective_date' => now()->subDay(), 'approved_at' => now(), 'exchange_rate' => 3]);
    $book->items()->create(['code' => '1.1', 'field' => 'boq_1_1_water_tank_repair_qty', 'category' => 'مياه', 'label' => 'صيانة خزان', 'description' => 'صيانة خزان المياه', 'unit' => 'عدد', 'rate' => 90]);
    $quantities = collect(app(RehSurvey::class)->dictionary()['fields'])->keys()->filter(fn (string $field): bool => str_starts_with($field, 'boq_'))->mapWithKeys(fn (string $field): array => [$field => $scenario === 'zero' ? 0 : null])->all();
    $source = $scenario === 'missing' ? [] : [...$quantities, 'OldUnit_objectID' => 10613, 'unit_needs_intervention' => $scenario === 'zero' ? 'no' : 'yes'];
    if (in_array($scenario, ['complete', 'partial', 'overrides'], true)) {
        $source['boq_1_1_water_tank_repair_qty'] = 2;
    }
    if ($scenario === 'partial') {
        $source['boq_2_1_pallet_wall_qty'] = 1;
    }
    $unit = RehUnit::factory()->create(['source' => $source, 'quantity_overrides' => $scenario === 'overrides' ? ['boq_1_1_water_tank_repair_qty' => 3] : null]);
    RehPriceBook::factory()->create(['status' => 'approved', 'effective_date' => now()->addMonth(), 'approved_at' => now()->addMinute(), 'exchange_rate' => 100]);
    $response = $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk()->assertSee('التسعير');
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $cell = $xpath->query('//*[@data-unit-pricing="'.$unit->id.'"]')->item(0);
    expect(trim($xpath->query('//*[@data-unit-pricing="'.$unit->id.'"]/strong')->item(0)->textContent))->toBe($amount)
        ->and($cell->textContent)->toContain($status)
        ->and($xpath->query('//*[@data-unit-pricing="'.$unit->id.'"]//button')->item(0)->getAttribute('data-price-unit'))->toBe(route('reh.pricing.unit', $unit));
    if ($scenario === 'partial') {
        expect($cell->textContent)->toContain('مبلغ جزئي', 'بانتظار السعر');
    }
    $this->getJson(route('reh.pricing.unit', $unit))->assertOk()->assertJsonStructure(['html']);
    Http::assertNothingSent();
})->with([
    ['complete', '60.00 USD', 'مكتمل التسعير'],
    ['overrides', '90.00 USD', 'مكتمل التسعير'],
    ['partial', '60.00 USD', 'تسعير غير مكتمل'],
    ['zero', '0.00 USD', 'مكتمل التسعير'],
    ['missing', '—', 'تسعير غير مكتمل'],
]);

it('shows awaiting prices rather than a false zero when only draft prices exist', function (): void {
    RehPriceBook::factory()->create(['status' => 'draft']);
    $unit = rehMaintenanceUnit();
    $response = $this->get(route('reh.maintenance.index', ['kind' => 'all']))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-unit-pricing="'.$unit->id.'"]')->item(0)->textContent)->toContain('بانتظار الأسعار')->not->toContain('0.00');
    expect($xpath->query('//*[@id="reh-price-dialog"]')->length)->toBe(1);
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

it('filters pricing across the full result set and preserves it in pagination navigation and bulk previews', function (): void {
    $book = RehPriceBook::factory()->create(['status' => 'approved', 'effective_date' => now()->subDay(), 'approved_at' => now(), 'exchange_rate' => 3]);
    $book->items()->create(['code' => '1.1', 'field' => 'boq_1_1_water_tank_repair_qty', 'category' => 'مياه', 'label' => 'صيانة خزان', 'description' => 'صيانة خزان المياه', 'unit' => 'عدد', 'rate' => 90]);
    $quantities = collect(app(RehSurvey::class)->dictionary()['fields'])->keys()->filter(fn (string $field): bool => str_starts_with($field, 'boq_'))->mapWithKeys(fn (string $field): array => [$field => 0])->all();
    $building = \App\Modules\Reh\Models\RehBuilding::factory()->create();
    $attributes = ['building_id' => $building->id, 'creator' => 'alice', 'code' => 'CODE-A'];
    $missing = RehUnit::factory()->count(26)->sequence(fn ($sequence): array => ['object_id' => 10000 + $sequence->index])->create([...$attributes, 'source' => ['OldUnit_objectID' => 10613]]);
    $source = [...$quantities, 'OldUnit_objectID' => 10613, 'unit_needs_intervention' => 'no'];
    $zero = RehUnit::factory()->create([...$attributes, 'object_id' => 20000, 'source' => $source]);
    $overridden = RehUnit::factory()->create([...$attributes, 'object_id' => 20001, 'source' => $source, 'quantity_overrides' => ['boq_1_1_water_tank_repair_qty' => 2]]);
    $partial = RehUnit::factory()->create([...$attributes, 'object_id' => 20002, 'source' => [...$source, 'boq_1_1_water_tank_repair_qty' => 1, 'boq_2_1_pallet_wall_qty' => 1]]);
    $absent = RehUnit::factory()->create([...$attributes, 'object_id' => 20003, 'source' => $source, 'source_missing' => true]);
    RehUnit::factory()->create([...$attributes, 'object_id' => 20004, 'creator' => 'bob', 'source' => $source]);
    RehPriceBook::factory()->create(['status' => 'approved', 'effective_date' => now()->addMonth(), 'approved_at' => now()->addMinute()]);
    $filters = ['kind' => 'duplicate', 'code_status' => 'available', 'creator' => 'alice', 'pricing_status' => 'complete'];
    $response = $this->get(route('reh.maintenance.index', [...$filters, 'per_page' => 25]))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->total() === 2 && $units->pluck('id')->all() === [$zero->id, $overridden->id]);
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//select[@name="pricing_status"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('complete')
        ->and(json_decode($xpath->query('//*[@id="reh-workspace"]')->item(0)->getAttribute('data-bulk-filters'), true)['pricing_status'])->toBe('complete');
    foreach ($xpath->query('//nav[@class="reh-maintenance-stats"]/a') as $link) {
        expect($link->getAttribute('href'))->toContain('pricing_status=complete');
    }
    $this->get(route('reh.maintenance.index', [...$filters, 'pricing_status' => 'incomplete', 'per_page' => 25]))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->total() === 28 && $units->count() === 25 && str_contains($units->nextPageUrl(), 'pricing_status=incomplete'));
    $this->get(route('reh.maintenance.index', [...$filters, 'pricing_status' => 'incomplete', 'per_page' => 25, 'page' => 2]))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$missing->last()->id, $partial->id, $absent->id]);
    $this->get(route('reh.maintenance.index', [...$filters, 'q' => $partial->object_id]))->assertOk()
        ->assertViewHas('units', fn ($units): bool => $units->total() === 0);
    $preview = $this->postJson(route('reh.maintenance.bulk.preview'), ['scope' => 'filtered', 'filters' => $filters])->assertOk()->assertJsonPath('total', 2)->json();
    expect(array_column(Cache::get('reh:bulk-preview:'.$preview['selection_token'])['units'], 'id'))->toBe([$zero->id, $overridden->id]);
    $this->postJson(route('reh.maintenance.bulk.preview'), ['scope' => 'selected', 'filters' => $filters, 'units' => [['id' => $partial->id, 'revision' => app(RehUnitMaintenance::class)->revision($partial)]]])->assertUnprocessable();
    $this->getJson(route('reh.maintenance.index', ['pricing_status' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('pricing_status');
    $this->postJson(route('reh.maintenance.bulk.preview'), ['scope' => 'filtered', 'filters' => [...$filters, 'pricing_status' => 'invalid']])->assertUnprocessable()->assertJsonValidationErrors('filters.pricing_status');
    Http::assertNothingSent();
});

it('treats awaiting approved prices as incomplete pricing and allows resetting the pricing filter', function (): void {
    RehPriceBook::factory()->create(['status' => 'draft']);
    $unit = rehMaintenanceUnit();
    $this->get(route('reh.maintenance.index', ['kind' => 'all', 'pricing_status' => 'complete']))->assertOk()->assertViewHas('units', fn ($units): bool => $units->total() === 0);
    foreach (['incomplete', 'all'] as $status) {
        $this->get(route('reh.maintenance.index', ['kind' => 'all', 'pricing_status' => $status]))->assertOk()->assertViewHas('units', fn ($units): bool => $units->pluck('id')->all() === [$unit->id]);
    }
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
