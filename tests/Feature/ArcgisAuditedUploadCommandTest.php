<?php

use App\services\ArcgisAuditedUploadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

it('uploads cached base table records with the latest audit edit values without audited views', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    DB::table('buildings')->insert([
        'objectid' => 7100,
        'globalid' => 'base-building-globalid',
        'building_damage_status' => 'minor',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 7200,
        'globalid' => 'base-unit-globalid',
        'parentglobalid' => 'base-building-globalid',
        'unit_damage_status' => 'minor',
    ]);

    DB::table('edit_assessments')->insert([
        [
            'global_id' => 'base-building-globalid',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => 'major',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ],
        [
            'global_id' => 'base-building-globalid',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => 'destroyed',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'global_id' => 'base-unit-globalid',
            'type' => 'housing_table',
            'field_name' => 'unit_damage_status',
            'field_value' => 'severe',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'globalid'],
                ['name' => 'building_damage_status'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'globalid'],
                ['name' => 'parentglobalid'],
                ['name' => 'unit_damage_status'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => function ($request) {
            if ($request['where'] === "old_global_id_B = 'base-building-globalid'") {
                return Http::response([
                    'features' => [
                        ['attributes' => ['globalid' => 'target-building-globalid']],
                    ],
                ]);
            }

            return Http::response(['features' => []]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toMatchArray([
                'old_objectid_B' => 7100,
                'old_global_id_B' => 'base-building-globalid',
                'building_damage_status' => 'destroyed',
                'is_audited' => 1,
            ]);

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 9100],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/addFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toMatchArray([
                'old_objectid_U' => 7200,
                'old_global_id_U' => 'base-unit-globalid',
                'parentglobalid' => 'target-building-globalid',
                'unit_damage_status' => 'severe',
            ]);

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 9200],
                ],
            ]);
        },
    ]);

    $this->artisan('arcgis:upload-audited', [
        '--refresh-cache' => true,
        '--without-attachments' => true,
    ])->assertSuccessful();
});

it('uploads only audited housing units when only units option is used', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::table('buildings')->insert([
        'objectid' => 7300,
        'globalid' => 'only-units-building-globalid',
        'building_damage_status' => 'minor',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 7400,
        'globalid' => 'only-units-unit-globalid',
        'parentglobalid' => 'only-units-building-globalid',
        'unit_damage_status' => 'fully_damaged2',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_global_id_B'],
                ['name' => 'globalid'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'parentglobalid'],
                ['name' => 'unit_damage_status'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response([
            'features' => [
                ['attributes' => ['globalid' => 'target-only-units-building-globalid']],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/addFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toMatchArray([
                'old_objectid_U' => 7400,
                'old_global_id_U' => 'only-units-unit-globalid',
                'parentglobalid' => 'target-only-units-building-globalid',
                'unit_damage_status' => 'fully_damaged2',
                'is_audited' => 1,
            ]);

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 9400],
                ],
            ]);
        },
    ]);

    $this->artisan('arcgis:upload-audited', [
        '--refresh-cache' => true,
        '--without-attachments' => true,
        '--only' => 'units',
    ])->assertSuccessful();

    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/FeatureServer/0/addFeatures'));
});

it('syncs the submitted audit field value directly to the audited arcgis layer', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::table('buildings')->insert([
        'objectid' => 8301,
        'globalid' => 'direct-sync-building-globalid',
        'building_name' => 'Original Building',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'building_name'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response([
            'features' => [
                ['attributes' => ['objectid' => 9301]],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/updateFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toBe([
                'building_name' => 'Direct Submitted Building',
                'objectid' => 9301,
            ]);

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 9301],
                ],
            ]);
        },
    ]);

    app(ArcgisAuditedUploadService::class)->syncAuditEditField(
        'building_table',
        'direct-sync-building-globalid',
        'building_name',
        'Direct Submitted Building',
    );

    Http::assertSentCount(4);
});

