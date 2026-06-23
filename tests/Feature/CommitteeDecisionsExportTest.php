<?php

declare(strict_types=1);

use App\Exports\CommitteeDecisionsExport;
use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('exports filtered committee buildings with the columns shown in the table', function () {
    $user = User::factory()->create();
    $building = Building::query()->create([
        'objectid' => 9101,
        'globalid' => 'building-export-1',
        'building_name' => 'برج الأمل',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'Rimal',
        'assignedto' => 'Engineer One',
        'building_damage_status' => 'committee_review',
    ]);
    Building::query()->create([
        'objectid' => 9102,
        'globalid' => 'building-export-2',
        'municipalitie' => 'North Gaza',
        'building_damage_status' => 'committee_review',
    ]);

    Excel::fake();

    $this->actingAs($user)
        ->get(route('committee-decisions.export', ['type' => 'buildings', 'municipality' => 'Gaza']))
        ->assertSuccessful();

    Excel::assertDownloaded('committee-decisions-buildings.xlsx', function (CommitteeDecisionsExport $export) use ($building): bool {
        expect($export->headings())->toBe([
            'ObjectID', 'اسم المبنى', 'البلدية', 'الحي', 'المهندس الميداني', 'الحالة الحالية', 'القرار', 'التواقيع', 'ArcGIS', 'الإجراء',
        ])->and($export->collection())->toHaveCount(1)
            ->and($export->collection()->first())->toBe([
                $building->objectid,
                'برج الأمل',
                'Gaza',
                'Rimal',
                'Engineer One',
                'committee_review',
                'لا يوجد',
                '0 / 0',
                'pending',
                route('committee-decisions.buildings.show', $building),
            ]);

        return true;
    });
});

it('exports housing units when their tab is selected', function () {
    $user = User::factory()->create();
    $building = Building::query()->create([
        'objectid' => 9201,
        'globalid' => 'building-export-unit',
        'building_name' => 'برج النور',
        'building_damage_status' => 'committee_review',
    ]);
    $unit = HousingUnit::query()->create([
        'objectid' => 9301,
        'globalid' => 'unit-export-1',
        'parentglobalid' => $building->globalid,
        'q_9_3_1_first_name' => 'أحمد',
        'q_9_3_2_second_name__father' => 'محمد',
        'q_9_3_4_last_name' => 'خليل',
        'unit_damage_status' => 'committee_review2',
    ]);

    Excel::fake();

    $this->actingAs($user)
        ->get(route('committee-decisions.export', ['type' => 'housing-units']))
        ->assertSuccessful();

    Excel::assertDownloaded('committee-decisions-housing-units.xlsx', function (CommitteeDecisionsExport $export) use ($unit): bool {
        expect($export->collection())->toHaveCount(1)
            ->and($export->collection()->first()[0])->toBe($unit->objectid)
            ->and($export->collection()->first()[1])->toBe('أحمد محمد خليل')
            ->and($export->collection()->first()[2])->toBe('برج النور');

        return true;
    });
});
