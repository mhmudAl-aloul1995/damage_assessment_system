<?php

declare(strict_types=1);

use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\services\CsoSurveyImporter;

test('it imports cso survey payload with searchable fields and raw payload', function (): void {
    $payload = [
        'objectid' => 7001,
        'globalid' => 'cso-global-7001',
        'field_status' => 'Completed',
        'assignedto' => 'arcgis.engineer',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza Municipality',
        'neighborhood' => 'Al-Rimal',
        'building_name' => 'CSO Main Building',
        'organization_name_en' => 'Civil Support Org',
        'building_damage_status' => 'partial_damage',
        'operational_status' => 'partially_operational',
        'weather' => 'sunny',
        'CSO_Organizations' => [
            [
                'objectid' => 8001,
                'globalid' => 'cso-org-8001',
                'organization_name_en' => 'Civil Support Org',
                'operational_status' => 'operational',
            ],
        ],
        'Unit_Information' => [
            [
                'objectid' => 9001,
                'globalid' => 'cso-unit-9001',
                'unit_name' => 'First floor unit',
                'unit_damage_status' => 'minor_damage',
            ],
        ],
    ];

    $survey = app(CsoSurveyImporter::class)->import($payload);

    expect($survey)->toBeInstanceOf(CsoSurvey::class)
        ->and($survey->objectid)->toBe(7001)
        ->and($survey->organization_name)->toBe('Civil Support Org')
        ->and($survey->raw_payload['weather'])->toBe('sunny')
        ->and($survey->organizations)->toHaveCount(1)
        ->and($survey->units)->toHaveCount(1)
        ->and($survey->organizations->first()?->parentglobalid)->toBe('cso-global-7001')
        ->and($survey->units->first()?->parentglobalid)->toBe('cso-global-7001');

    app(CsoSurveyImporter::class)->import([
        ...$payload,
        'organization_name_en' => 'Civil Support Org Updated',
    ]);

    expect(CsoSurvey::query()->count())->toBe(1)
        ->and(CsoSurvey::query()->first()?->organization_name)->toBe('Civil Support Org Updated')
        ->and(CsoSurveyOrganization::query()->count())->toBe(1)
        ->and(CsoSurveyUnit::query()->count())->toBe(1);
});
