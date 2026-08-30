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

    Schema::connection('mysql')->dropIfExists('system_operation_logs');
    Schema::connection('mysql')->dropIfExists('users');
    Schema::connection('mysql')->dropIfExists('buildings');

    Schema::connection('mysql')->create('buildings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('objectid')->nullable();
        $table->string('globalid')->nullable();
        $table->string('field_status')->nullable();
        $table->string('security_situation')->nullable();
        $table->string('assessment_obstacle')->nullable();
        $table->string('building_name')->nullable();
        $table->string('building_damage_status')->nullable();
        $table->string('service_ownership')->nullable();
        $table->string('assignedto')->nullable();
        $table->string('owner_mobile')->nullable();
        $table->string('owner_mobile_1')->nullable();
        $table->string('owner_mobile_v_1')->nullable();
        $table->string('end')->nullable();
        $table->string('creationdate')->nullable();
        $table->string('editdate')->nullable();
        $table->string('submissiondate')->nullable();
        $table->double('latitude')->nullable();
        $table->double('longitude')->nullable();
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

it('fills missing assessment obstacle when building security situation is unsafe', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.buildings_url', 'https://example.com/FeatureServer/0');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer/0?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'Security_Situation', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'assessment_obstacle', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'phase_number', 'type' => 'esriFieldTypeSmallInteger'],
            ],
        ]),
        'https://example.com/FeatureServer/0/query*' => Http::response([
            'features' => [
                [
                    'attributes' => [
                        'objectid' => 801,
                        'Security_Situation' => 'Unsafe',
                        'assessment_obstacle' => null,
                    ],
                ],
                [
                    'attributes' => [
                        'objectid' => 802,
                        'Security_Situation' => 'Unsafe',
                        'assessment_obstacle' => 'no',
                    ],
                ],
                [
                    'attributes' => [
                        'objectid' => 803,
                        'Security_Situation' => 'Safe',
                        'assessment_obstacle' => null,
                    ],
                ],
            ],
            'exceededTransferLimit' => false,
        ]),
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', ['table' => 'buildings']);

    expect($exitCode)->toBe(0);
    expect(DB::table('buildings')->where('objectid', 801)->value('assessment_obstacle'))->toBe('yes');
    expect(DB::table('buildings')->where('objectid', 802)->value('assessment_obstacle'))->toBe('no');
    expect(DB::table('buildings')->where('objectid', 803)->value('assessment_obstacle'))->toBeNull();
});