it('uploads audited views to arcgis and copies attachments', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('building_damage_status')->nullable();
        $table->string('municipalitie')->nullable();
        $table->string('neighborhood')->nullable();
        $table->string('assignedto')->nullable();
        $table->decimal('x', 10, 7)->nullable();
        $table->decimal('y', 10, 7)->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('parentglobalid')->nullable();
        $table->string('unit_damage_status')->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
    });

    Schema::table('housing_units', function (Blueprint $table): void {
        if (! Schema::hasColumn('housing_units', 'unit_governorate')) {
            $table->string('unit_governorate')->nullable();
        }

        if (! Schema::hasColumn('housing_units', 'unit_municipalitie')) {
            $table->string('unit_municipalitie')->nullable();
        }

        if (! Schema::hasColumn('housing_units', 'unit_neighborhood')) {
            $table->string('unit_neighborhood')->nullable();
        }

        if (! Schema::hasColumn('housing_units', 'unit_building_name')) {
            $table->string('unit_building_name')->nullable();
        }
    });

    DB::table('v_buildings_audited')->insert([
        'objectid' => 100,
        'globalid' => 'building-globalid',
        'building_damage_status' => 'major',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'assignedto' => 'auditor@example.test',
        'x' => 34.4567890,
        'y' => 31.5123450,
    ]);

    DB::table('v_housing_units_audited')->insert([
        'objectid' => 200,
        'globalid' => 'unit-globalid',
        'parentglobalid' => 'building-globalid',
        'unit_damage_status' => 'minor',
        'longitude' => 34.1234560,
        'latitude' => 31.6543210,
    ]);

    DB::table('buildings')->insert([
        'objectid' => 100,
        'globalid' => 'building-globalid',
        'building_name' => 'Uploaded Parent Building',
        'governorate' => 'Gaza Governorate',
        'municipalitie' => 'Gaza Municipality',
        'neighborhood' => 'Rimal',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 200,
        'globalid' => 'unit-globalid',
        'parentglobalid' => 'building-globalid',
        'unit_governorate' => 'ArcGIS Unit Governorate',
        'unit_municipalitie' => 'ArcGIS Unit Municipality',
        'unit_neighborhood' => 'ArcGIS Unit Neighborhood',
        'unit_building_name' => 'ArcGIS Unit Building',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'globalid'],
                ['name' => 'building_damage_status'],
                ['name' => 'municipalitie'],
                ['name' => 'neighborhood'],
                ['name' => 'assignedto'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'globalid'],
                ['name' => 'parentglobalid'],
                ['name' => 'unit_damage_status'],
                ['name' => 'unit_governorate'],
                ['name' => 'unit_municipalitie'],
                ['name' => 'unit_neighborhood'],
                ['name' => 'unit_building_name'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => function ($request) {
            if ($request['where'] === "old_global_id_B = 'building-globalid'") {
                return Http::response([
                    'features' => [
                        ['attributes' => ['globalid' => 'target-building-globalid']],
                    ],
                ]);
            }

            return Http::response(['features' => []]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response([
            'features' => [
                ['attributes' => ['objectid' => 9002, 'old_objectid_U' => 200]],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response([
            'features' => [
                [
                    'geometry' => [
                        'x' => 34.4567890,
                        'y' => 31.5123450,
                        'spatialReference' => ['wkid' => 4326],
                    ],
                ],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/query*' => Http::response([
            'features' => [
                [
                    'geometry' => [
                        'x' => 34.1234560,
                        'y' => 31.6543210,
                        'spatialReference' => ['wkid' => 4326],
                    ],
                ],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toMatchArray([
                'old_objectid_B' => 100,
                'old_global_id_B' => 'building-globalid',
                'building_damage_status' => 'major',
                'municipalitie' => 'Gaza',
                'neighborhood' => 'Rimal',
                'assignedto' => 'auditor@example.test',
                'is_audited' => 1,
            ]);
            expect($features[0]['geometry']['spatialReference']['wkid'])->toBe(4326);

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 9001],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/updateFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toMatchArray([
                'objectid' => 9002,
                'old_objectid_U' => 200,
                'old_global_id_U' => 'unit-globalid',
                'parentglobalid' => 'target-building-globalid',
                'unit_damage_status' => 'minor',
                'unit_governorate' => 'Gaza Governorate',
                'unit_municipalitie' => 'Gaza Municipality',
                'unit_neighborhood' => 'Rimal',
                'unit_building_name' => 'Uploaded Parent Building',
                'is_audited' => 1,
            ]);
            expect($features[0]['geometry']['spatialReference']['wkid'])->toBe(4326);

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 9002],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9001/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/9002/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/100/attachments?*' => Http::response([
            'attachmentInfos' => [
                ['id' => 501, 'name' => 'building-photo.jpg', 'size' => 15],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/200/attachments?*' => Http::response([
            'attachmentInfos' => [
                ['id' => 601, 'name' => 'unit-photo.jpg', 'size' => 11],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/100/attachments/501*' => Http::response('building-binary'),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/200/attachments/601*' => Http::response('unit-binary'),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9001/addAttachment*' => Http::response([
            'addAttachmentResult' => ['success' => true],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/9002/addAttachment*' => Http::response([
            'addAttachmentResult' => ['success' => true],
        ]),
    ]);

    $this->artisan('arcgis:upload-audited')->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/updateFeatures');
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9001/addAttachment'));
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/9002/addAttachment'));
});

it('refreshes the arcgis token and retries when adding a feature fails with an invalid token', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('unit_damage_status')->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
    });

    DB::table('v_housing_units_audited')->insert([
        'objectid' => 12074,
        'globalid' => 'unit-globalid',
        'unit_damage_status' => 'minor',
        'longitude' => 34.1234560,
        'latitude' => 31.6543210,
    ]);

    $tokenRequests = 0;
    $addFeatureRequests = 0;

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => function () use (&$tokenRequests) {
            $tokenRequests++;

            return Http::response([
                'token' => $tokenRequests === 1 ? 'expired-token' : 'refreshed-token',
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'globalid'],
                ['name' => 'unit_damage_status'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/query*' => Http::response([
            'features' => [
                ['geometry' => ['x' => 34.1234560, 'y' => 31.6543210]],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/addFeatures' => function ($request) use (&$addFeatureRequests) {
            $addFeatureRequests++;

            if ($addFeatureRequests === 1) {
                expect($request['token'])->toBe('expired-token');

                return Http::response([
                    'error' => [
                        'code' => 498,
                        'message' => 'Invalid token.',
                        'details' => ['Invalid token.'],
                    ],
                ]);
            }

            expect($request['token'])->toBe('refreshed-token');

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 9002],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/12074/attachments?*' => Http::response(['attachmentInfos' => []]),
    ]);

    $this->artisan('arcgis:upload-audited')->assertSuccessful();

    expect($tokenRequests)->toBe(2);
    expect($addFeatureRequests)->toBe(2);
});

it('can upload only a limited number of buildings with their housing units', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('parentglobalid')->nullable();
    });

    foreach (range(1, 6) as $index) {
        DB::table('v_buildings_audited')->insert([
            'objectid' => $index,
            'globalid' => "building-{$index}",
        ]);

        DB::table('v_housing_units_audited')->insert([
            'objectid' => 100 + $index,
            'globalid' => "unit-{$index}",
            'parentglobalid' => "building-{$index}",
        ]);
    }

    $buildingUploads = 0;
    $unitUploads = 0;
    $nextObjectId = 9000;

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'globalid'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'globalid'],
                ['name' => 'parentglobalid'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => function ($request) {
            if (str_starts_with((string) $request['where'], 'old_global_id_B = ')) {
                $sourceGlobalId = trim(str_replace('old_global_id_B = ', '', (string) $request['where']), "'");

                return Http::response([
                    'features' => [
                        ['attributes' => ['globalid' => "target-{$sourceGlobalId}"]],
                    ],
                ]);
            }

            return Http::response(['features' => []]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => function () use (&$buildingUploads, &$nextObjectId) {
            $buildingUploads++;
            $nextObjectId++;

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => $nextObjectId],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/addFeatures' => function ($request) use (&$unitUploads, &$nextObjectId) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes']['parentglobalid'])->not->toBe('building-6');
            expect(str_starts_with($features[0]['attributes']['parentglobalid'], 'target-building-'))->toBeTrue();

            $unitUploads++;
            $nextObjectId++;

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => $nextObjectId],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/*/attachments?*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/*/attachments?*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/*/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/*/attachments*' => Http::response(['attachmentInfos' => []]),
    ]);

    $this->artisan('arcgis:upload-audited', ['--buildings-limit' => 5])->assertSuccessful();

    expect($buildingUploads)->toBe(5);
    expect($unitUploads)->toBe(5);
});

