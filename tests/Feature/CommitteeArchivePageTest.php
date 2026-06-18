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
            'assignedto' => 'Old Engineer',
            'building_damage_status' => 'committee_review',
            'field_status' => 'COMPLETED',
            'general_notes' => 'Old full record note',
        ],
        'committee_decision_snapshot' => [
            'decision_type' => 'partially_damaged',
            'status' => 'completed',
            'arcgis_sync_status' => 'skipped',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('committee-archive.index'))
        ->assertOk()
        ->assertSee('أرشيف قرارات اللجنة الفنية')
        ->assertSee('8801')
        ->assertSee('Excel استثنائي');

    $this->actingAs($user)
        ->get(route('committee-archive.show', $archive))
        ->assertOk()
        ->assertSee('مقارنة القديم والحالي')
        ->assertSee('Old Building Name')
        ->assertSee('Current Building Name')
        ->assertSee('general_notes')
        ->assertSee('Old full record note')
        ->assertSee('Current full record note')
        ->assertSee('committee_review')
        ->assertSee('partially_damaged');
});
