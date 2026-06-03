<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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