it('uploads only records edited on or after the changed since date', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('building_damage_status')->nullable();
        $table->dateTime('editdate')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('parentglobalid')->nullable();
        $table->string('unit_damage_status')->nullable();
        $table->dateTime('editdate')->nullable();
    });

    DB::table('v_buildings_audited')->insert([
        [
            'objectid' => 100,
            'globalid' => 'building-edited-on-boundary',
            'building_damage_status' => 'major',
            'editdate' => '2026-07-27 10:00:00',
        ],
        [
            'objectid' => 101,
            'globalid' => 'building-before-boundary',
            'building_damage_status' => 'minor',
            'editdate' => '2026-07-27 23:59:59',
        ],
        [
            'objectid' => 102,
            'globalid' => 'building-new-by-editdate',
            'building_damage_status' => 'new',
            'editdate' => '2026-07-28 00:00:00',
        ],
    ]);

    DB::table('v_housing_units_audited')->insert([
        [
            'objectid' => 200,
            'globalid' => 'unit-under-edited-building-without-own-edit',
            'parentglobalid' => 'building-edited-on-boundary',
            'unit_damage_status' => 'not-uploaded',
            'editdate' => '2026-07-27 10:00:00',
        ],
        [
            'objectid' => 201,
            'globalid' => 'unit-edited-after-boundary',
            'parentglobalid' => 'building-parent-for-edited-unit',
            'unit_damage_status' => 'uploaded',
            'editdate' => '2026-07-27 10:00:00',
        ],
        [
            'objectid' => 202,
            'globalid' => 'unit-new-by-editdate',
            'parentglobalid' => 'building-parent-for-edited-unit',
            'unit_damage_status' => 'new',
            'editdate' => '2026-07-28 00:00:00',
        ],
    ]);

    DB::table('buildings')->insert([
        [
            'objectid' => 301,
            'globalid' => 'building-parent-for-edited-unit',
            'building_name' => 'Parent For Edited Unit',
            'governorate' => 'Gaza',
            'municipalitie' => 'Gaza',
            'neighborhood' => 'Rimal',
            'editdate' => null,
        ],
        [
            'objectid' => 102,
            'globalid' => 'building-new-by-editdate',
            'building_name' => null,
            'governorate' => null,
            'municipalitie' => null,
            'neighborhood' => null,
            'editdate' => '2026-07-28 00:00:00',
        ],
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 202,
        'globalid' => 'unit-new-by-editdate',
        'parentglobalid' => 'building-parent-for-edited-unit',
        'editdate' => '2026-07-28 00:00:00',
    ]);

    DB::table('edit_assessments')->insert([
        [
            'global_id' => 'building-edited-on-boundary',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => 'major',
            'created_at' => '2026-07-28 00:00:00',
            'updated_at' => '2026-07-28 00:00:00',
        ],
        [
            'global_id' => 'building-before-boundary',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => 'minor',
            'created_at' => '2026-07-27 23:59:59',
            'updated_at' => '2026-07-27 23:59:59',
        ],
        [
            'global_id' => 'unit-edited-after-boundary',
            'type' => 'housing_table',
            'field_name' => 'unit_damage_status',
            'field_value' => 'uploaded',
            'created_at' => '2026-07-28 12:00:00',
            'updated_at' => '2026-07-28 12:00:00',
        ],
    ]);

    $buildingUploads = 0;
    $unitUploads = 0;

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'globalid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'building_damage_status'],
                ['name' => 'editdate'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'globalid'],
                ['name' => 'parentglobalid'],
                ['name' => 'unit_damage_status'],
                ['name' => 'editdate'],
                ['name' => 'unit_governorate'],
                ['name' => 'unit_municipalitie'],
                ['name' => 'unit_neighborhood'],
                ['name' => 'unit_building_name'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => function ($request) {
            if ($request['where'] === "old_global_id_B = 'building-parent-for-edited-unit'") {
                return Http::response([
                    'features' => [
                        ['attributes' => ['globalid' => 'target-building-parent-for-edited-unit']],
                    ],
                ]);
            }

            return Http::response(['features' => []]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => function ($request) use (&$buildingUploads) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes']['old_objectid_B'])->toBeIn([100, 102]);
            expect($features[0]['attributes']['old_global_id_B'])->toBeIn([
                'building-edited-on-boundary',
                'building-new-by-editdate',
            ]);
            expect($features[0]['attributes']['is_audited'])->toBe(1);

            $buildingUploads++;

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 9100],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/addFeatures' => function ($request) use (&$unitUploads) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes']['old_objectid_U'])->toBeIn([201, 202]);
            expect($features[0]['attributes']['old_global_id_U'])->toBeIn([
                'unit-edited-after-boundary',
                'unit-new-by-editdate',
            ]);
            expect($features[0]['attributes'])->toMatchArray([
                'parentglobalid' => 'target-building-parent-for-edited-unit',
                'unit_governorate' => 'Gaza',
                'unit_municipalitie' => 'Gaza',
                'unit_neighborhood' => 'Rimal',
                'unit_building_name' => 'Parent For Edited Unit',
                'is_audited' => 1,
            ]);

            $unitUploads++;

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 9200],
                ],
            ]);
        },
    ]);

    $summary = app(\App\services\ArcgisAuditedUploadService::class)->upload(
        withoutAttachments: true,
        changedSince: \Carbon\CarbonImmutable::parse('2026-07-28'),
    );

    expect($buildingUploads)->toBe(2);
    expect($unitUploads)->toBe(2);
    expect($summary['buildings_to_sync'])->toBe(2);
    expect($summary['units_to_sync'])->toBe(2);

    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->body(), 'unit-under-edited-building-without-own-edit'));
    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->body(), 'building-before-boundary'));
});

