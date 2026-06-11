<?php

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('sends reset passwords only to users whose only role is field engineer', function (): void {
    Mail::fake();

    $fieldEngineerRole = Role::findOrCreate('QC/QA Engineer', 'web');
    $databaseOfficerRole = Role::findOrCreate('Database Officer', 'web');

    $fieldEngineerOnly = User::factory()->create([
        'email' => 'field-only@example.com',
        'password' => Hash::make('old-password'),
    ]);
    $fieldEngineerOnly->assignRole($fieldEngineerRole);

    $fieldEngineerWithAnotherRole = User::factory()->create([
        'email' => 'field-with-other@example.com',
        'password' => Hash::make('old-password'),
    ]);
    $fieldEngineerWithAnotherRole->assignRole($fieldEngineerRole, $databaseOfficerRole);

    $databaseOfficerOnly = User::factory()->create([
        'email' => 'database-officer@example.com',
        'password' => Hash::make('old-password'),
    ]);
    $databaseOfficerOnly->assignRole($databaseOfficerRole);

    $this->artisan('users:send-field-engineer-passwords', ['--force' => true])
        ->assertSuccessful();

    Mail::assertSent(WelcomeUserMail::class, 1);
    Mail::assertSent(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($fieldEngineerOnly): bool {
        return $mail->hasTo('field-only@example.com')
            && Hash::check($mail->password, $fieldEngineerOnly->refresh()->password);
    });
    Mail::assertNotSent(WelcomeUserMail::class, fn (WelcomeUserMail $mail): bool => $mail->hasTo('field-with-other@example.com'));
    Mail::assertNotSent(WelcomeUserMail::class, fn (WelcomeUserMail $mail): bool => $mail->hasTo('database-officer@example.com'));

    expect(Hash::check('old-password', $fieldEngineerWithAnotherRole->refresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $databaseOfficerOnly->refresh()->password))->toBeTrue();
});

it('previews matching field engineers without resetting passwords or sending email', function (): void {
    Mail::fake();

    $fieldEngineerRole = Role::findOrCreate('QC/QA Engineer', 'web');
    $fieldEngineerOnly = User::factory()->create([
        'email' => 'field-dry-run@example.com',
        'password' => Hash::make('old-password'),
    ]);
    $fieldEngineerOnly->assignRole($fieldEngineerRole);

    $this->artisan('users:send-field-engineer-passwords', ['--dry-run' => true])
        ->assertSuccessful();

    Mail::assertNothingSent();

    expect(Hash::check('old-password', $fieldEngineerOnly->refresh()->password))->toBeTrue();
});