it('syncs building latitude and longitude from arcgis geometry', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.buildings_url', 'https://example.com/FeatureServer/0');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer/0?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'globalid', 'type' => 'esriFieldTypeString', 'length' => 64],
                ['name' => 'field_status', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'building_name', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'end', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'creationdate', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'editdate', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'submissiondate', 'type' => 'esriFieldTypeDate'],
                ['name' => 'New_ArcGIS_Field', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'Shape__Area', 'type' => 'esriFieldTypeDouble'],
                ['name' => 'Shape__Length', 'type' => 'esriFieldTypeDouble'],
                ['name' => 'phase_number', 'type' => 'esriFieldTypeSmallInteger'],
            ],
        ]),
        'https://example.com/FeatureServer/0/query*' => function ($request) {
            $data = $request->data();

            expect($data['returnGeometry'])->toBe('true');
            expect((int) $data['outSR'])->toBe(4326);

            return Http::response([
                'features' => [
                    [
                        'attributes' => [
                            'objectid' => 501,
                            'globalid' => 'building-point-globalid',
                            'field_status' => 'COMPLETED',
                            'building_name' => 'Point Building',
                            'end' => null,
                            'creationdate' => '2026-05-10 09:30:00',
                            'New_ArcGIS_Field' => 'new dynamic value',
                            'Shape__Length' => 123.45,
                        ],
                        'geometry' => [
                            'x' => 34.501,
                            'y' => 31.501,
                        ],
                    ],
                    [
                        'attributes' => [
                            'objectid' => 502,
                            'globalid' => 'building-polygon-globalid',
                            'building_name' => 'Polygon Building',
                        ],
                        'geometry' => [
                            'rings' => [
                                [
                                    [34.0, 31.0],
                                    [35.0, 31.0],
                                    [35.0, 32.0],
                                    [34.0, 32.0],
                                ],
                            ],
                        ],
                    ],
                    [
                        'attributes' => [
                            'objectid' => 503,
                            'globalid' => 'building-completed-with-end-globalid',
                            'field_status' => 'COMPLETED',
                            'building_name' => 'Completed Building With End',
                            'end' => '2026-05-12 11:15:00',
                            'creationdate' => '2026-05-10 09:30:00',
                            'editdate' => '2026-05-13 12:30:00',
                            'submissiondate' => null,
                        ],
                    ],
                ],
                'exceededTransferLimit' => false,
            ]);
        },
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', ['table' => 'buildings']);

    expect($exitCode)->toBe(0);

    $pointBuilding = DB::table('buildings')->where('objectid', 501)->first();
    $polygonBuilding = DB::table('buildings')->where('objectid', 502)->first();
    $completedBuildingWithEnd = DB::table('buildings')->where('objectid', 503)->first();

    expect((float) $pointBuilding->latitude)->toBe(31.501);
    expect((float) $pointBuilding->longitude)->toBe(34.501);
    expect(Schema::connection('mysql')->hasColumn('buildings', 'new_arcgis_field'))->toBeTrue();
    expect(Schema::connection('mysql')->hasColumn('buildings', 'shape__length'))->toBeTrue();
    expect($pointBuilding->new_arcgis_field)->toBe('new dynamic value');
    expect((float) $pointBuilding->shape__length)->toBe(123.45);
    expect($pointBuilding->end)->toBe('2026-05-10 09:30:00');
    expect($pointBuilding->submissiondate)->toBe('2026-05-10 09:30:00');
    expect((float) $polygonBuilding->latitude)->toBe(31.5);
    expect((float) $polygonBuilding->longitude)->toBe(34.5);
    expect($completedBuildingWithEnd->submissiondate)->toBe('2026-05-12 11:15:00');
});

it('fills missing owner mobile from alternate arcgis owner mobile fields', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.buildings_url', 'https://example.com/FeatureServer/0');

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer/0?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'owner_mobile', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'owner_mobile_1', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'owner_mobile_v_1', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'phase_number', 'type' => 'esriFieldTypeSmallInteger'],
            ],
        ]),
        'https://example.com/FeatureServer/0/query*' => Http::response([
            'features' => [
                [
                    'attributes' => [
                        'objectid' => 601,
                        'owner_mobile' => null,
                        'owner_mobile_1' => '599854475',
                        'owner_mobile_v_1' => null,
                    ],
                ],
                [
                    'attributes' => [
                        'objectid' => 602,
                        'owner_mobile' => '',
                        'owner_mobile_1' => '',
                        'owner_mobile_v_1' => '599854476',
                    ],
                ],
                [
                    'attributes' => [
                        'objectid' => 603,
                        'owner_mobile' => '599854477',
                        'owner_mobile_1' => '599854478',
                        'owner_mobile_v_1' => '599854479',
                    ],
                ],
            ],
            'exceededTransferLimit' => false,
        ]),
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', ['table' => 'buildings']);

    expect($exitCode)->toBe(0);

    expect(DB::table('buildings')->where('objectid', 601)->value('owner_mobile'))->toBe('599854475');
    expect(DB::table('buildings')->where('objectid', 602)->value('owner_mobile'))->toBe('599854476');
    expect(DB::table('buildings')->where('objectid', 603)->value('owner_mobile'))->toBe('599854477');
});