it('can ignore editdate and sync only audit edits', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('building_damage_status')->nullable();
        $table->dateTime('editdate')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('unit_damage_status')->nullable();
        $table->dateTime('editdate')->nullable();
    });

    DB::table('buildings')->insert([
        'objectid' => 910,
        'globalid' => 'building-editdate-only',
        'editdate' => '2026-07-28 00:00:00',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 920,
        'globalid' => 'unit-editdate-only',
        'editdate' => '2026-07-28 00:00:00',
    ]);

    DB::table('v_buildings_audited')->insert([
        [
            'objectid' => 900,
            'globalid' => 'building-audit-edit',
            'building_damage_status' => 'major',
            'editdate' => '2026-07-27 00:00:00',
        ],
        [
            'objectid' => 910,
            'globalid' => 'building-editdate-only',
            'building_damage_status' => 'should-skip',
            'editdate' => '2026-07-28 00:00:00',
        ],
    ]);

    DB::table('v_housing_units_audited')->insert([
        [
            'objectid' => 901,
            'globalid' => 'unit-audit-edit',
            'unit_damage_status' => 'minor',
            'editdate' => '2026-07-27 00:00:00',
        ],
        [
            'objectid' => 920,
            'globalid' => 'unit-editdate-only',
            'unit_damage_status' => 'should-skip',
            'editdate' => '2026-07-28 00:00:00',
        ],
    ]);

    DB::table('edit_assessments')->insert([
        [
            'global_id' => 'building-audit-edit',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => 'major',
            'created_at' => '2026-07-28 01:00:00',
            'updated_at' => '2026-07-28 01:00:00',
        ],
        [
            'global_id' => 'unit-audit-edit',
            'type' => 'housing_table',
            'field_name' => 'unit_damage_status',
            'field_value' => 'minor',
            'created_at' => '2026-07-28 02:00:00',
            'updated_at' => '2026-07-28 02:00:00',
        ],
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'building_damage_status'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'unit_damage_status'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => Http::response([
            'addResults' => [
                ['success' => true, 'objectId' => 9900],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/addFeatures' => Http::response([
            'addResults' => [
                ['success' => true, 'objectId' => 9901],
            ],
        ]),
    ]);

    $summary = app(\App\services\ArcgisAuditedUploadService::class)->upload(
        withoutAttachments: true,
        changedSince: \Carbon\CarbonImmutable::parse('2026-07-28'),
        onlyAuditEdits: true,
    );

    expect($summary['buildings_to_sync'])->toBe(1);
    expect($summary['units_to_sync'])->toBe(1);
    expect($summary['only_audit_edits'])->toBe(1);

    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->body(), 'building-editdate-only'));
    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->body(), 'unit-editdate-only'));
});

