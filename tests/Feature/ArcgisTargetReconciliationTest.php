<?php

use App\Services\ArcgisAuditedUploadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('services.arcgis.username', 'tester');
    config()->set('services.arcgis.password', 'secret');
    config()->set('services.arcgis.referer', 'http://localhost');
    config()->set('services.arcgis.source_service', 'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer');
    config()->set('services.arcgis.source_buildings_layer', 10);
    config()->set('services.arcgis.source_units_layer', 11);
    config()->set('services.arcgis.target_service', 'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer');
    config()->set('services.arcgis.target_buildings_layer', 0);
    config()->set('services.arcgis.target_units_layer', 1);
});

it('prepares long text and non numeric values before uploading target features', function (): void {
    foreach (['final_comments', 'final_comments_v1', 'co5', 'quant1', 'damaged_area_m2'] as $column) {
        if (! Schema::hasColumn('audited_housing_units', $column)) {
            Schema::table('audited_housing_units', function (Blueprint $table) use ($column): void {
                $table->text($column)->nullable();
            });
        }
    }

    DB::table('audited_housing_units')->insert([
        'objectid' => 12188,
        'globalid' => 'unit-globalid',
        'final_comments' => str_repeat('أ', 300),
        'co5' => '0.36.',
        'quant1' => '24 متر مربع',
        'damaged_area_m2' => 'شمالي',
    ]);

    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1?*' => Http::response([
            'objectIdField' => 'objectid',
            'fields' => [
                ['name' => 'objectid', 'type' => 'esriFieldTypeOID'],
                ['name' => 'old_objectid_U', 'type' => 'esriFieldTypeInteger'],
                ['name' => 'old_global_id_U', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'globalid', 'type' => 'esriFieldTypeGlobalID', 'length' => 38],
                ['name' => 'final_comments', 'type' => 'esriFieldTypeString', 'length' => 255],
                ['name' => 'final_comments_v1', 'type' => 'esriFieldTypeString', 'length' => 2000],
                ['name' => 'CO5', 'type' => 'esriFieldTypeDouble'],
                ['name' => 'quant1', 'type' => 'esriFieldTypeDouble'],
                ['name' => 'Damaged_Area_m2', 'type' => 'esriFieldTypeDouble'],
                ['name' => 'is_audited', 'type' => 'esriFieldTypeInteger'],
            ],
        ]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/11/query*' => Http::response(['features' => []]),
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/1/addFeatures' => function ($request) {
            $feature = json_decode($request['features'], true)[0];
            $attributes = $feature['attributes'];

            expect(mb_strlen($attributes['final_comments']))->toBe(255)
                ->and($attributes['final_comments_v1'])->toBe(str_repeat('أ', 300))
                ->and($attributes['CO5'])->toBe(0.36)
                ->and($attributes['quant1'])->toBe(24)
                ->and($attributes['Damaged_Area_m2'])->toBeNull();

            return Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 71093],
                ],
            ]);
        },
    ]);

    $summary = app(ArcgisAuditedUploadService::class)->uploadObjectIds(
        unitObjectIds: [12188],
        withoutAttachments: true,
    );

    expect($summary['units_uploaded'])->toBe(1)
        ->and($summary['errors'])->toBe(0);
});

it('reports source target differences without applying changes by default', function (): void {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response(['token' => 'arcgis-token']),
        'https://services.example.test/ArcGIS/rest/services/SOURCE/FeatureServer/10/query*' => function ($request) {
            if ((int) ($request['resultOffset'] ?? 0) > 0) {
                return Http::response(['features' => []]);
            }

            return Http::response([
                'features' => [
                    ['attributes' => ['objectid' => 1]],
                    ['attributes' => ['objectid' => 2]],
                ],
            ]);
        },
        'https://services.example.test/ArcGIS/rest/services/TARGET/FeatureServer/0/query*' => function ($request) {
            if ((int) ($request['resultOffset'] ?? 0) > 0) {
                return Http::response(['features' => []]);
            }

            return Http::response([
                'features' => [
                    ['attributes' => ['objectid' => 10, 'old_objectid_B' => 1]],
                    ['attributes' => ['objectid' => 11, 'old_objectid_B' => 3]],
                ],
            ]);
        },
    ]);

    $exitCode = Artisan::call('arcgis:reconcile-target', [
        '--only' => 'buildings',
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('missing');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'addFeatures')
        || str_contains($request->url(), 'deleteFeatures'));
});
