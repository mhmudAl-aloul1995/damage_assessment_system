<?php

use App\Models\BuildingSurveyArchiveObject;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('marks workbook buildings and units as not completed on the target arcgis service', function (): void {
    ensureHousingStatusColumns();

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://target.example.test/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);

    $archiver = User::factory()->create();

    DB::table('buildings')->insert([
        'objectid' => 829,
        'globalid' => 'source-building-globalid',
        'building_name' => 'Phase exception building',
        'field_status' => 'COMPLETED',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 9001,
        'globalid' => 'source-unit-globalid',
        'parentglobalid' => 'source-building-globalid',
        'field_status' => 'COMPLETED',
        'building_field_status' => 'COMPLETED',
    ]);

    $workbookPath = phaseExceptionsWorkbookPath([
        [829, 'Phase exception building'],
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://target.example.test/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'OBJECTID',
            'fields' => [
                ['name' => 'OBJECTID'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'Field_status'],
            ],
        ]),
        'https://target.example.test/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'OBJECTID',
            'fields' => [
                ['name' => 'OBJECTID'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'field_status'],
                ['name' => 'building_field_status'],
            ],
        ]),
        'https://target.example.test/FeatureServer/0/query*' => function ($request) {
            expect($request['where'])->toBe('old_objectid_B = 829');

            return Http::response([
                'features' => [
                    ['attributes' => ['OBJECTID' => 9100]],
                ],
            ]);
        },
        'https://target.example.test/FeatureServer/1/query*' => function ($request) {
            expect($request['where'])->toBe('old_objectid_U = 9001');

            return Http::response([
                'features' => [
                    ['attributes' => ['OBJECTID' => 9200]],
                ],
            ]);
        },
        'https://target.example.test/FeatureServer/0/updateFeatures' => function ($request) {
            expect($request->header('Content-Type')[0] ?? '')->toContain('application/x-www-form-urlencoded');

            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toBe([
                'OBJECTID' => 9100,
                'Field_status' => 'Not_Completed',
            ]);

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 9100],
                ],
            ]);
        },
        'https://target.example.test/FeatureServer/1/updateFeatures' => function ($request) {
            expect($request->header('Content-Type')[0] ?? '')->toContain('application/x-www-form-urlencoded');

            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toBe([
                'OBJECTID' => 9200,
                'field_status' => 'Not_Completed',
                'building_field_status' => 'Not_Completed',
            ]);

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 9200],
                ],
            ]);
        },
    ]);

    $this->artisan('arcgis:mark-phase-exceptions-not-completed', [
        'file' => $workbookPath,
        '--archived-by' => $archiver->id,
    ])->assertSuccessful();

    $archives = BuildingSurveyArchiveObject::query()
        ->where('building_objectid', 829)
        ->orderBy('id')
        ->get();

    expect($archives)->toHaveCount(2)
        ->and($archives[0]->source_type)->toBe('phase_exception_not_completed')
        ->and($archives[0]->archived_by)->toBe($archiver->id)
        ->and($archives[0]->building_snapshot['field_status'])->toBe('COMPLETED')
        ->and($archives[0]->housing_unit_snapshot)->toBeNull()
        ->and($archives[1]->housing_unit_objectid)->toBe(9001)
        ->and($archives[1]->housing_unit_snapshot['field_status'])->toBe('COMPLETED')
        ->and($archives[1]->housing_unit_snapshot['building_field_status'])->toBe('COMPLETED');

    expect(DB::table('buildings')->where('objectid', 829)->value('field_status'))->toBe('Not_Completed')
        ->and(DB::table('housing_units')->where('objectid', 9001)->value('field_status'))->toBe('Not_Completed')
        ->and(DB::table('housing_units')->where('objectid', 9001)->value('building_field_status'))->toBe('Not_Completed');

    Http::assertSentCount(7);
});

it('keeps local statuses unchanged when arcgis rejects the update after archiving', function (): void {
    ensureHousingStatusColumns();

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://target.example.test/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);

    $archiver = User::factory()->create();

    DB::table('buildings')->insert([
        'objectid' => 3904,
        'globalid' => 'arcgis-failure-building-globalid',
        'building_name' => 'ArcGIS failure building',
        'field_status' => 'COMPLETED',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 9301,
        'globalid' => 'arcgis-failure-unit-globalid',
        'parentglobalid' => 'arcgis-failure-building-globalid',
        'field_status' => 'COMPLETED',
        'building_field_status' => 'COMPLETED',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://target.example.test/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'OBJECTID',
            'fields' => [
                ['name' => 'OBJECTID'],
                ['name' => 'old_objectid_B'],
                ['name' => 'Field_status'],
            ],
        ]),
        'https://target.example.test/FeatureServer/0/query*' => Http::response([
            'features' => [
                ['attributes' => ['OBJECTID' => 9400]],
            ],
        ]),
        'https://target.example.test/FeatureServer/0/updateFeatures' => Http::response([
            'error' => [
                'code' => 499,
                'message' => 'Token Required',
            ],
        ]),
    ]);

    $this->artisan('arcgis:mark-phase-exceptions-not-completed', [
        '--ids' => '3904',
        '--archived-by' => $archiver->id,
    ])->assertFailed();

    $archives = BuildingSurveyArchiveObject::query()
        ->where('building_objectid', 3904)
        ->orderBy('id')
        ->get();

    expect($archives)->toHaveCount(2)
        ->and($archives[0]->building_snapshot['field_status'])->toBe('COMPLETED')
        ->and($archives[1]->housing_unit_snapshot['field_status'])->toBe('COMPLETED')
        ->and(DB::table('buildings')->where('objectid', 3904)->value('field_status'))->toBe('COMPLETED')
        ->and(DB::table('housing_units')->where('objectid', 9301)->value('field_status'))->toBe('COMPLETED')
        ->and(DB::table('housing_units')->where('objectid', 9301)->value('building_field_status'))->toBe('COMPLETED');
});