it('partially updates existing target features from audit edits and still copies new attachments', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('building_damage_status')->nullable();
        $table->string('municipalitie')->nullable();
        $table->dateTime('editdate')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('parentglobalid')->nullable();
        $table->string('unit_damage_status')->nullable();
        $table->string('housing_unit_number')->nullable();
        $table->dateTime('editdate')->nullable();
    });

    DB::table('v_buildings_audited')->insert([
        'objectid' => 700,
        'globalid' => 'existing-building-audit-only',
        'building_damage_status' => 'major',
        'municipalitie' => 'Should Not Be Sent',
        'editdate' => '2026-07-27 10:00:00',
    ]);

    DB::table('v_housing_units_audited')->insert([
        'objectid' => 800,
        'globalid' => 'existing-unit-audit-only',
        'parentglobalid' => 'existing-building-audit-only',
        'unit_damage_status' => 'minor',
        'housing_unit_number' => 'Should Not Be Sent',
        'editdate' => '2026-07-27 10:00:00',
    ]);

    DB::table('edit_assessments')->insert([
        [
            'global_id' => 'existing-building-audit-only',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => 'major',
            'created_at' => '2026-07-28 08:00:00',
            'updated_at' => '2026-07-28 08:00:00',
        ],
        [
            'global_id' => 'existing-unit-audit-only',
            'type' => 'housing_table',
            'field_name' => 'unit_damage_status',
            'field_value' => 'minor',
            'created_at' => '2026-07-28 09:00:00',
            'updated_at' => '2026-07-28 09:00:00',
        ],
    ]);

    $buildingUpdateRequests = 0;
    $unitUpdateRequests = 0;

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'building_damage_status'],
                ['name' => 'municipalitie'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_U'],
                ['name' => 'old_global_id_U'],
                ['name' => 'unit_damage_status'],
                ['name' => 'housing_unit_number'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response([
            'features' => [
                ['attributes' => ['objectid' => 9700]],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response([
            'features' => [
                ['attributes' => ['objectid' => 9800]],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/updateFeatures' => function ($request) use (&$buildingUpdateRequests) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toBe([
                'building_damage_status' => 'major',
                'objectid' => 9700,
            ]);

            $buildingUpdateRequests++;

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 9700],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/updateFeatures' => function ($request) use (&$unitUpdateRequests) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toBe([
                'unit_damage_status' => 'minor',
                'objectid' => 9800,
            ]);

            $unitUpdateRequests++;

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 9800],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9700/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/9800/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/700/attachments?*' => Http::response([
            'attachmentInfos' => [
                ['id' => 1701, 'name' => 'new-building-photo.jpg', 'size' => 7],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/800/attachments?*' => Http::response([
            'attachmentInfos' => [
                ['id' => 1801, 'name' => 'new-unit-photo.jpg', 'size' => 8],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/700/attachments/1701*' => Http::response('building-image'),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/800/attachments/1801*' => Http::response('unit-image'),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9700/addAttachment*' => Http::response([
            'addAttachmentResult' => ['success' => true],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/9800/addAttachment*' => Http::response([
            'addAttachmentResult' => ['success' => true],
        ]),
    ]);

    $summary = app(\App\services\ArcgisAuditedUploadService::class)->upload(
        changedSince: \Carbon\CarbonImmutable::parse('2026-07-28'),
    );

    expect($buildingUpdateRequests)->toBe(1);
    expect($unitUpdateRequests)->toBe(1);
    expect($summary['features_partially_updated'])->toBe(2);
    expect($summary['attachments_uploaded'])->toBe(2);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/addFeatures'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'SOURCE/FeatureServer/10/query'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'SOURCE/FeatureServer/11/query'));
});

