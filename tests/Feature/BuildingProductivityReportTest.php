<?php

use App\Models\Building;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    config()->set('database.default', 'mysql');
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('renders and exports the building productivity report with totals and charts', function () {
    $role = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    Building::query()->create([
        'objectid' => 9101,
        'globalid' => 'building-productivity-1',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-10 10:00:00',
        'editdate' => '2026-04-10 10:00:00',
    ]);

    Building::query()->create([
        'objectid' => 9102,
        'globalid' => 'building-productivity-2',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'field_status' => 'pending',
        'creationdate' => '2026-04-11 10:00:00',
        'editdate' => '2026-04-11 10:00:00',
    ]);

    Building::query()->create([
        'objectid' => 9103,
        'globalid' => 'building-productivity-3',
        'governorate' => 'North Gaza',
        'municipalitie' => 'Jabalia',
        'neighborhood' => 'Camp',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-04-12 10:00:00',
        'editdate' => '2026-04-12 10:00:00',
    ]);

    Building::query()->create([
        'objectid' => 9104,
        'globalid' => 'building-productivity-outside-range',
        'governorate' => 'Gaza',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Zeitoun',
        'field_status' => 'COMPLETED',
        'creationdate' => '2026-03-01 10:00:00',
        'editdate' => '2026-03-01 10:00:00',
    ]);

    $response = $this->actingAs($user)->get(route('reports.building-productivity.index', [
        'from_date' => '2026-04-01',
        'to_date' => '2026-04-30',
    ]));

    $response->assertOk()
        ->assertSee('Building Productivity Report')
        ->assertSee('Completion Distribution')
        ->assertSee('Completed vs Not Completed by Gov')
        ->assertSee('Top Neighborhoods Completed %')
        ->assertSee('Every Neighborhood Productivity')
        ->assertSee('Location Pie Charts')
        ->assertSee('Governorate | 2 buildings')
        ->assertSee('Municipality: Gaza')
        ->assertSee('Neighborhoods under Gaza')
        ->assertSee('<td>Gaza</td>', false)
        ->assertSee('<td>Rimal</td>', false)
        ->assertSee('Grand Total');

    $response->assertViewHas('summary', function (array $summary): bool {
        return $summary['completed'] === 2
            && $summary['not_completed'] === 1
            && $summary['buildings_count'] === 3;
    });

    $this->actingAs($user)
        ->get(route('reports.building-productivity.export', [
            'from_date' => '2026-04-01',
            'to_date' => '2026-04-30',
        ]))
        ->assertOk();
});

it('renders the empty building productivity table without tbody colspan', function () {
    $role = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('reports.building-productivity.index'))
        ->assertOk()
        ->assertSee('No matching data.')
        ->assertDontSee('colspan="7"', false);
});
