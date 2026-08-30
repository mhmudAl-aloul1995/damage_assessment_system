<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Database Officer', 'web');

    foreach (['QC/QA Engineer', 'Auditing Supervisor', 'Team Leader', 'Team Leader -INF'] as $roleName) {
        Role::findOrCreate($roleName, 'web');
    }
});

it('updates a user with an avatar and empty arcgis username through the ajax form method spoofing flow', function () {
    $databaseOfficer = User::factory()->create();
    $databaseOfficer->assignRole('Database Officer');

    $user = User::factory()->create([
        'email' => 'old-user@example.test',
        'id_no' => '413349051',
        'username_arcgis' => 'old.arcgis.username',
    ]);

    $response = $this->actingAs($databaseOfficer)
        ->post(route('users.update', $user), [
            '_method' => 'PUT',
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 600, 600),
            'name' => 'سعيد محمد جمال عبد الخالق ريحان',
            'name_en' => 'SAID MJ. A. REHAN',
            'username_arcgis' => '',
            'email' => 'said.rehan550@gmail.com',
            'id_no' => '413349051',
            'contract_type' => 'phc',
            'address' => 'غزة',
            'phone' => '594712230',
            'region' => 'north',
            'default_phase_number' => '',
            'send_password' => '',
            'roles' => [
                'QC/QA Engineer',
                'Auditing Supervisor',
                'Team Leader',
                'Team Leader -INF',
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('message', __('ui.users.saved'));

    $user->refresh();

    expect($user->name)->toBe('سعيد محمد جمال عبد الخالق ريحان')
        ->and($user->username_arcgis)->toBeNull()
        ->and($user->email)->toBe('said.rehan550@gmail.com')
        ->and($user->hasAllRoles([
            'QC/QA Engineer',
            'Auditing Supervisor',
            'Team Leader',
            'Team Leader -INF',
        ]))->toBeTrue()
        ->and($user->avatar)->not->toBeNull();

    expect(file_exists(storage_path('app/public/'.$user->avatar)))->toBeTrue();

    @unlink(storage_path('app/public/'.$user->avatar));
});
