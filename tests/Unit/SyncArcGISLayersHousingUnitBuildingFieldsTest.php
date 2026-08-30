<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');

    Schema::connection('mysql')->dropIfExists('assessment_edit_histories');
    Schema::connection('mysql')->dropIfExists('edit_assessments');
    Schema::connection('mysql')->dropIfExists('building_survey_archive_objects');
    Schema::connection('mysql')->dropIfExists('building_survey_return_requests');
    Schema::connection('mysql')->dropIfExists('system_operation_logs');
    Schema::connection('mysql')->dropIfExists('users');
    Schema::connection('mysql')->dropIfExists('housing_units');
    Schema::connection('mysql')->dropIfExists('buildings');

    Schema::connection('mysql')->create('buildings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('objectid')->nullable();
        $table->string('globalid')->nullable();
        $table->string('governorate')->nullable();
        $table->string('municipalitie')->nullable();
        $table->string('neighborhood')->nullable();
        $table->string('end')->nullable();
        $table->string('submission_date')->nullable();
        $table->string('field_status')->nullable();
        $table->string('assignedto')->nullable();
    });

    Schema::connection('mysql')->create('housing_units', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('objectid')->nullable();
        $table->string('globalid')->nullable();
        $table->string('parentglobalid')->nullable();
        $table->string('governorate')->nullable();
        $table->string('municipalitie')->nullable();
        $table->string('locality')->nullable();
        $table->string('neighborhood')->nullable();
        $table->string('unit_damage_status')->nullable();
        $table->text('building_submit_date')->nullable();
        $table->string('building_field_status')->nullable();
        $table->string('arcgis_hash', 64)->nullable();
        $table->timestamp('arcgis_synced_at')->nullable();
    });

    Schema::connection('mysql')->create('system_operation_logs', function (Blueprint $table): void {
        $table->id();
        $table->string('operation_type');
        $table->string('status')->default('success');
        $table->string('connection_name')->nullable();
        $table->string('layer_name')->nullable();
        $table->unsignedInteger('layer_id')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->string('file_path')->nullable();
        $table->unsignedInteger('total_records')->nullable();
        $table->integer('inserted')->default(0);
        $table->integer('updated')->default(0);
        $table->integer('skipped')->default(0);
        $table->unsignedInteger('duration_seconds')->nullable();
        $table->decimal('records_per_second', 10, 2)->nullable();
        $table->text('message')->nullable();
        $table->timestamps();
    });

    Schema::connection('mysql')->create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('username_arcgis')->nullable();
        $table->timestamps();
    });

    Schema::connection('mysql')->create('building_survey_return_requests', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('building_id')->nullable();
        $table->unsignedBigInteger('building_objectid')->nullable();
        $table->string('building_globalid')->nullable();
        $table->unsignedBigInteger('requested_by')->nullable();
        $table->string('current_step')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::connection('mysql')->create('building_survey_archive_objects', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('building_objectid')->nullable();
        $table->string('building_globalid')->nullable();
        $table->unsignedBigInteger('housing_unit_objectid')->nullable();
        $table->string('housing_unit_globalid')->nullable();
        $table->foreignId('return_request_id')->nullable();
        $table->unsignedBigInteger('archived_by')->nullable();
        $table->timestamp('archived_at')->nullable();
        $table->json('building_snapshot')->nullable();
        $table->json('housing_unit_snapshot')->nullable();
        $table->timestamps();
    });

    Schema::connection('mysql')->create('edit_assessments', function (Blueprint $table): void {
        $table->id();
        $table->string('global_id')->index();
        $table->string('type')->default('building_table');
        $table->string('field_name');
        $table->text('field_value')->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::connection('mysql')->create('assessment_edit_histories', function (Blueprint $table): void {
        $table->id();
        $table->string('global_id')->index();
        $table->unsignedBigInteger('objectid')->nullable();
        $table->string('type');
        $table->string('field_name');
        $table->longText('old_value')->nullable();
        $table->longText('new_value')->nullable();
        $table->unsignedBigInteger('edited_by')->nullable();
        $table->unsignedBigInteger('edit_assessment_id')->nullable();
        $table->unsignedBigInteger('return_request_id')->nullable();
        $table->string('source')->nullable();
        $table->timestamps();
    });
});

