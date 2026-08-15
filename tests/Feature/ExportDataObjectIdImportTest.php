<?php

use App\Jobs\ExportDataJob;
use App\Models\Building;
use App\Models\Export;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use OpenSpout\Reader\XLSX\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('imports objectids from uploaded file and stores unique cleaned values in session', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent(
        'objectids.csv',
        "objectid\n 1001 \n\n1002\n1001\n 1003 \n",
    );

    $response = $this
        ->actingAs($user)
        ->post(route('export.data.objectids.import'), [
            'objectids_file' => $file,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('count', 3)
        ->assertJsonPath('object_ids', ['1001', '1002', '1003']);

    expect(session('exports.imported_object_ids'))->toBe(['1001', '1002', '1003']);
    expect(session('exports.imported_object_id_target'))->toBe('building');
});

it('imports housing unit objectids from the matching uploaded file column', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent(
        'objectids.csv',
        "objectidللمبنى,objectid للوحدة\n1105,1\n1261,7\n",
    );

    $response = $this
        ->actingAs($user)
        ->post(route('export.data.objectids.import'), [
            'objectid_filter_target' => 'housing_unit',
            'objectids_file' => $file,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('count', 2)
        ->assertJsonPath('target', 'housing_unit');

    expect(session('exports.imported_object_ids'))->toBe(['1', '7']);
    expect(session('exports.imported_object_id_target'))->toBe('housing_unit');
});

it('imports pasted housing unit objectids from textarea input', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('export.data.objectids.import'), [
            'objectid_filter_target' => 'housing_unit',
            'objectids_text' => "objectid للوحدة\n1, 2\n2\t3",
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('count', 3);

    expect(session('exports.imported_object_ids'))->toBe(['1', '2', '3']);
    expect(session('exports.imported_object_id_target'))->toBe('housing_unit');
});

it('does not reload the export page after importing objectids', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response
        ->assertOk()
        ->assertDontSee('window.location.reload();', false);
});

it('reads checked export columns directly before showing the no columns warning', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response
        ->assertOk()
        ->assertSee('function checkedColumnValues(inputName)', false)
        ->assertSee("appendMissingFormFields(formData, 'building_columns[]', checkedColumnValues('building_columns[]'));", false)
        ->assertSee('const formData = exportFormData();', false);
});

it('can render imported objectids on the export page without reloading', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response
        ->assertOk()
        ->assertSee('function objectIdsFromImportResponse(response, formData)', false)
        ->assertSee('function renderImportedObjectIds(objectIds, target)', false)
        ->assertSee('renderImportedObjectIds(objectIdsFromImportResponse(response, formData), response.target ||', false)
        ->assertSee('id="importedObjectIdsInputs"', false)
        ->assertDontSee('window.location.reload();', false);
});

it('passes imported objectids into the export payload', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->withSession([
        'exports.imported_object_ids' => ['1001', '1002', '1002', '1003'],
        'exports.imported_object_id_target' => 'housing_unit',
    ]);

    $response = $this->post(route('export.start'), [
        'export_type' => 'excel',
        'building_columns' => ['objectid'],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true);

    $export = Export::query()->latest('id')->first();

    expect($export)->not->toBeNull();

    $payload = json_decode($export->filters, true);

    expect($payload['imported_object_ids'])->toBe(['1001', '1002', '1003']);
    expect($payload['imported_object_id_target'])->toBe('housing_unit');
    expect(session()->has('exports.imported_object_ids'))->toBeFalse();
    expect(session()->has('exports.imported_object_id_target'))->toBeFalse();

    Queue::assertPushed(ExportDataJob::class);
});

it('shows imported objectids once on the export page and then clears the session filter', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withSession([
            'exports.imported_object_ids' => ['273', '360'],
            'exports.imported_object_id_target' => 'housing_unit',
        ])
        ->get(route('export.data.index'));

    $response
        ->assertOk()
        ->assertSee('name="imported_object_ids[]" value="273"', false)
        ->assertSee('name="imported_object_ids[]" value="360"', false)
        ->assertSee('name="imported_object_id_target" value="housing_unit"', false);

    expect(session()->has('exports.imported_object_ids'))->toBeFalse();
    expect(session()->has('exports.imported_object_id_target'))->toBeFalse();
});