it('forces updates when arcgis hash already matches', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.buildings_url', 'https://example.com/FeatureServer/0');

    $incomingRow = [
        'objectid' => 701,
        'globalid' => 'force-update-globalid',
        'building_name' => 'Updated Building Name',
        'latitude' => null,
        'longitude' => null,
    ];
    $hashRow = $incomingRow;
    ksort($hashRow);
    $matchingHash = hash('sha256', json_encode($hashRow, JSON_UNESCAPED_UNICODE));

    DB::table('buildings')->insert([
        'objectid' => 701,
        'globalid' => 'force-update-globalid',
        'building_name' => 'Stale Building Name',
        'arcgis_hash' => $matchingHash,
        'arcgis_synced_at' => '2026-05-01 00:00:00',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer/0?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'globalid', 'type' => 'esriFieldTypeString', 'length' => 64],
                ['name' => 'building_name', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'phase_number', 'type' => 'esriFieldTypeSmallInteger'],
            ],
        ]),
        'https://example.com/FeatureServer/0/query*' => Http::response([
            'features' => [
                [
                    'attributes' => [
                        'objectid' => 701,
                        'globalid' => 'force-update-globalid',
                        'building_name' => 'Updated Building Name',
                    ],
                ],
            ],
            'exceededTransferLimit' => false,
        ]),
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', [
        'table' => 'buildings',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(DB::table('buildings')->where('objectid', 701)->value('building_name'))->toBe('Updated Building Name');
});

