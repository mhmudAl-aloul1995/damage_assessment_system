<?php

use App\Models\Building;
use App\services\ArcgisAuditedRestoreService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

it('restores audited view data to normal arcgis layers and local tables without adding features', function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.buildings_url', 'https://services.example.test/FeatureServer/0');
    config()->set('services.arcgis.housing_units_url', 'https://services.example.test/FeatureServer/1');

    DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    Schema::dropIfExists('v_buildings_audited');
    Schema::dropIfExists('v_housing_units_audited');

    Schema::create('v_buildings_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('building_name')->nullable();
        $table->string('building_damage_status')->nullable();
        $table->string('municipalitie')->nullable();
        $table->decimal('x', 10, 7)->nullable();
    });

    Schema::create('v_housing_units_audited', function (Blueprint $table): void {
        $table->integer('objectid')->primary();
        $table->string('globalid')->nullable();
        $table->string('parentglobalid')->nullable();
        $table->string('unit_damage_status')->nullable();
        $table->string('unit_owner')->nullable();
        $table->decimal('longitude', 10, 7)->nullable();
    });

    DB::table('buildings')->insert([
        'objectid' => 100,
        'globalid' => 'building-globalid',
        'building_name' => 'Old Building',
        'building_damage_status' => 'old',
        'municipalitie' => 'Old Municipality',
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 200,
        'globalid' => 'unit-globalid',
        'parentglobalid' => 'building-globalid',
        'unit_damage_status' => 'old',
        'unit_owner' => 'Old Owner',
    ]);

    DB::table('v_buildings_audited')->insert([
        'objectid' => 100,
        'globalid' => 'building-globalid',
        'building_name' => 'Audited Building',
        'building_damage_status' => 'audited',
        'municipalitie' => 'Audited Municipality',
        'x' => 34.1234567,
    ]);

    DB::table('v_housing_units_audited')->insert([
        'objectid' => 200,
        'globalid' => 'unit-globalid',
        'parentglobalid' => 'building-globalid',
        'unit_damage_status' => 'audited-unit',
        'unit_owner' => 'Audited Owner',
        'longitude' => 35.1234567,
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/FeatureServer/0?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'globalid'],
                ['name' => 'building_name'],
                ['name' => 'building_damage_status'],
                ['name' => 'municipalitie'],
                ['name' => 'x'],
            ],
        ]),
        'https://services.example.test/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid'],
                ['name' => 'globalid'],
                ['name' => 'parentglobalid'],
                ['name' => 'unit_damage_status'],
                ['name' => 'unit_owner'],
                ['name' => 'longitude'],
            ],
        ]),
        'https://services.example.test/FeatureServer/0/query*' => Http::response([
            'features' => [
                ['attributes' => ['objectid' => 100]],
            ],
        ]),
        'https://services.example.test/FeatureServer/1/query*' => Http::response([
            'features' => [
                ['attributes' => ['objectid' => 200]],
            ],
        ]),
        'https://services.example.test/FeatureServer/0/updateFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0])->not->toHaveKey('geometry');
            expect($features[0]['attributes'])->toMatchArray([
                'objectid' => 100,
                'building_name' => 'Audited Building',
                'building_damage_status' => 'audited',
                'municipalitie' => 'Audited Municipality',
                'x' => '34.1234567',
            ]);
            expect($features[0]['attributes'])->not->toHaveKey('globalid');

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 100],
                ],
            ]);
        },
        'https://services.example.test/FeatureServer/1/updateFeatures' => function ($request) {
            $features = json_decode($request['features'], true);

            expect($features[0])->not->toHaveKey('geometry');
            expect($features[0]['attributes'])->toMatchArray([
                'objectid' => 200,
                'parentglobalid' => 'building-globalid',
                'unit_damage_status' => 'audited-unit',
                'unit_owner' => 'Audited Owner',
                'longitude' => '35.1234567',
            ]);
            expect($features[0]['attributes'])->not->toHaveKey('globalid');

            return Http::response([
                'updateResults' => [
                    ['success' => true, 'objectId' => 200],
                ],
            ]);
        },
    ]);

    $summary = app(ArcgisAuditedRestoreService::class)
        ->restoreBuilding(Building::query()->where('objectid', 100)->firstOrFail());

    expect($summary['building_arcgis_updated'])->toBeTrue()
        ->and($summary['building_local_updated'])->toBeTrue()
        ->and($summary['units_arcgis_updated'])->toBe(1)
        ->and($summary['units_local_updated'])->toBe(1)
        ->and($summary['units_skipped_arcgis'])->toBe(0)
        ->and($summary['units_skipped_local'])->toBe(0);

    expect(DB::table('buildings')->where('objectid', 100)->value('building_name'))->toBe('Audited Building');
    expect(DB::table('housing_units')->where('objectid', 200)->value('unit_owner'))->toBe('Audited Owner');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/FeatureServer/0/updateFeatures');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://services.example.test/FeatureServer/1/updateFeatures');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/addFeatures'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/attachments'));
});
