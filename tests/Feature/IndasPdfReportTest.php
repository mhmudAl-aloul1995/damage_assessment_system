<?php

use App\Models\User;
use App\Services\DamageAssessment\Reports\IndasPdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

it('builds the indas pdf report data from current assessment tables', function () {
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

    $data = app(IndasPdfReportService::class)->build(Request::create('/damage-assessment/reports/indas'));

    expect($data['totalPages'])->toBe(14)
        ->and($data['totals']['buildings'])->toBe(1)
        ->and($data['totals']['housing_units'])->toBe(1)
        ->and($data['totals']['assessed_housing_units'])->toBe(1)
        ->and($data['governorates'])->toHaveCount(5)
        ->and($data['neighborhoodPages'])->toHaveCount(5)
        ->and($data['gazaMapSvg'])->toContain('<svg');
});

it('renders the indas report page with an export pdf action', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('damage-assessment.reports.indas'))
        ->assertOk()
        ->assertSee('Export PDF', false)
        ->assertSee(route('damage-assessment.reports.indas.export'), false);
});