it('records field return changes only for completed buildings with a completed return archive', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.buildings_url', 'https://example.com/FeatureServer/0');

    DB::table('buildings')->insert([
        [
            'id' => 1,
            'objectid' => 901,
            'globalid' => 'completed-without-return',
            'field_status' => 'COMPLETED',
            'building_name' => 'Old ordinary name',
            'building_damage_status' => null,
            'service_ownership' => null,
            'assignedto' => null,
            'arcgis_hash' => 'old-hash',
        ],
        [
            'id' => 2,
            'objectid' => 902,
            'globalid' => 'completed-with-return',
            'field_status' => 'COMPLETED',
            'building_name' => 'Old returned name',
            'building_damage_status' => null,
            'service_ownership' => 'All_Owners',
            'assignedto' => 'field.engineer',
            'arcgis_hash' => 'old-hash',
        ],
        [
            'id' => 3,
            'objectid' => 903,
            'globalid' => 'completed-with-equal-return',
            'field_status' => 'COMPLETED',
            'building_name' => 'Equal returned name',
            'building_damage_status' => 'Partially Damaged',
            'service_ownership' => null,
            'assignedto' => 'field.engineer',
            'arcgis_hash' => 'old-hash',
        ],
    ]);

    $fieldEngineerId = DB::table('users')->insertGetId([
        'name' => 'Field Engineer Name',
        'email' => 'field.engineer@example.test',
        'username_arcgis' => 'field.engineer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $returnRequestId = DB::table('building_survey_return_requests')->insertGetId([
        'building_id' => 2,
        'building_objectid' => 902,
        'building_globalid' => 'completed-with-return',
        'current_step' => 'completed',
        'status' => 'completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('building_survey_archive_objects')->insert([
        'building_objectid' => 902,
        'building_globalid' => 'completed-with-return',
        'return_request_id' => $returnRequestId,
        'archived_at' => now(),
        'building_snapshot' => json_encode([
            'objectid' => 902,
            'globalid' => 'completed-with-return',
            'field_status' => 'COMPLETED',
            'building_name' => 'Old returned name',
            'service_ownership' => 'All_Owners',
        ], JSON_UNESCAPED_UNICODE),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $equalReturnRequestId = DB::table('building_survey_return_requests')->insertGetId([
        'building_id' => 3,
        'building_objectid' => 903,
        'building_globalid' => 'completed-with-equal-return',
        'current_step' => 'completed',
        'status' => 'completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('building_survey_archive_objects')->insert([
        'building_objectid' => 903,
        'building_globalid' => 'completed-with-equal-return',
        'return_request_id' => $equalReturnRequestId,
        'archived_at' => now(),
        'building_snapshot' => json_encode([
            'objectid' => 903,
            'globalid' => 'completed-with-equal-return',
            'field_status' => 'COMPLETED',
            'building_name' => 'Equal returned name',
            'building_damage_status' => 'Partially Damaged',
        ], JSON_UNESCAPED_UNICODE),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'arcgis-token',
        ]),
        'https://example.com/FeatureServer/0?*' => Http::response([
            'fields' => [
                ['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'],
                ['name' => 'globalid', 'type' => 'esriFieldTypeString', 'length' => 64],
                ['name' => 'field_status', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'building_name', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'building_damage_status', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'service_ownership', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'assignedto', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'phase_number', 'type' => 'esriFieldTypeSmallInteger'],
            ],
        ]),
        'https://example.com/FeatureServer/0/query*' => Http::response([
            'features' => [
                [
                    'attributes' => [
                        'objectid' => 901,
                        'globalid' => 'completed-without-return',
                        'field_status' => 'COMPLETED',
                        'building_name' => 'New ordinary name',
                        'service_ownership' => null,
                        'assignedto' => null,
                    ],
                ],
                [
                    'attributes' => [
                        'objectid' => 902,
                        'globalid' => 'completed-with-return',
                        'field_status' => 'COMPLETED',
                        'building_name' => 'New returned name',
                        'building_damage_status' => 'Totally Damaged',
                        'service_ownership' => 'جميع الملاك / All_Owners',
                        'assignedto' => 'field.engineer',
                    ],
                ],
                [
                    'attributes' => [
                        'objectid' => 903,
                        'globalid' => 'completed-with-equal-return',
                        'field_status' => 'COMPLETED',
                        'building_name' => 'Equal returned name',
                        'building_damage_status' => 'Partially Damaged',
                        'service_ownership' => null,
                        'assignedto' => 'field.engineer',
                    ],
                ],
            ],
            'exceededTransferLimit' => false,
        ]),
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', ['table' => 'buildings']);

    expect($exitCode)->toBe(0);

    $this->assertDatabaseMissing('assessment_edit_histories', [
        'global_id' => 'completed-without-return',
        'field_name' => 'building_name',
    ]);

    $this->assertDatabaseHas('assessment_edit_histories', [
        'global_id' => 'completed-with-return',
        'objectid' => 902,
        'type' => 'building_table',
        'field_name' => 'building_name',
        'old_value' => 'Old returned name',
        'new_value' => 'New returned name',
        'edited_by' => $fieldEngineerId,
        'return_request_id' => $returnRequestId,
        'source' => 'field_sync',
    ]);

    $this->assertDatabaseHas('edit_assessments', [
        'global_id' => 'completed-with-return',
        'type' => 'building_table',
        'field_name' => 'building_name',
        'field_value' => 'New returned name',
        'user_id' => $fieldEngineerId,
    ]);

    $this->assertDatabaseHas('assessment_edit_histories', [
        'global_id' => 'completed-with-return',
        'field_name' => 'building_damage_status',
        'old_value' => null,
        'new_value' => 'Totally Damaged',
        'edited_by' => $fieldEngineerId,
        'return_request_id' => $returnRequestId,
        'source' => 'field_sync',
    ]);

    $this->assertDatabaseMissing('assessment_edit_histories', [
        'global_id' => 'completed-with-return',
        'field_name' => 'service_ownership',
    ]);

    $this->assertDatabaseMissing('assessment_edit_histories', [
        'global_id' => 'completed-with-equal-return',
        'field_name' => 'building_damage_status',
    ]);

    $this->assertDatabaseMissing('edit_assessments', [
        'global_id' => 'completed-with-equal-return',
        'field_name' => 'building_damage_status',
    ]);
});
