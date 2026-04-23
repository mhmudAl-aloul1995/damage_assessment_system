<?php

use App\Jobs\ExportDataJob;
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

    $response = $this->post('/exports/start', [
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
