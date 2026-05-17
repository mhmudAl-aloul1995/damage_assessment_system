<?php

use App\Models\User;
use App\Services\DamageAssessment\Reports\phcPdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('filters')->insert([
        ['list_name' => 'governorate', 'name' => 'Gaza', 'label' => 'Gaza from database'],
        ['list_name' => 'governorate', 'name' => 'Middle_Area', 'label' => 'Middle Area from database'],
        ['list_name' => 'governorate', 'name' => 'Khan_Younis', 'label' => 'Khan Younis from database'],
    ]);
});

it('builds the phc pdf report data from current assessment tables', function () {
    DB::table('buildings')->insert([
        'objectid' => 1001,
        'globalid' => 'building-gaza-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Daraj',
        'building_damage_status' => 'partially_damaged',
        'building_type' => 'building',
        'building_use' => 'residential',
        'latitude' => 31.51,
        'longitude' => 34.46,
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 2001,
        'globalid' => 'unit-gaza-1',
        'parentglobalid' => 'building-gaza-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Al-Daraj',
        'unit_damage_status' => 'fully_damaged2',
        'occupied' => 'yes',
    ]);

    $data = app(phcPdfReportService::class)->build(Request::create('/damage-assessment/reports/phc'));

    expect($data['totalPages'])->toBe(14)
        ->and($data['totals']['buildings'])->toBe(1)
        ->and($data['totals']['housing_units'])->toBe(1)
        ->and($data['totals']['assessed_housing_units'])->toBe(1)
        ->and($data['governorates'])->toHaveCount(1)
        ->and($data['governorates'][0]['name'])->toBe('Gaza from database')
        ->and($data['neighborhoodPages'])->toHaveCount(1)
        ->and($data['gazaMapSvg'])->toContain('<svg')
        ->and($data['gazaMapSvg'])->not->toContain('North from database')
        ->and($data['gazaMapSvg'])->not->toContain('Rafah from database');
});

it('uses the database governorate options when grouping phc report pages', function () {
    DB::table('filters')->insert([
        'list_name' => 'governorate',
        'name' => 'North',
        'label' => 'North from database',
    ]);

    DB::table('buildings')->insert([
        'objectid' => 1002,
        'globalid' => 'building-north-gaza-1',
        'governorate' => 'North Gaza',
        'municipalitie' => 'North',
        'neighborhood' => 'Jabalia',
        'building_damage_status' => 'partially_damaged',
    ]);

    $data = app(phcPdfReportService::class)->build(Request::create('/damage-assessment/reports/phc'));
    $northGovernorate = collect($data['governorates'])->firstWhere('english_name', 'North');

    expect($northGovernorate['name'])->toBe('North from database')
        ->and($northGovernorate['totals']['buildings'])->toBe(1);
});

it('renders the phc report page with an export pdf action', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('damage-assessment.reports.phc'))
        ->assertOk()
        ->assertSee('Export PDF', false)
        ->assertSee(route('damage-assessment.reports.phc.export'), false);
});