it('uses building old global id when the target layer does not have building old objectid', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('building_damage_status')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
    });

    DB::table('v_buildings_audited')->insert([
        'objectid' => 1000,
        'globalid' => 'existing-building-globalid',
        'building_damage_status' => 'updated',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_global_id_B'],
                ['name' => 'building_damage_status'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => function ($request) {
            expect($request['where'])->toBe("old_global_id_B = 'existing-building-globalid'");

            return Http::response([
                'features' => [
                    ['attributes' => ['objectid' => 9901]],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/updateFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toMatchArray([
                'objectid' => 9901,
                'old_global_id_B' => 'existing-building-globalid',
                'building_damage_status' => 'updated',
                'is_audited' => 1,
            ]);
            expect($features[0]['attributes'])->not->toHaveKey('old_objectid_B');
            expect($features[0]['attributes'])->not->toHaveKey('globalid');

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 9901],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/1000/attachments?*' => Http::response(['attachmentInfos' => []]),
    ]);

    $this->artisan('arcgis:upload-audited')->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/updateFeatures');
});

it('skips source attachments that arcgis reports but cannot download', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
    });

    DB::table('v_buildings_audited')->insert([
        'objectid' => 300,
        'globalid' => 'building-with-missing-attachment',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => Http::response([
            'addResults' => [
                ['success' => true, 'objectId' => 9300],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9300/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/300/attachments?*' => Http::response([
            'attachmentInfos' => [
                ['id' => 178606, 'name' => 'missing-photo.jpg', 'size' => 120],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/300/attachments/178606*' => Http::response([
            'error' => [
                'code' => 404,
                'message' => 'Unable to complete operation.',
                'details' => ['None. This feature has no associated attachments.'],
            ],
        ], 404),
    ]);

    $this->artisan('arcgis:upload-audited')->assertSuccessful();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/addAttachment'));
});

it('moves comments recommendations into the v1 field before uploading', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->text('Comments_Recommendations')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
    });

    DB::table('v_buildings_audited')->insert([
        'objectid' => 400,
        'globalid' => 'building-with-comments',
        'Comments_Recommendations' => str_repeat('Long comment ', 80),
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'Comments_Recommendations'],
                ['name' => 'comments_recommendations_v1'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toHaveKey('comments_recommendations_v1');
            expect($features[0]['attributes'])->not->toHaveKey('Comments_Recommendations');

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 9400],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9400/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/400/attachments?*' => Http::response(['attachmentInfos' => []]),
    ]);

    $this->artisan('arcgis:upload-audited')->assertSuccessful();
});

