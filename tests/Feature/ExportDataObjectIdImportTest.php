<?php

use App\Jobs\ExportDataJob;
use App\Models\Building;
use App\Models\Export;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

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
        ->assertJsonPath('count', 3);

    expect(session('exports.imported_object_ids'))->toBe(['1001', '1002', '1003']);
});

it('passes imported objectids into the export payload', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->withSession([
        'exports.imported_object_ids' => ['1001', '1002', '1002', '1003'],
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

    Queue::assertPushed(ExportDataJob::class);
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
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
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
        ->assertJsonPath('processed', 1);

    $export->refresh();

    expect($export->file_name)->not->toBeNull();

    if ($export->file_name && is_file(storage_path('app/public/'.$export->file_name))) {
        unlink(storage_path('app/public/'.$export->file_name));
    }
});