it('passes page embedded imported objectids into the export payload after the session is cleared', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('export.start'), [
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
            'imported_object_ids' => ['273', '360', '273'],
            'imported_object_id_target' => 'housing_unit',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true);

    $export = Export::query()->latest('id')->first();

    expect($export)->not->toBeNull();

    $payload = json_decode($export->filters, true);

    expect($payload['imported_object_ids'])->toBe(['273', '360']);
    expect($payload['imported_object_id_target'])->toBe('housing_unit');

    Queue::assertPushed(ExportDataJob::class);
});

it('filters exports by housing unit objectid without matching building objectids', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 9001,
        'globalid' => 'housing-filter-parent-building',
    ]);

    HousingUnit::query()->create([
        'objectid' => 7001,
        'globalid' => 'housing-filter-unit',
        'parentglobalid' => 'housing-filter-parent-building',
    ]);

    Building::query()->create([
        'objectid' => 7001,
        'globalid' => 'building-with-same-objectid-as-unit',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'building_columns' => ['objectid'],
            'imported_object_ids' => ['7001'],
            'imported_object_id_target' => 'housing_unit',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    try {
        (new ExportDataJob($export->id))->handle();

        $export->refresh();

        expect($export->status)->toBe('done');
        expect($export->processed)->toBe(1);

        $reader = new Reader;
        $reader->open(storage_path('app/public/'.$export->file_name));

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        expect($rows[1])->toBe([9001]);
    } finally {
        $export->refresh();

        if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
            unlink(storage_path('app/public/'.$export->file_name));
        }
    }
});

it('marks an orphaned processing export as failed when checking status', function () {
    $user = User::factory()->create();

    $export = Export::query()->create([
        'status' => 'processing',
        'filters' => json_encode([
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 1,
        'processed' => 0,
        'file_name' => null,
    ]);

    $export->forceFill([
        'created_at' => now()->subSeconds(11),
        'updated_at' => now()->subSeconds(11),
    ])->save();

    $response = $this
        ->actingAs($user)
        ->get(route('export.status', $export));

    $response
        ->assertOk()
        ->assertJsonPath('status', 'failed');

    expect($export->fresh()->status)->toBe('failed');
});

it('runs an orphaned pending export inline when checking status', function () {
    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 9001,
        'globalid' => 'pending-export-building',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    $export->forceFill([
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ])->save();

    $response = $this
        ->actingAs($user)
        ->get(route('export.status', $export));

    $response
        ->assertOk()
        ->assertJsonPath('status', 'done')
        ->assertJsonPath('processed', 1)
        ->assertJsonPath('total_rows', 1);

    $export->refresh();

    expect($export->file_name)->not->toBeNull();

    if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
        unlink(storage_path('app/public/'.$export->file_name));
    }
});

it('runs an orphaned pending attachment column export with the attachment job when checking status', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/9002/attachments' => Http::response([
            'attachmentInfos' => [],
        ]),
    ]);

    $user = User::factory()->create();

    Building::query()->create([
        'objectid' => 9002,
        'globalid' => 'pending-attachment-export-building',
    ]);

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'export_type' => 'excel',
            'export_mode' => 'data',
            'building_columns' => ['objectid'],
            'attachment_sources' => ['building_arcgis'],
            'attachment_type_filters' => ['identity'],
            'include_attachment_excel_columns' => '1',
            'attachment_excel_display' => 'images',
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    $export->forceFill([
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ])->save();

    $response = $this
        ->actingAs($user)
        ->get(route('export.status', $export));

    $response
        ->assertOk()
        ->assertJsonPath('status', 'done')
        ->assertJsonPath('processed', 1)
        ->assertJsonPath('total_rows', 1);

    $export->refresh();

    $spreadsheet = IOFactory::load(storage_path('app/public/'.$export->file_name));
    $sheet = $spreadsheet->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('Objectid');
    expect($sheet->getCell('B2')->getValue())->toBe('لا توجد مرفقات مطابقة');

    $spreadsheet->disconnectWorksheets();

    if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
        unlink(storage_path('app/public/'.$export->file_name));
    }
});
