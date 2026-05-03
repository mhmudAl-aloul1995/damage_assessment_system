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

it('shows daily lawyer achievement counts grouped by legal auditor', function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);

    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $lawyerRole = Role::query()->create([
        'name' => 'Legal Auditor',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    $firstLawyer = User::factory()->create([
        'name' => 'Lawyer One',
    ]);
    $firstLawyer->assignRole($lawyerRole);

    $secondLawyer = User::factory()->create([
        'name' => 'Lawyer Two',
    ]);
    $secondLawyer->assignRole($lawyerRole);

    $assignedStatus = AssessmentStatus::query()->create([
        'name' => 'assigned_to_lawyer',
        'label_en' => 'Assigned To Lawyer',
        'label_ar' => 'assigned ar',
        'stage' => 'lawyer',
        'order_step' => 6,
    ]);

    $acceptedStatus = AssessmentStatus::query()->create([
        'name' => 'accepted_by_lawyer',
        'label_en' => 'Accepted By Lawyer',
        'label_ar' => 'accepted ar',
        'stage' => 'lawyer',
        'order_step' => 8,
    ]);

    $legalNotesStatus = AssessmentStatus::query()->create([
        'name' => 'legal_notes',
        'label_en' => 'Legal Notes',
        'label_ar' => 'notes ar',
        'stage' => 'lawyer',
        'order_step' => 7,
    ]);

    Building::query()->create([
        'objectid' => 301,
        'globalid' => 'building-301',
    ]);

    Building::query()->create([
        'objectid' => 302,
        'globalid' => 'building-302',
    ]);

    HousingUnit::query()->create([
        'objectid' => 401,
        'globalid' => 'housing-401',
        'parentglobalid' => 'building-301',
    ]);

    HousingUnit::query()->create([
        'objectid' => 402,
        'globalid' => 'housing-402',
        'parentglobalid' => 'building-301',
    ]);

    HousingUnit::query()->create([
        'objectid' => 403,
        'globalid' => 'housing-403',
        'parentglobalid' => 'building-302',
    ]);

    HousingStatus::query()->create([
        'housing_id' => 401,
        'status_id' => $assignedStatus->id,
        'user_id' => $firstLawyer->id,
        'type' => 'Legal Auditor',
        'notes' => 'assigned today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    HousingStatus::query()->create([
        'housing_id' => 402,
        'status_id' => $acceptedStatus->id,
        'user_id' => $firstLawyer->id,
        'type' => 'Legal Auditor',
        'notes' => 'accepted today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    HousingStatus::query()->create([
        'housing_id' => 403,
        'status_id' => $legalNotesStatus->id,
        'user_id' => $firstLawyer->id,
        'type' => 'Legal Auditor',
        'notes' => 'notes today',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    HousingStatus::query()->insert([
        'housing_id' => 404,
        'status_id' => $acceptedStatus->id,
        'user_id' => $secondLawyer->id,
        'type' => 'Legal Auditor',
        'notes' => 'old accepted',
        'updated_at' => now()->subDays(3),
        'created_at' => now()->subDays(3),
    ]);

    BuildingStatus::query()->insert([
        'building_id' => 301,
        'status_id' => $acceptedStatus->id,
        'user_id' => $firstLawyer->id,
        'type' => 'Legal Auditor',
        'notes' => 'accepted building',
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    BuildingStatus::query()->insert([
        'building_id' => 302,
        'status_id' => $acceptedStatus->id,
        'user_id' => $firstLawyer->id,
        'type' => 'Legal Auditor',
        'notes' => 'old building',
        'updated_at' => now()->subDays(3),
        'created_at' => now()->subDays(3),
    ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('reports.daily-achievement', ['tab' => 'lawyers',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertSee('Lawyer One');
    $response->assertSee('Lawyer Two');
    $response->assertViewHas('rows', function ($rows) {
        $firstRow = collect($rows)->firstWhere('name', 'Lawyer One');
        $secondRow = collect($rows)->firstWhere('name', 'Lawyer Two');

        return $firstRow !== null
            && $firstRow['assigned_count'] === 1
            && $firstRow['accepted_count'] === 1
            && $firstRow['legal_notes_count'] === 1
            && $firstRow['total_count'] === 3
            && $secondRow !== null
            && $secondRow['assigned_count'] === 0
            && $secondRow['accepted_count'] === 0
            && $secondRow['legal_notes_count'] === 0
            && $secondRow['total_count'] === 0;
    });
    $response->assertViewHas('totals', function (array $totals) {
        return $totals['assigned_count'] === 1
            && $totals['accepted_count'] === 1
            && $totals['legal_notes_count'] === 1
            && $totals['total_count'] === 3;
    });
    $response->assertViewHas('chartMetrics', function (array $chartMetrics) {
        return $chartMetrics['buildings']['audited_count'] === 1
            && $chartMetrics['buildings']['total_count'] === 2
            && $chartMetrics['buildings']['percentage'] === 50.0
            && $chartMetrics['housing_units']['audited_count'] === 2
            && $chartMetrics['housing_units']['total_count'] === 3
            && $chartMetrics['housing_units']['percentage'] === 66.7;
    });
});
