<?php

declare(strict_types=1);

use App\Models\CsoSurvey;
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
    ];

    $survey = app(CsoSurveyImporter::class)->import($payload);

    expect($survey)->toBeInstanceOf(CsoSurvey::class)
        ->and($survey->objectid)->toBe(7001)
        ->and($survey->organization_name)->toBe('Civil Support Org')
        ->and($survey->raw_payload['weather'])->toBe('sunny');

    app(CsoSurveyImporter::class)->import([
        ...$payload,
        'organization_name_en' => 'Civil Support Org Updated',
    ]);

    expect(CsoSurvey::query()->count())->toBe(1)
        ->and(CsoSurvey::query()->first()?->organization_name)->toBe('Civil Support Org Updated');
});