it('fails instead of counting target units as updated when their layer has no status fields', function (): void {
    ensureHousingStatusColumns();

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://target.example.test/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);

    $archiver = User::factory()->create();

    DB::table('buildings')->insert([
        'objectid' => 7070,
        'globalid' => 'missing-unit-status-building-globalid',
        'building_name' => 'Missing unit status building',
        'field_status' => 'COMPLETED',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 9701,
        'globalid' => 'missing-unit-status-unit-globalid',
        'parentglobalid' => 'missing-unit-status-building-globalid',
        'field_status' => 'COMPLETED',
        'building_field_status' => 'COMPLETED',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://target.example.test/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'OBJECTID',
            'fields' => [
                ['name' => 'OBJECTID'],
                ['name' => 'old_objectid_B'],
                ['name' => 'Field_status'],
            ],
        ]),
        'https://target.example.test/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'OBJECTID',
            'fields' => [
                ['name' => 'OBJECTID'],
                ['name' => 'old_objectid_U'],
            ],
        ]),
        'https://target.example.test/FeatureServer/0/query*' => Http::response([
            'features' => [
                ['attributes' => ['OBJECTID' => 9800]],
            ],
        ]),
        'https://target.example.test/FeatureServer/1/query*' => Http::response([
            'features' => [
                ['attributes' => ['OBJECTID' => 9801]],
            ],
        ]),
        'https://target.example.test/FeatureServer/0/updateFeatures' => Http::response([
            'updateResults' => [
                ['success' => true, 'objectId' => 9800],
            ],
        ]),
    ]);

    $this->artisan('arcgis:mark-phase-exceptions-not-completed', [
        '--ids' => '7070',
        '--archived-by' => $archiver->id,
    ])->assertFailed();

    expect(DB::table('buildings')->where('objectid', 7070)->value('field_status'))->toBe('COMPLETED')
        ->and(DB::table('housing_units')->where('objectid', 9701)->value('field_status'))->toBe('COMPLETED')
        ->and(DB::table('housing_units')->where('objectid', 9701)->value('building_field_status'))->toBe('COMPLETED');
});

it('can preview workbook exceptions without changing local or arcgis records', function (): void {
    ensureHousingStatusColumns();

    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://target.example.test/FeatureServer');

    DB::table('buildings')->insert([
        'objectid' => 1116,
        'globalid' => 'dry-run-building-globalid',
        'building_name' => 'Dry run building',
        'field_status' => 'COMPLETED',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 9101,
        'globalid' => 'dry-run-unit-globalid',
        'parentglobalid' => 'dry-run-building-globalid',
        'field_status' => 'COMPLETED',
        'building_field_status' => 'COMPLETED',
    ]);

    $workbookPath = phaseExceptionsWorkbookPath([
        [1116, 'Dry run building'],
    ]);

    Http::fake();

    $this->artisan('arcgis:mark-phase-exceptions-not-completed', [
        'file' => $workbookPath,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(DB::table('buildings')->where('objectid', 1116)->value('field_status'))->toBe('COMPLETED')
        ->and(DB::table('housing_units')->where('objectid', 9101)->value('field_status'))->toBe('COMPLETED')
        ->and(DB::table('housing_units')->where('objectid', 9101)->value('building_field_status'))->toBe('COMPLETED')
        ->and(BuildingSurveyArchiveObject::query()->where('building_objectid', 1116)->exists())->toBeFalse();

    Http::assertNothingSent();
});

it('can preview direct building ids without requiring a workbook file', function (): void {
    ensureHousingStatusColumns();

    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://target.example.test/FeatureServer');

    DB::table('buildings')->insert([
        'objectid' => 1817,
        'globalid' => 'direct-id-building-globalid',
        'building_name' => 'Direct id building',
        'field_status' => 'COMPLETED',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 9201,
        'globalid' => 'direct-id-unit-globalid',
        'parentglobalid' => 'direct-id-building-globalid',
        'field_status' => 'COMPLETED',
        'building_field_status' => 'COMPLETED',
    ]);

    Http::fake();

    $this->artisan('arcgis:mark-phase-exceptions-not-completed', [
        '--ids' => '1817, 1817',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(DB::table('buildings')->where('objectid', 1817)->value('field_status'))->toBe('COMPLETED')
        ->and(DB::table('housing_units')->where('objectid', 9201)->value('field_status'))->toBe('COMPLETED')
        ->and(DB::table('housing_units')->where('objectid', 9201)->value('building_field_status'))->toBe('COMPLETED')
        ->and(BuildingSurveyArchiveObject::query()->where('building_objectid', 1817)->exists())->toBeFalse();

    Http::assertNothingSent();
});

function ensureHousingStatusColumns(): void
{
    Schema::table('housing_units', function (Blueprint $table): void {
        if (! Schema::hasColumn('housing_units', 'field_status')) {
            $table->text('field_status')->nullable();
        }

        if (! Schema::hasColumn('housing_units', 'building_field_status')) {
            $table->string('building_field_status')->nullable();
        }
    });
}

/**
 * @param  array<int, array{0: int, 1: string}>  $rows
 */
function phaseExceptionsWorkbookPath(array $rows): string
{
    $directory = storage_path('app/testing');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['#', 'Building ID', '# of units', 'Building Name', 'Location'], null, 'A1');

    foreach ($rows as $index => [$buildingId, $buildingName]) {
        $sheet->fromArray([$index + 1, $buildingId, 1, $buildingName, 'Gaza'], null, 'A'.($index + 2));
    }

    $path = $directory.'/phase-exceptions-'.uniqid('', true).'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}
