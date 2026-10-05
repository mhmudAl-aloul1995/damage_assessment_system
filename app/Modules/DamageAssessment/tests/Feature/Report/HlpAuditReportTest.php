<?php

use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('counts only legal notes and accepted by lawyer statuses in the hlp audit report', function () {
    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $legalNotesStatus = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'Legal Notes',
        'stage' => 'lawyer',
        'order_step' => 7,
    ]);

    $acceptedByLawyerStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_lawyer',
        'label_en' => 'Accepted By Lawyer',
        'label_ar' => 'Accepted By Lawyer',
        'stage' => 'lawyer',
        'order_step' => 8,
    ]);

    $assignedToLawyerStatus = AssessmentStatus::query()->create([
        'name' => 'assigned_to_lawyer',
        'label_en' => 'Assigned To Lawyer',
        'label_ar' => 'Assigned To Lawyer',
        'stage' => 'lawyer',
        'order_step' => 6,
    ]);

    Building::query()->create([
        'objectid' => 501,
        'globalid' => 'building-501',
        'governorate' => 'Gaza',
        'neighborhood' => 'Rimal',
    ]);

    Building::query()->create([
        'objectid' => 502,
        'globalid' => 'building-502',
        'governorate' => 'Gaza',
        'neighborhood' => 'Rimal',
    ]);

    Building::query()->create([
        'objectid' => 503,
        'globalid' => 'building-503',
        'governorate' => 'North Gaza',
        'neighborhood' => 'Camp',
    ]);

    HousingUnit::query()->create([
        'objectid' => 601,
        'globalid' => 'housing-601',
        'parentglobalid' => 'building-501',
    ]);

    HousingUnit::query()->create([
        'objectid' => 602,
        'globalid' => 'housing-602',
        'parentglobalid' => 'building-501',
    ]);

    HousingUnit::query()->create([
        'objectid' => 603,
        'globalid' => 'housing-603',
        'parentglobalid' => 'building-503',
    ]);

    BuildingStatus::query()->insert([
        [
            'building_id' => 501,
            'status_id' => $legalNotesStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'updated_at' => '2026-05-01 10:00:00',
            'created_at' => '2026-05-01 10:00:00',
        ],
        [
            'building_id' => 502,
            'status_id' => $assignedToLawyerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'updated_at' => '2026-05-01 10:00:00',
            'created_at' => '2026-05-01 10:00:00',
        ],
        [
            'building_id' => 503,
            'status_id' => $acceptedByLawyerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'updated_at' => '2026-04-01 10:00:00',
            'created_at' => '2026-04-01 10:00:00',
        ],
    ]);

    HousingStatus::query()->insert([
        [
            'housing_id' => 601,
            'status_id' => $acceptedByLawyerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'updated_at' => '2026-05-01 11:00:00',
            'created_at' => '2026-05-01 11:00:00',
        ],
        [
            'housing_id' => 602,
            'status_id' => $legalNotesStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'updated_at' => '2026-05-02 11:00:00',
            'created_at' => '2026-05-02 11:00:00',
        ],
        [
            'housing_id' => 603,
            'status_id' => $acceptedByLawyerStatus->id,
            'user_id' => $viewer->id,
            'type' => 'Legal Auditor',
            'updated_at' => '2026-04-01 11:00:00',
            'created_at' => '2026-04-01 11:00:00',
        ],
    ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('reports.hlp-audit', [
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]));

    $response
        ->assertOk()
        ->assertSee('HLP Buildings')
        ->assertSee('HLP Housings')
        ->assertSee('<td>Gaza</td>', false)
        ->assertSee('<td>Rimal</td>', false);

    $response->assertViewHas('rows', function ($rows): bool {
        $rimal = collect($rows)->firstWhere('neighborhood', 'Rimal');

        return $rimal !== null
            && (int) $rimal->hlp_buildings === 1
            && (int) $rimal->hlp_housings === 2;
    });

    $response->assertViewHas('summary', function (array $summary): bool {
        return $summary['hlp_buildings'] === 1
            && $summary['hlp_housings'] === 2;
    });

    $this
        ->actingAs($viewer)
        ->get(route('reports.hlp-audit.export', [
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]))
        ->assertOk();
});
