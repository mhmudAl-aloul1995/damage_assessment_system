<?php

use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\User;
use App\Support\Navigation\Sidebar;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-arcgis-token',
        ]),
        'https://services2.arcgis.com/*' => Http::response([
            'features' => [],
            'exceededTransferLimit' => false,
        ]),
    ]);
});

it('lets audit managers manage audit reviewers', function (string $managerRoleName) {
    $managerRole = Role::findOrCreate($managerRoleName, 'web');
    Role::findOrCreate('Audit Reviewer', 'web');
    Role::findOrCreate('QC/QA Engineer', 'web');
    Role::findOrCreate('Legal Auditor', 'web');

    $manager = User::factory()->create();
    $manager->assignRole($managerRole);

    $reviewer = User::factory()->create([
        'name' => 'Audit Reviewer Candidate',
    ]);

    $this->actingAs($manager)
        ->get(route('audit.reviewers.index'))
        ->assertRedirect(route('audit.index', ['audit_reviewers' => 1]));

    $this->actingAs($manager)
        ->get(route('audit.index', ['audit_reviewers' => 1]))
        ->assertOk()
        ->assertSee('auditReviewersModal')
        ->assertSee('Audit Reviewer Candidate');

    $this->actingAs($manager)
        ->post(route('audit.reviewers.store'), [
            'user_id' => $reviewer->id,
        ])
        ->assertRedirect();

    expect($reviewer->fresh()->hasRole('Audit Reviewer'))->toBeTrue();

    $this->actingAs($manager)
        ->delete(route('audit.reviewers.destroy', $reviewer))
        ->assertRedirect();

    expect($reviewer->fresh()->hasRole('Audit Reviewer'))->toBeFalse();
})->with([
    'auditing supervisor' => 'Auditing Supervisor',
    'database officer' => 'Database Officer',
]);

it('prevents non audit managers from managing audit reviewers', function () {
    Role::findOrCreate('Project Officer', 'web');

    $user = User::factory()->create();
    $user->assignRole('Project Officer');

    $this->actingAs($user)
        ->get(route('audit.reviewers.index'))
        ->assertForbidden();
});

it('shows audit home to audit reviewers without showing reviewer management', function () {
    Role::findOrCreate('Audit Reviewer', 'web');

    $user = User::factory()->create();
    $user->assignRole('Audit Reviewer');

    $urls = Sidebar::forUser($user)
        ->flatMap(fn (array $module) => $module['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->pluck('url')
        ->all();

    expect($urls)
        ->toContain('damage-assessment/audit')
        ->not->toContain('damage-assessment/audit/reviewers');
});

it('lets audit reviewers edit and set statuses without assessment assignment', function () {
    Role::findOrCreate('Audit Reviewer', 'web');

    $user = User::factory()->create();
    $user->assignRole('Audit Reviewer');

    $status = AssessmentStatus::query()->create([
        'name' => 'accepted_by_engineer',
        'label_en' => 'Accepted By Engineer',
        'label_ar' => 'Accepted By Engineer',
        'stage' => 'engineer',
        'order_step' => 1,
    ]);

    $building = Building::query()->create([
        'objectid' => 96301,
        'globalid' => 'audit-reviewer-building',
        'building_name' => 'Original Building',
    ]);

    $this->actingAs($user)
        ->postJson(route('assessment.inline.update'), [
            'type' => 'building_table',
            'globalid' => $building->globalid,
            'field' => 'building_name',
            'value' => 'Changed Building',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('field_value', 'Changed Building');

    $this->actingAs($user)
        ->postJson(route('building.assessment.set.status'), [
            'globalid' => $building->globalid,
            'status' => 'accepted',
            'audit_type' => 'QC/QA Engineer',
        ])
        ->assertOk()
        ->assertJsonPath('data.status_name', 'accepted_by_engineer');

    $this->assertDatabaseHas('edit_assessments', [
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'building_name',
        'field_value' => 'Changed Building',
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('building_statuses', [
        'building_id' => $building->objectid,
        'status_id' => $status->id,
        'type' => 'QC/QA Engineer',
    ]);
});