it('copies building location fields and submit date when syncing housing units', function (): void {
    DB::table('buildings')->insert([
        'objectid' => 1001,
        'globalid' => 'building-global-id',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza Municipality',
        'neighborhood' => 'Old City',
        'end' => '2026-05-10 08:45:00',
        'submission_date' => '2026-05-11 09:15:00',
        'field_status' => 'COMPLETED',
    ]);

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://example.com/HousingUnits/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/HousingUnits/FeatureServer/1?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'globalid', 'type' => 'esriFieldTypeString', 'length' => 64],
                ['name' => 'parentglobalid', 'type' => 'esriFieldTypeString', 'length' => 64],
                ['name' => 'governorate', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'municipalitie', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'locality', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'neighborhood', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'unit_governorate', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'unit_municipalitie', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'unit_neighborhood', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'unit_building_name', 'type' => 'esriFieldTypeString', 'length' => 255],
            ],
        ]),
        'https://example.com/HousingUnits/FeatureServer/1/query*' => Http::response([
            'features' => [
                [
                    'attributes' => [
                        'objectid' => 2001,
                        'globalid' => 'housing-global-id',
                        'parentglobalid' => 'building-global-id',
                        'governorate' => 'Wrong Governorate',
                        'municipalitie' => 'Wrong Municipality',
                        'locality' => 'Original Locality',
                        'neighborhood' => 'Wrong Neighborhood',
                        'unit_governorate' => 'ArcGIS Unit Governorate',
                        'unit_municipalitie' => 'ArcGIS Unit Municipality',
                        'unit_neighborhood' => 'ArcGIS Unit Neighborhood',
                        'unit_building_name' => 'ArcGIS Unit Building',
                    ],
                ],
            ],
            'exceededTransferLimit' => false,
        ]),
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', ['table' => 'housing_units']);

    expect($exitCode)->toBe(0);

    $housingUnit = DB::table('housing_units')->where('objectid', 2001)->first();

    expect($housingUnit)->not->toBeNull();
    expect($housingUnit->parentglobalid)->toBe('building-global-id');
    expect($housingUnit->governorate)->toBe('Gaza');
    expect($housingUnit->municipalitie)->toBe('Gaza Municipality');
    expect($housingUnit->locality)->toBe('Original Locality');
    expect($housingUnit->neighborhood)->toBe('Old City');
    expect($housingUnit->unit_governorate)->toBe('ArcGIS Unit Governorate');
    expect($housingUnit->unit_municipalitie)->toBe('ArcGIS Unit Municipality');
    expect($housingUnit->unit_neighborhood)->toBe('ArcGIS Unit Neighborhood');
    expect($housingUnit->unit_building_name)->toBe('ArcGIS Unit Building');
    expect($housingUnit->building_submit_date)->toBe('2026-05-11 09:15:00');
    expect($housingUnit->building_field_status)->toBe('COMPLETED');
});

it('deletes missing housing units without a large not in query', function (): void {
    $existingHousingUnits = collect(range(1, 1005))
        ->map(fn (int $objectId): array => [
            'objectid' => $objectId,
            'globalid' => 'existing-housing-unit-'.$objectId,
        ])
        ->all();

    DB::table('housing_units')->insert($existingHousingUnits);

    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://example.com/HousingUnits/FeatureServer/1');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/HousingUnits/FeatureServer/1?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'globalid', 'type' => 'esriFieldTypeString', 'length' => 64],
            ],
        ]),
        'https://example.com/HousingUnits/FeatureServer/1/query*' => function ($request) {
            $offset = (int) $request['resultOffset'];

            if ($offset === 0) {
                return Http::response([
                    'features' => collect(range(1, 1000))
                        ->map(fn (int $objectId): array => [
                            'attributes' => [
                                'objectid' => $objectId,
                                'globalid' => 'synced-housing-unit-'.$objectId,
                            ],
                        ])
                        ->all(),
                    'exceededTransferLimit' => true,
                ]);
            }

            return Http::response([
                'features' => [
                    [
                        'attributes' => [
                            'objectid' => 1001,
                            'globalid' => 'synced-housing-unit-1001',
                        ],
                    ],
                ],
                'exceededTransferLimit' => false,
            ]);
        },
    ]);

    $executedSql = [];
    DB::listen(function ($query) use (&$executedSql): void {
        $executedSql[] = strtolower($query->sql);
    });

    $exitCode = Artisan::call('sync:arcgis-layers', [
        'table' => 'housing_units',
        '--chunk' => 1000,
    ]);

    expect($exitCode)->toBe(0);
    expect(DB::table('housing_units')->whereBetween('objectid', [1002, 1005])->exists())->toBeFalse();
    expect(DB::table('housing_units')->whereBetween('objectid', [1, 1001])->count())->toBe(1001);
    expect(collect($executedSql)->contains(fn (string $sql): bool => str_contains($sql, 'not in')))->toBeFalse();
});

