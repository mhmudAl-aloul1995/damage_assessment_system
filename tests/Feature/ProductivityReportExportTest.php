<?php

use App\Exports\ProductivityExport;
use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('exports productivity report using the same housing-unit date filter as the table', function () {
    Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole('Database Officer');

    Building::query()->create([
        'objectid' => 9901,
        'globalid' => 'productivity-building-may',
        'assignedto' => 'eng-may',
        'building_damage_status' => 'fully_damaged',
        'creationdate' => '2026-05-01 08:00:00',
    ]);

    Building::query()->create([
        'objectid' => 9902,
        'globalid' => 'productivity-building-only',
        'assignedto' => 'eng-building-only',
        'building_damage_status' => 'fully_damaged',
        'creationdate' => '2026-05-10 08:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9911,
        'globalid' => 'productivity-housing-fully',
        'parentglobalid' => 'productivity-building-may',
        'unit_damage_status' => 'fully_damaged2',
        'creationdate' => '2026-05-15 10:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9912,
        'globalid' => 'productivity-housing-partial',
        'parentglobalid' => 'productivity-building-may',
        'unit_damage_status' => 'partially_damaged2',
        'creationdate' => '2026-05-15 11:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9913,
        'globalid' => 'productivity-housing-outside-filter',
        'parentglobalid' => 'productivity-building-may',
        'unit_damage_status' => 'fully_damaged2',
        'creationdate' => '2026-06-01 10:00:00',
    ]);

    $this->actingAs($user)
        ->get('damage-assessment/reports/productivity?minDate=2026-05-01&maxDate=2026-05-31')
        ->assertOk()
        ->assertSee('productivity-table', false)
        ->assertSee('productivity-sticky-yellow', false);

    Excel::fake();

    $this->actingAs($user)
        ->get(route('export_productivity', [
            'minDate' => '2026-05-01',
            'maxDate' => '2026-05-31',
        ]))
        ->assertOk();

    Excel::assertDownloaded('productivity.xlsx', function (ProductivityExport $export): bool {
        $stats = $export->view()->getData()['stats'];
        $mayData = $stats['eng-may']['daily_breakdown']['2026-05-15'][0];

        return (int) $mayData->tda === 1
            && (int) $mayData->pda === 1
            && (int) $stats['eng-may']['engineer_total'] === 2
            && ! isset($stats['eng-may']['daily_breakdown']['2026-06-01'])
            && ! isset($stats['eng-building-only']);
    });
});
