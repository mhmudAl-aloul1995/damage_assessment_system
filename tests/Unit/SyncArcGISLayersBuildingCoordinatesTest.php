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
    Schema::connection('mysql')->dropIfExists('buildings');

    Schema::connection('mysql')->create('buildings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('objectid')->nullable();
        $table->string('globalid')->nullable();
        $table->string('field_status')->nullable();
        $table->string('building_name')->nullable();
        $table->string('end')->nullable();
        $table->string('creationdate')->nullable();
        $table->double('latitude')->nullable();
        $table->double('longitude')->nullable();
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
                ['name' => 'New_ArcGIS_Field', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'Shape__Area', 'type' => 'esriFieldTypeDouble'],
                ['name' => 'Shape__Length', 'type' => 'esriFieldTypeDouble'],
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
                ],
                'exceededTransferLimit' => false,
            ]);
        },
    ]);

    $exitCode = Artisan::call('sync:arcgis-layers', ['table' => 'buildings']);

    expect($exitCode)->toBe(0);

    $pointBuilding = DB::table('buildings')->where('objectid', 501)->first();
    $polygonBuilding = DB::table('buildings')->where('objectid', 502)->first();

    expect((float) $pointBuilding->latitude)->toBe(31.501);
    expect((float) $pointBuilding->longitude)->toBe(34.501);
    expect(Schema::connection('mysql')->hasColumn('buildings', 'new_arcgis_field'))->toBeTrue();
    expect(Schema::connection('mysql')->hasColumn('buildings', 'shape__length'))->toBeTrue();
    expect($pointBuilding->new_arcgis_field)->toBe('new dynamic value');
    expect((float) $pointBuilding->shape__length)->toBe(123.45);
    expect($pointBuilding->end)->toBe('2026-05-10 09:30:00');
    expect((float) $polygonBuilding->latitude)->toBe(31.5);
    expect((float) $polygonBuilding->longitude)->toBe(34.5);
});