it('records housing unit field return changes with the assigned building engineer', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.housing_units_url', 'https://example.com/HousingUnits/FeatureServer/1');

    DB::table('buildings')->insert([
        'id' => 10,
        'objectid' => 9001,
        'globalid' => 'return-building-global-id',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza Municipality',
        'neighborhood' => 'Old City',
        'submission_date' => '2026-05-11 09:15:00',
        'field_status' => 'COMPLETED',
        'assignedto' => 'field.engineer',
    ]);

    DB::table('housing_units')->insert([
        'id' => 20,
        'objectid' => 9101,
        'globalid' => 'return-housing-unit-global-id',
        'parentglobalid' => 'return-building-global-id',
        'unit_damage_status' => 'Partially Damaged',
        'building_field_status' => 'COMPLETED',
        'arcgis_hash' => 'old-hash',
    ]);

    $fieldEngineerId = DB::table('users')->insertGetId([
        'name' => 'Field Engineer Name',
        'email' => 'field.engineer@example.test',
        'username_arcgis' => 'field.engineer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $returnRequestId = DB::table('building_survey_return_requests')->insertGetId([
        'building_id' => 10,
        'building_objectid' => 9001,
        'building_globalid' => 'return-building-global-id',
        'current_step' => 'completed',
        'status' => 'completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('building_survey_archive_objects')->insert([
        'building_objectid' => 9001,
        'building_globalid' => 'return-building-global-id',
        'housing_unit_objectid' => 9101,
        'housing_unit_globalid' => 'return-housing-unit-global-id',
        'return_request_id' => $returnRequestId,
        'archived_at' => now(),
        'housing_unit_snapshot' => json_encode([
            'objectid' => 9101,
            'globalid' => 'return-housing-unit-global-id',
            'parentglobalid' => 'return-building-global-id',
            'unit_damage_status' => 'Partially Damaged',
            'building_field_status' => 'COMPLETED',
        ], JSON_UNESCAPED_UNICODE),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/HousingUnits/FeatureServer/1?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'globalid', 'type' => 'esriFieldTypeString', 'length' => 64],
                ['name' => 'parentglobalid', 'type' => 'esriFieldTypeString', 'length' => 64],
                ['name' => 'unit_damage_status', 'type' => 'esriFieldTypeString', 'length' => 255],
            ],
        ]),
        'https://example.com/HousingUnits/FeatureServer/1/query*' => Http::response([
            'features' => [
                [
                    'attributes' => [
                        'objectid' => 9101,
                        'globalid' => 'return-housing-unit-global-id',
                        'parentglobalid' => 'return-building-global-id',
                        'unit_damage_status' => 'Totally Damaged',
                    ],
                ],
            ],
            'exceededTransferLimit' => false,
        ]),
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', ['table' => 'housing_units']);

    expect($exitCode)->toBe(0);

    $this->assertDatabaseHas('assessment_edit_histories', [
        'global_id' => 'return-housing-unit-global-id',
        'objectid' => 9101,
        'type' => 'housing_table',
        'field_name' => 'unit_damage_status',
        'old_value' => 'Partially Damaged',
        'new_value' => 'Totally Damaged',
        'edited_by' => $fieldEngineerId,
        'return_request_id' => $returnRequestId,
        'source' => 'field_sync',
    ]);

    $this->assertDatabaseHas('edit_assessments', [
        'global_id' => 'return-housing-unit-global-id',
        'type' => 'housing_table',
        'field_name' => 'unit_damage_status',
        'field_value' => 'Totally Damaged',
        'user_id' => $fieldEngineerId,
    ]);
});
