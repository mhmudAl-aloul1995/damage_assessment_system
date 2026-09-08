<?php

use App\Exports\AreaProductivityExport;
use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use App\Modules\DamageAssessment\Services\Reports\AreaProductivityReportService;
use App\Support\CsoDamageStatusMapper;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    ensureAuditedAreaProductivityColumns();
    app(RolesAndPermissionsSeeder::class)->run();
});

function ensureAuditedAreaProductivityColumns(): void
{
    foreach (['audited_buildings', 'audited_housing_units'] as $tableName) {
        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            foreach (['assignedto', 'municipalitie', 'zone_code', 'creationdate', 'end'] as $columnName) {
                if (! Schema::hasColumn($tableName, $columnName)) {
                    $table->text($columnName)->nullable();
                }
            }
        });
    }
}

it('renders empty area productivity tables without tbody colspan rows', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    $response = $this->actingAs($user)
        ->get(route('reports.area-productivity.buildings'))
        ->assertOk();

    expect($response->getContent())
        ->toContain('https:\/\/cdn.datatables.net\/plug-ins\/1.13.4\/i18n\/')
        ->not->toContain('<tbody>
                                    <tr>
                                        <td colspan=');
});

it('counts archived technical committee buildings in area productivity reports for historical date filters', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    AuditedBuilding::query()->create([
        'objectid' => 7101,
        'globalid' => 'historical-committee-building',
        'building_name' => 'Historical Committee Building',
        'assignedto' => 'eng-archive',
        'building_damage_status' => 'fully_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-Archive',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-08-15 10:00:00',
        'end' => '2026-08-15 10:00:00',
    ]);

    BuildingSurveyArchiveObject::query()->create([
        'building_objectid' => 7101,
        'building_globalid' => 'historical-committee-building',
        'source_type' => 'committee_decision',
        'archived_by' => $user->id,
        'archived_at' => '2026-04-20 12:00:00',
        'building_snapshot' => [
            'objectid' => 7101,
            'globalid' => 'historical-committee-building',
            'assignedto' => 'eng-archive',
            'building_damage_status' => 'committee_review',
            'governorate' => 'Gaza',
            'municipalitie' => 'Gaza',
            'neighborhood' => 'Rimal',
            'zone_code' => 'Z-Archive',
            'end' => '2026-04-20 10:00:00',
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('reports.area-productivity.buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))
        ->assertOk();

    $response->assertViewHas('summary', function (array $summary): bool {
        return $summary['total_records'] === 1
            && $summary['cra'] === 1
            && $summary['tda'] === 0;
    });

    $response->assertViewHas('rows', function ($rows): bool {
        $rimal = $rows->firstWhere('neighborhood', 'Rimal');

        return $rimal !== null
            && (int) $rimal->total_count === 1
            && (int) $rimal->cra_range === 1
            && (int) $rimal->tda_range === 0;
    });

    $exportRows = app(AreaProductivityReportService::class)->exportRows(AreaProductivityReportService::TYPE_BUILDINGS, [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
    ]);

    expect((int) $exportRows->firstWhere('neighborhood', 'Rimal')->cra_range)->toBe(1);
});

it('counts archived technical committee housing units in area productivity reports for historical date filters', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    AuditedBuilding::query()->create([
        'objectid' => 7201,
        'globalid' => 'historical-committee-unit-building',
        'building_name' => 'Historical Committee Unit Building',
        'assignedto' => 'eng-unit-archive',
        'building_damage_status' => 'partially_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-Unit-Archive',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-08-15 10:00:00',
        'end' => '2026-08-15 10:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 7202,
        'globalid' => 'historical-committee-unit',
        'parentglobalid' => 'historical-committee-unit-building',
        'unit_damage_status' => 'partially_damaged2',
        'building_submit_date' => '2026-08-15 12:00:00',
        'creationdate' => '2026-08-15 12:00:00',
    ]);

    BuildingSurveyArchiveObject::query()->create([
        'building_objectid' => 7201,
        'building_globalid' => 'historical-committee-unit-building',
        'housing_unit_objectid' => 7202,
        'housing_unit_globalid' => 'historical-committee-unit',
        'source_type' => 'temporary_committee_excel_archive',
        'archived_by' => $user->id,
        'archived_at' => '2026-04-21 12:00:00',
        'building_snapshot' => [
            'objectid' => 7201,
            'globalid' => 'historical-committee-unit-building',
            'assignedto' => 'eng-unit-archive',
            'governorate' => 'Gaza',
            'municipalitie' => 'Gaza',
            'neighborhood' => 'Rimal',
            'zone_code' => 'Z-Unit-Archive',
        ],
        'housing_unit_snapshot' => [
            'objectid' => 7202,
            'globalid' => 'historical-committee-unit',
            'unit_damage_status' => 'committee_review2',
            'building_submit_date' => '2026-04-21 10:00:00',
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('reports.area-productivity.housing-units', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))
        ->assertOk();

    $response->assertViewHas('summary', function (array $summary): bool {
        return $summary['total_records'] === 1
            && $summary['cra'] === 1
            && $summary['pda'] === 0;
    });

    $response->assertViewHas('rows', function ($rows): bool {
        $rimal = $rows->firstWhere('neighborhood', 'Rimal');

        return $rimal !== null
            && (int) $rimal->total_count === 1
            && (int) $rimal->cra_range === 1
            && (int) $rimal->pda_range === 0;
    });

    $exportRows = app(AreaProductivityReportService::class)->exportRows(AreaProductivityReportService::TYPE_HOUSING_UNITS, [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
    ]);

    expect((int) $exportRows->firstWhere('neighborhood', 'Rimal')->cra_range)->toBe(1);
});

it('maps cso damage status values into shared report buckets', function (): void {
    expect(CsoDamageStatusMapper::bucket('1'))->toBe(CsoDamageStatusMapper::FULLY_DAMAGED)
        ->and(CsoDamageStatusMapper::bucket('fully_damaged2'))->toBe(CsoDamageStatusMapper::FULLY_DAMAGED)
        ->and(CsoDamageStatusMapper::bucket('2'))->toBe(CsoDamageStatusMapper::PARTIALLY_DAMAGED)
        ->and(CsoDamageStatusMapper::bucket('partially_damaged2'))->toBe(CsoDamageStatusMapper::PARTIALLY_DAMAGED)
        ->and(CsoDamageStatusMapper::bucket('no_damaged'))->toBe(CsoDamageStatusMapper::NO_DAMAGE)
        ->and(CsoDamageStatusMapper::bucket('3'))->toBe(CsoDamageStatusMapper::COMMITTEE_REVIEW)
        ->and(CsoDamageStatusMapper::bucket('committee_review'))->toBe(CsoDamageStatusMapper::COMMITTEE_REVIEW)
        ->and(CsoDamageStatusMapper::bucket('unexpected'))->toBe(CsoDamageStatusMapper::UNCLASSIFIED)
        ->and(CsoDamageStatusMapper::bucket(null))->toBe(CsoDamageStatusMapper::UNCLASSIFIED);
});

it('renders cso area productivity with organizations and units tabs using shared damage buckets', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    CsoSurvey::query()->create([
        'objectid' => 8101,
        'globalid' => 'cso-survey-total',
        'field_status' => 'COMPLETED',
        'assignedto' => 'cso-eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'building_name' => 'CSO Building A',
        'organization_name' => 'Hope Association',
        'building_damage_status' => '1',
        'operational_status' => 'operational',
        'creationdate' => '2026-09-02 10:00:00',
        'created_at' => '2026-09-02 10:00:00',
        'updated_at' => '2026-09-02 10:00:00',
    ]);

    CsoSurvey::query()->create([
        'objectid' => 8102,
        'globalid' => 'cso-survey-committee',
        'field_status' => 'COMPLETED',
        'assignedto' => 'cso-eng-2',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'building_name' => 'CSO Building B',
        'organization_name' => 'Relief Society',
        'building_damage_status' => 'committee_review',
        'operational_status' => 'partial',
        'creationdate' => '2026-09-03 10:00:00',
        'created_at' => '2026-09-03 10:00:00',
        'updated_at' => '2026-09-03 10:00:00',
    ]);

    CsoSurvey::query()->create([
        'objectid' => 8103,
        'globalid' => 'cso-survey-outside-filter',
        'field_status' => 'COMPLETED',
        'assignedto' => 'cso-eng-3',
        'governorate' => 'North Gaza',
        'municipalitie' => 'Jabalia',
        'neighborhood' => 'Camp',
        'building_damage_status' => '2',
        'creationdate' => '2026-09-03 10:00:00',
        'created_at' => '2026-09-03 10:00:00',
        'updated_at' => '2026-09-03 10:00:00',
    ]);

    CsoSurveyOrganization::query()->create([
        'objectid' => 8201,
        'globalid' => 'cso-org-total',
        'parentglobalid' => 'cso-survey-total',
        'organization_name_ar' => 'جمعية الأمل',
        'organization_name_en' => 'Hope Association',
        'organization_acronym' => 'HOPE',
        'operational_status' => 'operational',
        'creationdate' => '2026-09-02 11:00:00',
        'created_at' => '2026-09-02 11:00:00',
        'updated_at' => '2026-09-02 11:00:00',
    ]);

    CsoSurveyOrganization::query()->create([
        'objectid' => 8202,
        'globalid' => 'cso-org-committee',
        'parentglobalid' => 'cso-survey-committee',
        'organization_name_ar' => 'جمعية الإغاثة',
        'organization_name_en' => 'Relief Society',
        'organization_acronym' => 'REL',
        'operational_status' => 'partial',
        'creationdate' => '2026-09-03 11:00:00',
        'created_at' => '2026-09-03 11:00:00',
        'updated_at' => '2026-09-03 11:00:00',
    ]);

    CsoSurveyUnit::query()->create([
        'objectid' => 8301,
        'globalid' => 'cso-unit-total',
        'parentglobalid' => 'cso-survey-total',
        'unit_name' => 'Clinic',
        'unit_number' => 1,
        'unit_floor_number' => 0,
        'unit_damage_status' => 'fully_damaged2',
        'creationdate' => '2026-09-02 12:00:00',
        'created_at' => '2026-09-02 12:00:00',
        'updated_at' => '2026-09-02 12:00:00',
    ]);

    CsoSurveyUnit::query()->create([
        'objectid' => 8302,
        'globalid' => 'cso-unit-committee',
        'parentglobalid' => 'cso-survey-committee',
        'unit_name' => 'Office',
        'unit_number' => 2,
        'unit_floor_number' => 1,
        'unit_damage_status' => '3',
        'creationdate' => '2026-09-03 12:00:00',
        'created_at' => '2026-09-03 12:00:00',
        'updated_at' => '2026-09-03 12:00:00',
    ]);

    $response = $this->actingAs($user)
        ->get(route('reports.area-productivity.cso-surveys', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-08',
            'municipalitie' => 'Gaza',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.cso_surveys'), false)
        ->assertSee('area-productivity-organizations-tab', false)
        ->assertSee('area-productivity-units-tab', false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.organizations_count'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.cso_units_count'), false)
        ->assertSee('جمعية الأمل', false)
        ->assertSee('Clinic', false);

    $response->assertViewHas('summary', function (array $summary): bool {
        return $summary['total_records'] === 2
            && $summary['organizations_count'] === 2
            && $summary['cso_units_count'] === 2
            && $summary['tda'] === 1
            && $summary['cra'] === 1
            && $summary['pda'] === 0
            && $summary['no_damage'] === 0
            && $summary['unclassified'] === 0;
    });

    $response->assertViewHas('cso', function (array $cso): bool {
        return $cso['organizations']->count() === 2
            && $cso['organization_summary']['tda'] === 1
            && $cso['organization_summary']['cra'] === 1
            && $cso['units']->count() === 2
            && $cso['unit_summary']['tda'] === 1
            && $cso['unit_summary']['cra'] === 1;
    });

    $this->actingAs($user)
        ->getJson(route('reports.area-productivity.cso-surveys.data', [
            'municipalitie' => 'Gaza',
        ]))
        ->assertOk()
        ->assertJsonPath('summary.total_records', 2)
        ->assertJsonPath('summary.organizations_count', 2)
        ->assertJsonPath('summary.cso_units_count', 2)
        ->assertJsonPath('summary.cra', 1);

    $exportRows = app(AreaProductivityReportService::class)->exportRows(AreaProductivityReportService::TYPE_CSO_SURVEYS, [
        'municipalitie' => 'Gaza',
    ]);
    $export = new AreaProductivityExport(
        $exportRows,
        '',
        '',
        __('multilingual.area_productivity_reports.titles.cso_surveys'),
        __('multilingual.area_productivity_reports.sectors.cso_surveys'),
        AreaProductivityReportService::TYPE_CSO_SURVEYS,
    );
    $exportCollection = $export->collection();

    expect($export->map($exportCollection->firstWhere('neighborhood', 'Rimal')))->toBe([
        2,
        2,
        2,
        1,
        0,
        0,
        1,
        0,
        2,
        'Rimal',
        'Gaza',
        'Gaza',
        __('multilingual.area_productivity_reports.sectors.cso_surveys'),
    ]);
});

it('renders separated area productivity reports for all supported datasets with filtering', function () {
    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    Building::query()->create([
        'objectid' => 9001,
        'globalid' => 'source-building-ignored',
        'building_name' => 'Source Building Ignored',
        'assignedto' => 'eng-1',
        'building_damage_status' => 'fully_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-1',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-10 10:00:00',
        'end' => '2026-04-10 10:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9002,
        'globalid' => 'source-housing-ignored',
        'parentglobalid' => 'source-building-ignored',
        'unit_damage_status' => 'fully_damaged2',
        'building_submit_date' => '2026-04-10 12:00:00',
        'creationdate' => '2026-04-10 12:00:00',
    ]);

    AuditedBuilding::query()->create([
        'objectid' => 1001,
        'globalid' => 'building-1',
        'building_name' => 'Building 1',
        'assignedto' => 'eng-1',
        'building_damage_status' => 'fully_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-1',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-10 10:00:00',
        'end' => '2026-04-10 10:00:00',
    ]);

    AuditedBuilding::query()->create([
        'objectid' => 1002,
        'globalid' => 'building-2',
        'building_name' => 'Building 2',
        'assignedto' => 'eng-1',
        'building_damage_status' => 'partially_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-9',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-11 10:00:00',
        'end' => '2026-04-11 10:00:00',
    ]);

    AuditedBuilding::query()->create([
        'objectid' => 1003,
        'globalid' => 'building-3',
        'building_name' => 'Building 3',
        'assignedto' => 'eng-2',
        'building_damage_status' => 'committee_review',
        'governorate' => 'North Gaza',
        'municipalitie' => 'Jabalia',
        'neighborhood' => 'Camp',
        'zone_code' => 'Z-2',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-11 10:00:00',
        'end' => '2026-04-11 10:00:00',
    ]);

    AuditedBuilding::query()->create([
        'objectid' => 1004,
        'globalid' => 'building-4',
        'building_name' => 'Building 4',
        'assignedto' => 'eng-3',
        'building_damage_status' => 'partially_damaged',
        'governorate' => null,
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-7',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-12 10:00:00',
        'end' => '2026-04-12 10:00:00',
    ]);

    AuditedBuilding::query()->create([
        'objectid' => 1005,
        'globalid' => 'building-unclassified',
        'building_name' => 'Building Unclassified',
        'assignedto' => 'eng-4',
        'building_damage_status' => null,
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'zone_code' => 'Z-8',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-03-13 10:00:00',
        'end' => '2026-04-13 10:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 2001,
        'globalid' => 'housing-1',
        'parentglobalid' => 'building-1',
        'unit_damage_status' => 'fully_damaged2',
        'building_submit_date' => '2026-04-10 12:00:00',
        'creationdate' => '2026-05-10 12:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 2002,
        'globalid' => 'housing-2',
        'parentglobalid' => 'building-1',
        'unit_damage_status' => 'partially_damaged2',
        'building_submit_date' => '2026-04-10 13:00:00',
        'creationdate' => '2026-04-10 13:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 2003,
        'globalid' => 'housing-3',
        'parentglobalid' => 'building-2',
        'unit_damage_status' => 'committee_review2',
        'building_submit_date' => '2026-04-11 13:00:00',
        'creationdate' => '2026-04-11 13:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 2004,
        'globalid' => 'housing-no-damage',
        'parentglobalid' => 'building-2',
        'unit_damage_status' => 'no_damaged',
        'building_submit_date' => '2026-04-11 14:00:00',
        'creationdate' => '2026-04-11 14:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 2007,
        'globalid' => 'housing-unclassified',
        'parentglobalid' => 'building-2',
        'unit_damage_status' => null,
        'building_submit_date' => '2026-04-11 15:00:00',
        'creationdate' => '2026-04-11 15:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 2005,
        'globalid' => 'housing-for-unclassified-building',
        'parentglobalid' => 'building-unclassified',
        'unit_damage_status' => 'partially_damaged2',
        'building_submit_date' => '2026-04-13 14:00:00',
        'creationdate' => '2026-04-13 14:00:00',
    ]);

    AuditedHousingUnit::query()->create([
        'objectid' => 2006,
        'globalid' => 'housing-outside-report-range',
        'parentglobalid' => 'building-1',
        'unit_damage_status' => 'fully_damaged2',
        'building_submit_date' => '2026-05-01 14:00:00',
        'creationdate' => '2026-04-10 14:00:00',
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 3001,
        'building_name' => 'School A',
        'assignedto' => 'eng-1',
        'building_damage_status' => 'fully_damaged',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'creationdate' => '2026-04-10 09:00:00',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 3002,
        'building_name' => 'School B',
        'assignedto' => 'eng-2',
        'building_damage_status' => 'committee_review',
        'governorate' => 'North Gaza',
        'municipalitie' => 'Jabalia',
        'neighborhood' => 'Camp',
        'creationdate' => '2026-04-10 09:00:00',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4001,
        'str_name' => 'Street A',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'destroyed',
        'field_status' => 'COMPLETED',
        'shape__length' => 0.01,
        'Lenght_Km_2' => 10,
        'zone_code' => 'RZ-1',
        'creationdate' => '2026-04-10 09:00:00',
        'created_at' => '2026-04-10 09:00:00',
        'updated_at' => '2026-04-10 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4002,
        'str_name' => 'Street B',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'moderate',
        'field_status' => 'COMPLETED',
        'shape__length' => 0.02,
        'Lenght_Km_2' => 20,
        'zone_code' => 'RZ-2',
        'creationdate' => '2026-04-11 09:00:00',
        'created_at' => '2026-04-11 09:00:00',
        'updated_at' => '2026-04-11 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4003,
        'str_name' => 'Street C',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'severe',
        'field_status' => 'COMPLETED',
        'shape__length' => 0.03,
        'Lenght_Km_2' => 30,
        'zone_code' => 'RZ-3',
        'creationdate' => '2026-04-12 09:00:00',
        'created_at' => '2026-04-12 09:00:00',
        'updated_at' => '2026-04-12 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4004,
        'str_name' => 'Street D',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'minor',
        'field_status' => 'COMPLETED',
        'shape__length' => 0.04,
        'Lenght_Km_2' => 40,
        'zone_code' => 'RZ-4',
        'creationdate' => '2026-04-13 09:00:00',
        'created_at' => '2026-04-13 09:00:00',
        'updated_at' => '2026-04-13 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4005,
        'str_name' => 'Street E',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'No_Damage',
        'field_status' => 'COMPLETED',
        'shape__length' => 0.05,
        'Lenght_Km_2' => 50,
        'zone_code' => 'RZ-5',
        'creationdate' => '2026-04-14 09:00:00',
        'created_at' => '2026-04-14 09:00:00',
        'updated_at' => '2026-04-14 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4006,
        'str_name' => 'Street F',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'not_classified',
        'field_status' => 'COMPLETED',
        'shape__length' => 0.99,
        'Lenght_Km_2' => 296.683,
        'zone_code' => 'RZ-6',
        'creationdate' => '2026-04-15 09:00:00',
        'created_at' => '2026-04-15 09:00:00',
        'updated_at' => '2026-04-15 09:00:00',
    ]);

    RoadFacilitySurvey::query()->create([
        'objectid' => 4007,
        'str_name' => 'Street G Not Completed',
        'assignedto' => 'eng-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'road_damage_level' => 'destroyed',
        'field_status' => 'Not_Completed',
        'shape__length' => 0.77,
        'Lenght_Km_2' => 770,
        'zone_code' => 'RZ-7',
        'creationdate' => '2026-04-16 09:00:00',
        'created_at' => '2026-04-16 09:00:00',
        'updated_at' => '2026-04-16 09:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('reports.area-productivity.housing-units', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.housing_units'), false)
        ->assertSee('Location Pie Charts')
        ->assertSee('area-productivity-table-tab', false)
        ->assertSee('area-productivity-location-charts-tab', false)
        ->assertSee('area-productivity-location-charts-pane', false)
        ->assertSee('Municipality | 5 housing units')
        ->assertSee('Neighborhoods under Gaza')
        ->assertSee('Totally Damaged')
        ->assertSee('Partially Damaged')
        ->assertSee('Technical Committee')
        ->assertSee('Unclassified')
        ->assertSee('location-pie-section-toggle', false)
        ->assertSee('location-pie-card', false)
        ->assertSee('housing_units_municipality', false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('Grand Totals', false)
        ->assertSee('3', false)
        ->assertSee('1', false)
        ->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_records'] === 5
                && $summary['tda'] === 1
                && $summary['pda'] === 1
                && $summary['cra'] === 1
                && $summary['no_damage'] === 1
                && $summary['unclassified'] === 1;
        })
        ->assertViewHas('charts', function (array $charts): bool {
            $municipalityNode = $charts['location_pies'][0] ?? null;

            return $municipalityNode !== null
                && $municipalityNode['pie']['title'] === 'Gaza'
                && $municipalityNode['pie']['series'] === [1, 1, 1, 1, 1]
                && $municipalityNode['pie']['labels'] === ['Totally Damaged', 'Partially Damaged', 'Technical Committee', 'No Damage', 'Unclassified']
                && $municipalityNode['pie']['colors'] === ['#F1416C', '#FFC700', '#E879F9', '#50CD89', '#7E8299']
                && array_column($municipalityNode['pie']['summary_items'], 'color') === ['#F1416C', '#FFC700', '#E879F9', '#50CD89', '#7E8299']
                && $municipalityNode['pie']['units_count'] === 5
                && count($municipalityNode['neighborhoods']) === 1
                && $municipalityNode['neighborhoods'][0]['title'] === 'Rimal';
        });

    $buildingResponse = $this->actingAs($user)
        ->get(route('reports.area-productivity.buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'municipalitie' => 'Gaza',
        ]));

    $buildingResponse
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.buildings'), false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('3', false)
        ->assertSee('<th>'.__('multilingual.area_productivity_reports.columns.housing_units_count').'</th>', false)
        ->assertDontSee('<td>Camp</td>', false)
        ->assertSee('Grand Totals', false)
        ->assertSee(__('multilingual.area_productivity_reports.sectors.buildings'), false)
        ->assertSeeInOrder(['<td>Gaza</td>', '<td>Buildings</td>'], false);

    $buildingResponse->assertViewHas('summary', function (array $summary): bool {
        return $summary['total_records'] === 4
            && $summary['tda'] === 1
            && $summary['pda'] === 2
            && $summary['cra'] === 0
            && $summary['unclassified'] === 1
            && $summary['housing_units_count'] === 6;
    });

    $buildingResponse->assertViewHas('rows', function ($rows): bool {
        $rimal = $rows->firstWhere('neighborhood', 'Rimal');

        return $rimal !== null
            && (int) $rimal->total_count === 4
            && (int) $rimal->tda_range === 1
            && (int) $rimal->pda_range === 2
            && (int) $rimal->cra_range === 0
            && (int) $rimal->unclassified_count === 1
            && (int) $rimal->housing_units_count === 6;
    });

    $this->actingAs($user)
        ->get(route('reports.area-productivity.housing-units'))
        ->assertOk()
        ->assertSee('name="start_date" id="start_date" value=""', false)
        ->assertSee('name="end_date" id="end_date" value=""', false)
        ->assertSee('id="kt_daterangepicker" autocomplete="off"', false)
        ->assertSee('const useAjaxFilters = true;', false)
        ->assertSee("mode: 'range'", false)
        ->assertSee('fixedHeader: {', false)
        ->assertSee('onReady: function (selectedDates, dateStr, instance)', false)
        ->assertSee('defaultDate: [startDateInput.value, endDateInput.value].filter(Boolean)', false)
        ->assertSee(__('multilingual.area_productivity_reports.actions.auto_filter'), false)
        ->assertDontSee('type="submit" class="btn btn-primary"', false)
        ->assertViewHas('start_date', '')
        ->assertViewHas('end_date', '');

    $this->actingAs($user)
        ->get(route('reports.area-productivity.buildings'))
        ->assertOk()
        ->assertSee('name="start_date" id="start_date" value=""', false)
        ->assertSee('name="end_date" id="end_date" value=""', false)
        ->assertSee('id="kt_daterangepicker" autocomplete="off"', false)
        ->assertSee('const useAjaxFilters = true;', false)
        ->assertSee("mode: 'range'", false)
        ->assertSee('fixedHeader: {', false)
        ->assertSee('onReady: function (selectedDates, dateStr, instance)', false)
        ->assertSee(__('multilingual.area_productivity_reports.actions.auto_filter'), false)
        ->assertDontSee('type="submit" class="btn btn-primary"', false)
        ->assertViewHas('start_date', '')
        ->assertViewHas('end_date', '');

    $this->actingAs($user)
        ->get(route('reports.area-productivity.buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'municipalitie' => ['Gaza', 'Jabalia'],
            'assignedto' => ['eng-1', 'eng-2'],
        ]))
        ->assertOk()
        ->assertViewHas('rows', function ($rows): bool {
            return $rows->pluck('neighborhood')->sort()->values()->all() === ['Camp', 'Rimal']
                && (int) $rows->sum('total_count') === 3;
        });

    $this->actingAs($user)
        ->getJson(route('reports.area-productivity.housing-units.data', [
            'start_date' => '',
            'end_date' => '',
        ]))
        ->assertOk()
        ->assertJsonPath('summary.total_records', 7)
        ->assertJsonPath('summary.no_damage', 1)
        ->assertJsonPath('summary.unclassified', 1)
        ->assertJsonPath('start_date', '')
        ->assertJsonPath('end_date', '');

    $this->actingAs($user)
        ->getJson(route('reports.area-productivity.public-buildings.data', [
            'start_date' => '',
            'end_date' => '',
        ]))
        ->assertOk()
        ->assertJsonPath('summary.total_records', 2)
        ->assertJsonPath('start_date', '')
        ->assertJsonPath('end_date', '');

    $this->actingAs($user)
        ->getJson(route('reports.area-productivity.road-facilities.data', [
            'start_date' => '',
            'end_date' => '',
        ]))
        ->assertOk()
        ->assertJsonPath('summary.total_records', 6)
        ->assertJsonPath('summary.unclassified', 1)
        ->assertJsonPath('summary.total_road_length_km', 446.683)
        ->assertJsonPath('start_date', '')
        ->assertJsonPath('end_date', '');

    $this->actingAs($user)
        ->getJson(route('reports.area-productivity.buildings.data', [
            'start_date' => '',
            'end_date' => '',
        ]))
        ->assertOk()
        ->assertJsonPath('summary.total_records', 5)
        ->assertJsonPath('summary.unclassified', 1)
        ->assertJsonPath('start_date', '')
        ->assertJsonPath('end_date', '');

    $publicBuildingsResponse = $this->actingAs($user)
        ->get(route('reports.area-productivity.public-buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]));

    $publicBuildingsResponse
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.public_buildings'), false)
        ->assertSee('Location Pie Charts')
        ->assertSee('Municipality | 1 public buildings')
        ->assertSee('public_buildings_municipality', false)
        ->assertDontSee('public_buildings_neighborhood', false)
        ->assertDontSee('Neighborhoods under Gaza')
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('1', false)
        ->assertDontSee('<td>Camp</td>', false)
        ->assertSee('Grand Totals', false);

    $publicBuildingsResponse->assertViewHas('charts', function (array $charts): bool {
        $municipalityNode = $charts['location_pies'][0] ?? null;

        return $municipalityNode !== null
            && $municipalityNode['pie']['title'] === 'Gaza'
            && $municipalityNode['pie']['series'] === [1, 0]
            && $municipalityNode['pie']['items_count'] === 1
            && count($municipalityNode['neighborhoods']) === 0;
    });

    $roadFacilitiesResponse = $this->actingAs($user)
        ->get(route('reports.area-productivity.road-facilities', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'assignedto' => 'eng-1',
        ]));

    $roadFacilitiesResponse
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.road_facilities'), false)
        ->assertSee('Location Pie Charts')
        ->assertSee('Municipality | 6 road facilities')
        ->assertSee('road_facilities_municipality', false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.destroyed'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.severe'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.moderate'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.minor'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.no_damage'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.unclassified'), false)
        ->assertSee(__('multilingual.area_productivity_reports.columns.total_road_length'), false)
        ->assertDontSee(__('multilingual.area_productivity_reports.columns.cra'), false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('446.683', false)
        ->assertSee('6', false)
        ->assertSee('Grand Totals', false)
        ->assertSee(__('multilingual.area_productivity_reports.sectors.road_facilities'), false);

    $roadFacilitiesResponse->assertViewHas('summary', function (array $summary): bool {
        return $summary['total_records'] === 6
            && $summary['unclassified'] === 1
            && (float) $summary['total_road_length_km'] === 446.683;
    });

    $roadFacilitiesResponse->assertViewHas('rows', function ($rows): bool {
        $rimal = $rows->firstWhere('neighborhood', 'Rimal');

        return $rimal !== null
            && (int) $rimal->total_count === 6
            && (int) $rimal->destroyed_count === 1
            && (int) $rimal->severe_count === 1
            && (int) $rimal->moderate_count === 1
            && (int) $rimal->minor_count === 1
            && (int) $rimal->no_damage_count === 1
            && (int) $rimal->unclassified_count === 1
            && (float) $rimal->total_road_length_km === 446.683;
    });

    $roadFacilitiesResponse->assertViewHas('charts', function (array $charts): bool {
        $municipalityNode = $charts['location_pies'][0] ?? null;

        return $municipalityNode !== null
            && $municipalityNode['pie']['title'] === 'Gaza'
            && $municipalityNode['pie']['series'] === [1, 1, 1, 1, 1, 1]
            && $municipalityNode['pie']['labels'] === ['Destroyed', 'Severe', 'Moderate', 'Minor', 'No Damage', 'Unclassified']
            && $municipalityNode['pie']['colors'] === ['#F1416C', '#E879F9', '#FFC700', '#009EF7', '#50CD89', '#7E8299']
            && array_column($municipalityNode['pie']['summary_items'], 'color') === ['#F1416C', '#E879F9', '#FFC700', '#009EF7', '#50CD89', '#7E8299']
            && $municipalityNode['pie']['items_count'] === 6
            && count($municipalityNode['neighborhoods']) === 1;
    });

    $this->actingAs($user)
        ->get(route('reports.area-productivity.road-facilities', [
            'assignedto' => 'eng-1',
        ]))
        ->assertOk()
        ->assertSee(__('multilingual.area_productivity_reports.titles.road_facilities').':', false)
        ->assertSee('All')
        ->assertViewHas('date_range_label', 'All');

    $this->actingAs($user)
        ->get(route('reports.area-productivity.export.buildings', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('reports.area-productivity.export.road-facilities', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))
        ->assertOk();

    $reportService = app(AreaProductivityReportService::class);
    $exportRows = $reportService->exportRows(AreaProductivityReportService::TYPE_ROAD_FACILITIES, [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
    ]);
    $export = new AreaProductivityExport(
        $exportRows,
        '2026-04-01',
        '2026-04-30',
        __('multilingual.area_productivity_reports.titles.road_facilities'),
        __('multilingual.area_productivity_reports.sectors.road_facilities'),
        AreaProductivityReportService::TYPE_ROAD_FACILITIES,
    );
    $exportCollection = $export->collection();

    expect($export->map($exportCollection->firstWhere('neighborhood', 'Rimal')))->toBe([
        6,
        446.683,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        'Rimal',
        'Gaza',
        'Gaza',
        __('multilingual.area_productivity_reports.sectors.road_facilities'),
    ]);
    expect($export->map($exportCollection->last()))->toBe([
        6,
        446.683,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        '',
        '',
        '',
        __('multilingual.area_productivity_reports.labels.grand_totals'),
    ]);
});
