<?php

use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\BuildingStatusHistory;
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

it('filters the area manager review queue by latest rejected or need review status and blocks other users', function () {
    DB::table('model_has_roles')->delete();
    DB::table('roles')->delete();
    DB::table('building_status_histories')->delete();
    DB::table('assessment_statuses')->delete();
    DB::table('buildings')->delete();
    DB::table('users')->delete();

    $areaManagerRole = Role::findOrCreate('Area Manager', 'web');

    $manager = User::factory()->create([
        'region' => 'south',
    ]);
    $manager->assignRole($areaManagerRole);

    $otherUser = User::factory()->create([
        'region' => 'south',
    ]);

    $rejected = AssessmentStatus::query()->create([
        'name' => 'rejected_by_engineer',
        'label_en' => 'Rejected By Engineer',
        'label_ar' => 'مرفوضة بواسطة المهندس',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);
    $needReview = AssessmentStatus::query()->create([
        'name' => 'need_review',
        'label_en' => 'Need Review',
        'label_ar' => 'بحاجة لمراجعة',
        'stage' => 'engineer',
        'order_step' => 2,
    ]);
    $accepted = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'مقبولة بواسطة المهندس',
        'stage' => 'engineer',
        'order_step' => 3,
    ]);

    $visibleRejected = Building::query()->create([
        'objectid' => 1101,
        'globalid' => 'building-visible-rejected',
        'building_name' => 'South Rejected',
        'municipalitie' => 'Khan Younis',
        'neighborhood' => 'Center',
        'assignedto' => 'Engineer A',
    ]);

    $visibleNeedReview = Building::query()->create([
        'objectid' => 1102,
        'globalid' => 'building-visible-need-review',
        'building_name' => 'South Need Review',
        'municipalitie' => 'Nusairat',
        'neighborhood' => 'Camp',
        'assignedto' => 'Engineer B',
    ]);

    $hiddenByLatestStatus = Building::query()->create([
        'objectid' => 1103,
        'globalid' => 'building-hidden-latest-accepted',
        'building_name' => 'South Accepted Latest',
        'municipalitie' => 'Nuseirat',
        'neighborhood' => 'West',
        'assignedto' => 'Engineer C',
    ]);

    $hiddenByMunicipality = Building::query()->create([
        'objectid' => 1104,
        'globalid' => 'building-hidden-north',
        'building_name' => 'North Rejected',
        'municipalitie' => 'Gaza',
        'neighborhood' => 'North',
        'assignedto' => 'Engineer D',
    ]);

    foreach ([
        [$visibleRejected->objectid, $accepted->id, 'Old accepted', now()->subDays(3)],
        [$visibleRejected->objectid, $rejected->id, 'Latest rejected', now()->subDay()],
        [$visibleNeedReview->objectid, $needReview->id, 'Latest need review', now()->subHours(12)],
        [$hiddenByLatestStatus->objectid, $rejected->id, 'Old rejected', now()->subDays(2)],
        [$hiddenByLatestStatus->objectid, $accepted->id, 'Latest accepted', now()->subHours(4)],
        [$hiddenByMunicipality->objectid, $rejected->id, 'North rejected', now()->subHours(2)],
    ] as [$buildingId, $statusId, $notes, $timestamp]) {
        BuildingStatusHistory::query()->create([
            'building_id' => $buildingId,
            'status_id' => $statusId,
            'type' => 'QC/QA Engineer',
            'user_id' => $manager->id,
            'notes' => $notes,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    $this->actingAs($manager)
        ->get(route('area-manager-review.index'))
        ->assertOk()
        ->assertSee('Area Manager Review Queue')
        ->assertSee('Khan Younis')
        ->assertSee('Nusairat');

    $this->actingAs($manager)
        ->get(route('area-manager-review.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertSee('South Rejected')
        ->assertSee('South Need Review')
        ->assertDontSee('South Accepted Latest')
        ->assertDontSee('North Rejected');

    $this->actingAs($otherUser)
        ->get(route('area-manager-review.index'))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->get(route('area-manager-review.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertForbidden();
});