it('keeps target object id while replacing target objectid attribute with the old source objectid', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::table('audited_buildings')->insert([
        'objectid' => 1234,
        'globalid' => 'building-with-target-objectid-field',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'OBJECTID',
            'fields' => [
                ['name' => 'OBJECTID'],
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response([
            'features' => [
                ['attributes' => ['OBJECTID' => 9100]],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/updateFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toMatchArray([
                'OBJECTID' => 9100,
                'objectid' => 1234,
                'old_objectid_B' => 1234,
                'old_global_id_B' => 'building-with-target-objectid-field',
                'is_audited' => 1,
            ]);

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 9100],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9100/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/1234/attachments?*' => Http::response(['attachmentInfos' => []]),
    ]);

    $this->artisan('arcgis:upload-audited')->assertSuccessful();
});

it('can upload features without copying attachments', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
    });

    DB::table('v_buildings_audited')->insert([
        'objectid' => 500,
        'globalid' => 'building-without-attachments',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => Http::response([
            'addResults' => [
                ['success' => true, 'objectId' => 9500],
            ],
        ]),
    ]);

    $this->artisan('arcgis:upload-audited', ['--without-attachments' => true])->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/attachments'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/addAttachment'));
});

it('can skip candidate counts before syncing', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
    });

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
    ]);

    $summary = app(\App\services\ArcgisAuditedUploadService::class)->upload(skipCounts: true);

    expect($summary['candidate_counts_skipped'])->toBe(1);
    expect($summary)->not->toHaveKey('buildings_to_sync');
    expect($summary)->not->toHaveKey('units_to_sync');
});

it('can copy attachments only for existing target features', function () {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
    });

    DB::table('v_buildings_audited')->insert([
        'objectid' => 600,
        'globalid' => 'building-attachments-only',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'old_objectid_B'],
                ['name' => 'old_global_id_B'],
                ['name' => 'is_audited'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response([
            'features' => [
                ['attributes' => ['objectid' => 9600]],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9600/attachments*' => Http::response(['attachmentInfos' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/600/attachments?*' => Http::response([
            'attachmentInfos' => [
                ['id' => 701, 'name' => 'photo.jpg', 'size' => 5],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/600/attachments/701*' => Http::response('image'),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9600/addAttachment*' => Http::response([
            'addAttachmentResult' => ['success' => true],
        ]),
    ]);

    $this->artisan('arcgis:upload-audited', ['--attachments-only' => true])->assertSuccessful();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/addFeatures'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/updateFeatures'));
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9600/addAttachment'));
});
