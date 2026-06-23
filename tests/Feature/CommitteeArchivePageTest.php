<?php

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    app(RolesAndPermissionsSeeder::class)->run();
});

it('shows archived committee records and compares snapshot values with current values', function () {
    $user = User::factory()->create();

    $building = Building::query()->create([
        'objectid' => 8801,
        'globalid' => 'archive-building-globalid',
        'building_name' => 'Current Building Name',
        'neighborhood' => 'Rimal',
        'municipalitie' => 'Gaza',
        'assignedto' => 'Current Engineer',
        'building_damage_status' => 'partially_damaged',
        'field_status' => 'Not_Completed',
        'general_notes' => 'Current full record note',
    ]);

    $decision = CommitteeDecision::query()->create([
        'decisionable_type' => Building::class,
        'decisionable_id' => $building->id,
        'decision_type' => 'partially_damaged',
        'status' => CommitteeDecision::STATUS_COMPLETED,
        'arcgis_sync_status' => 'skipped',
    ]);

    $archive = BuildingSurveyArchiveObject::query()->create([
        'building_objectid' => $building->objectid,
        'building_globalid' => $building->globalid,
        'source_type' => 'temporary_committee_excel_archive',
        'committee_decision_id' => $decision->id,
        'archived_by' => $user->id,
        'archived_at' => now(),
        'notes' => 'Exceptional archive from temporary committee Excel seed.',
        'building_snapshot' => [
            'objectid' => 8801,
            'globalid' => 'archive-building-globalid',
            'building_name' => 'Old Building Name',
            'neighborhood' => 'Rimal',
            'municipalitie' => 'Gaza',
            'assignedto' => 'Old Engineer',
            'building_damage_status' => 'committee_review',
            'field_status' => 'COMPLETED',
            'general_notes' => 'Old full record note',
        ],
        'committee_decision_snapshot' => [
            'decision_type' => 'partially_damaged',
            'status' => 'completed',
            'arcgis_sync_status' => 'skipped',
            'committee_members' => [[
                'name' => 'Archived Committee Member',
                'title' => 'Engineer',
                'status' => 'approved',
                'notes' => 'Approved in the archived decision.',
                'signed_at' => '2026-06-20 10:30:00',
                'signed_by' => 'Archived Committee User',
            ]],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('committee-archive.index'))
        ->assertOk()
        ->assertSee('أرشيف قرارات اللجنة الفنية')
        ->assertSee('8801')
        ->assertSee('Archived Committee Member')
        ->assertSee('Excel استثنائي');

    $this->actingAs($user)
        ->get(route('committee-archive.index', [
            'municipality' => 'Gaza',
            'old_damage_status' => 'committee_review',
            'current_damage_status' => 'partially_damaged',
            'field_status' => 'Not_Completed',
        ]))
        ->assertOk()
        ->assertSee('8801')
        ->assertSee('Gaza')
        ->assertDontSee('لا توجد سجلات مطابقة.');

    $this->actingAs($user)
        ->get(route('committee-archive.show', $archive))
        ->assertOk()
        ->assertSee('مراجعة التغييرات')
        ->assertSee('السجل السابق')
        ->assertSee('السجل الحالي')
        ->assertSee('الحقول المتغيرة')
        ->assertSee('عرض السجل الخام للمراجعة الفنية')
        ->assertSee('Old Building Name')
        ->assertSee('Current Building Name')
        ->assertSee('general_notes')
        ->assertSee('Old full record note')
        ->assertSee('Current full record note')
        ->assertSee('Archived Committee Member')
        ->assertSee('Archived Committee User')
        ->assertSee('Approved in the archived decision.')
        ->assertSee('committee_review')
        ->assertSee('partially_damaged');
});

it('uses Bootstrap pagination so navigation arrows keep their intended size', function () {
    $user = User::factory()->create();

    foreach (range(1, 26) as $index) {
        BuildingSurveyArchiveObject::query()->create([
            'building_objectid' => 8900 + $index,
            'source_type' => 'committee_decision',
            'archived_by' => $user->id,
            'archived_at' => now()->subMinutes($index),
        ]);
    }

    $this->actingAs($user)
        ->get(route('committee-archive.index'))
        ->assertOk()
        ->assertSee('class="pagination"', false)
        ->assertDontSee('w-5 h-5', false);
});
