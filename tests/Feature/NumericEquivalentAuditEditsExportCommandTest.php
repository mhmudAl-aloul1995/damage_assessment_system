<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports audit edits that differ as text but match as numbers with later edit status', function (): void {
    Storage::disk('local')->delete('testing/numeric-equivalent-audit-edits.xlsx');

    DB::table('buildings')->insert([
        'objectid' => 1001,
        'globalid' => 'building-numeric',
        'building_damage_status' => '130.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('housing_units')->insert([
        'objectid' => 2001,
        'globalid' => 'housing-numeric',
        'parentglobalid' => 'building-numeric',
        'unit_damage_status' => '130',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('edit_assessments')->insert([
        [
            'id' => 201,
            'global_id' => 'building-numeric',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => '130',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 202,
            'global_id' => 'building-numeric',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => '140',
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ],
        [
            'id' => 203,
            'global_id' => 'housing-numeric',
            'type' => 'housing_table',
            'field_name' => 'unit_damage_status',
            'field_value' => '0130.000',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 204,
            'global_id' => 'building-numeric',
            'type' => 'building_table',
            'field_name' => 'building_name',
            'field_value' => '130A',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->artisan('audit-edits:export-numeric-equivalent', [
        '--export' => 'testing/numeric-equivalent-audit-edits.xlsx',
        '--base-url' => 'http://213.6.135.115/damage_assessment_system',
    ])->assertSuccessful();

    expect(Storage::disk('local')->exists('testing/numeric-equivalent-audit-edits.xlsx'))->toBeTrue();

    $rows = Excel::toArray(null, Storage::disk('local')->path('testing/numeric-equivalent-audit-edits.xlsx'))[0];

    expect($rows[0])->toContain('Has Later Edit For Same Field');

    $buildingRow = collect($rows)->first(fn (array $row): bool => $row[1] === 201);
    $housingRow = collect($rows)->first(fn (array $row): bool => $row[1] === 203);

    expect($buildingRow[8])->toBe(130);
    expect($buildingRow[9])->toBe(130.00);
    expect($buildingRow[10])->toBe(130);
    expect($buildingRow[11])->toBe(130);
    expect($buildingRow[14])->toBe('Yes');
    expect($buildingRow[15])->toBe(202);
    expect($buildingRow[16])->toBe(140);
    expect($housingRow[14])->toBe('No');

    $spreadsheet = IOFactory::load(Storage::disk('local')->path('testing/numeric-equivalent-audit-edits.xlsx'));
    $sheet = $spreadsheet->getActiveSheet();

    expect($sheet->getCell('G2')->getHyperlink()->getUrl())->toBe((string) $buildingRow[6]);
});
