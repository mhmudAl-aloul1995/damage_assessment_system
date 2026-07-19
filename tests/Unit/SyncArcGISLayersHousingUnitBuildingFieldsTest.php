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
        $table->text('building_submit_date')->nullable();
        $table->text('submission_date')->nullable();
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
    expect($housingUnit->building_submit_date)->toBe('2026-05-10 08:45:00');
    expect($housingUnit->submission_date)->toBe('2026-05-11 09:15:00');
});
