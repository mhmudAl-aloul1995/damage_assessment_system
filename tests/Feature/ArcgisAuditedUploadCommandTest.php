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
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response([
            'features' => [
                ['attributes' => ['objectid' => 9002, 'old_objectid' => 200]],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0]['attributes'])->toMatchArray([
                'old_objectid' => 100,
                'globalid' => 'building-globalid',
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
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9001/addAttachment' => Http::response([
            'addAttachmentResult' => ['success' => true],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/9002/addAttachment' => Http::response([
            'addAttachmentResult' => ['success' => true],
        ]),
    ]);

    $this->artisan('arcgis:upload-audited')
        ->expectsOutput('buildings_uploaded: 1')
        ->expectsOutput('units_uploaded: 0')
        ->expectsOutput('attachments_uploaded: 2')
        ->expectsOutput('errors: 0')
        ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/addFeatures');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/9001/addAttachment');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/9002/addAttachment');
});
