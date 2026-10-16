<?php

use App\Models\User;
use App\Modules\HR\Models\AttendanceChallenge;
use App\Modules\HR\Models\DeviceCredential;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\WorkSchedule;
use App\Modules\HR\Notifications\DeviceDecisionNotification;
use App\Modules\HR\Services\AttendanceCryptography;
use App\Modules\HR\Services\AttendanceGatewayService;
use App\Modules\HR\Services\AttendanceSetup;
use App\Modules\HR\Services\DeviceEnrollment;
use App\Modules\HR\Services\SecureAttendance;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(HrPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:10:00', 'Asia/Hebron'));
    Notification::fake();
    $this->staff = User::factory()->create();
    $this->staff->givePermissionTo(['hr.attendance.view-own', 'hr.attendance.capture', 'hr.devices.register']);
    $this->employee = Employee::factory()->create(['user_id' => $this->staff->id]);
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['hr.attendance.view', 'hr.attendance.setup', 'hr.devices.view', 'hr.devices.approve', 'hr.devices.revoke']);
    $this->keyOptions = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
    $opensslConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    if (is_file($opensslConfig)) {
        $this->keyOptions['config'] = $opensslConfig;
    }
    $this->key = openssl_pkey_new($this->keyOptions);
    expect($this->key)->not->toBeFalse();
    $this->publicKey = openssl_pkey_get_details($this->key)['key'];
    $this->enrollment = app(DeviceEnrollment::class);
    $this->sign = function (string $payload): string {
        openssl_sign($payload, $signature, $this->key, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    };
    $this->register = function (): DeviceCredential {
        $challenge = $this->enrollment->challenge($this->staff, $this->employee);
        $this->registrationData = ['challenge_id' => $challenge->id, 'device_uuid' => (string) Str::uuid(), 'label' => 'PHC attendance device',
            'public_key' => $this->publicKey, 'signature' => ($this->sign)($challenge->payload)];

        return $this->enrollment->register($this->staff, $this->employee, $this->registrationData);
    };
    $this->approved = function (): DeviceCredential {
        $credential = ($this->register)();
        $this->enrollment->decide($this->admin, $credential, ['decision' => 'approved', 'reason' => 'Identity verified by HR']);

        return $credential->fresh();
    };
    $this->allowTestGateway = function (): void {
        $gateway = Mockery::mock(AttendanceGatewayService::class);
        $gateway->shouldReceive('assertCaptureAllowed')->andReturnNull();
        app()->instance(AttendanceGatewayService::class, $gateway);
    };
    $this->request = Request::create('https://attendance.example.test/api/v1/hr/attendance/challenge');
    $this->schedule = WorkSchedule::factory()->create();
    $this->assignment = app(AttendanceSetup::class)->assign($this->admin, ['employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
});

it('can require HTTPS explicitly without minting credentials on HTTP', function (): void {
    config(['hr.native_require_https' => true, 'hr.attendance_enabled' => false]);
    $data = ['email' => $this->staff->email, 'password' => '123456', 'device_name' => 'Android'];
    $this->postJson('/api/v1/hr/auth/login', $data)->assertStatus(426);
    expect($this->staff->tokens()->count())->toBe(0);
    $response = $this->postJson('https://localhost/api/v1/hr/auth/login', $data)->assertOk()->assertJsonMissingPath('employee.id_no');
    expect($this->staff->tokens()->first()->abilities)->toBe(['hr:native']);
    $this->withToken($response->json('access_token'))->getJson('https://localhost/api/v1/hr/me')->assertOk()->assertJsonPath('data.capture_enabled', false);
});

it('records signed attendance over the selected transport without an internal gateway', function (string $scheme): void {
    config(['hr.native_require_https' => false, 'hr.attendance_enabled' => true, 'hr.network_mode' => 'unrestricted']);
    $base = $scheme.'://localhost/api/v1/hr/';
    $login = $this->postJson($base.'auth/login', ['email' => $this->staff->email, 'password' => '123456', 'device_name' => 'Android'])->assertOk();
    $this->withToken($login->json('access_token'))->getJson($base.'me')
        ->assertOk()->assertJsonPath('data.capture_enabled', true)->assertJsonPath('data.blockers', []);
    $credential = ($this->register)();
    $this->postJson($base.'attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_in'])->assertForbidden();
    $this->enrollment->decide($this->admin, $credential, ['decision' => 'approved', 'reason' => 'Device approved independently']);
    $challenge = $this->postJson($base.'attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_in'])->assertCreated()->json('data');
    $data = ['challenge_id' => $challenge['id'], 'signature' => ($this->sign)(base64_decode($challenge['payload']).'altered')];
    $this->postJson($base.'attendance/check-in', $data)->assertUnprocessable();
    $data['signature'] = ($this->sign)(base64_decode($challenge['payload']));
    $receipt = $this->postJson($base.'attendance/check-in', $data)->assertOk()->assertJsonPath('data.confirmed', true)->json('data.event_id');
    $this->postJson($base.'attendance/check-in', $data)->assertOk()->assertJsonPath('data.event_id', $receipt);
    $this->travel(1)->hours();
    $checkout = $this->postJson($base.'attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_out'])->assertCreated()->json('data');
    $this->postJson($base.'attendance/check-out', ['challenge_id' => $checkout['id'], 'signature' => ($this->sign)(base64_decode($checkout['payload']))])
        ->assertOk()->assertJsonPath('data.action', 'check_out');
    $this->assertDatabaseCount('hr_attendance_events', 2);
})->with(['http', 'https']);

it('reports and enforces an administrative pause even on unrestricted networks', function (): void {
    config(['hr.native_require_https' => false, 'hr.attendance_enabled' => false, 'hr.network_mode' => 'unrestricted']);
    $credential = ($this->approved)();
    $this->withToken($this->staff->createToken('HR', ['hr:native'])->plainTextToken)
        ->getJson('/api/v1/hr/me')->assertOk()->assertJsonPath('data.capture_enabled', false)->assertJsonCount(1, 'data.blockers');
    $this->postJson('/api/v1/hr/attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_in'])->assertServiceUnavailable();
    $this->assertDatabaseCount('hr_attendance_challenges', 0);
});

it('keeps the signed action rate limit separate from attendance screen reads', function (): void {
    config(['hr.native_require_https' => false, 'hr.attendance_enabled' => true, 'hr.network_mode' => 'unrestricted']);
    $credential = ($this->register)();
    $this->withToken($this->staff->createToken('HR', ['hr:native'])->plainTextToken);
    for ($request = 0; $request < 5; $request++) {
        $this->getJson('/api/v1/hr/me')->assertOk();
    }
    for ($request = 0; $request < 10; $request++) {
        $this->postJson('/api/v1/hr/attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_in'])->assertForbidden();
    }
    $this->postJson('/api/v1/hr/attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_in'])->assertTooManyRequests();
    $this->assertDatabaseCount('hr_attendance_events', 0);
});

it('rejects inquiry and unrestricted tokens on the secure HR audience', function (array $abilities): void {
    $this->withToken($this->staff->createToken('legacy', $abilities)->plainTextToken)->getJson('https://localhost/api/v1/hr/me')->assertForbidden();
})->with([[['mobile:read']], [['*']], [['hr:native', 'mobile:read']]]);

it('registers a proof of key ownership once and keeps credentials pending and immutable', function (): void {
    $credential = ($this->register)();
    expect($credential->status())->toBe('pending')->and($credential->attestation_status)->toBe('unverified');
    expect($this->enrollment->register($this->staff, $this->employee, $this->registrationData)->id)->toBe($credential->id);
    $this->assertDatabaseCount('hr_device_credentials', 1);
    expect(fn () => $credential->update(['public_key' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $credential->delete())->toThrow(LogicException::class);
    $this->registrationData['label'] = 'Different request';
    expect(fn () => $this->enrollment->register($this->staff, $this->employee, $this->registrationData))->toThrow(ValidationException::class);
});

it('rejects expired registration proof and an altered challenge', function (): void {
    $challenge = $this->enrollment->challenge($this->staff, $this->employee);
    $data = ['challenge_id' => $challenge->id, 'device_uuid' => (string) Str::uuid(), 'label' => 'Android', 'public_key' => $this->publicKey, 'signature' => ($this->sign)($challenge->payload.'x')];
    expect(fn () => $this->enrollment->register($this->staff, $this->employee, $data))->toThrow(ValidationException::class);
    $data['signature'] = ($this->sign)($challenge->payload);
    $this->travel(5)->minutes();
    expect(fn () => $this->enrollment->register($this->staff, $this->employee, $data))->toThrow(ValidationException::class, 'انتهت صلاحية');
    $this->assertDatabaseCount('hr_employee_devices', 0);
});

it('rejects wrong key algorithms instead of treating any public key as an Android credential', function (): void {
    $rsa = openssl_pkey_new(array_replace($this->keyOptions, ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]));
    expect(fn () => app(AttendanceCryptography::class)->publicKey(openssl_pkey_get_details($rsa)['key']))->toThrow(ValidationException::class);
});

it('requires independent approval and one active device before replacement', function (): void {
    $first = ($this->register)();
    $this->staff->givePermissionTo('hr.devices.approve');
    expect(fn () => $this->enrollment->decide($this->staff, $first, ['decision' => 'approved', 'reason' => 'Self approval attempt']))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->enrollment->decide($this->admin, $first, ['decision' => 'approved', 'reason' => 'Identity checked independently']);
    $this->key = openssl_pkey_new($this->keyOptions);
    $this->publicKey = openssl_pkey_get_details($this->key)['key'];
    $second = ($this->register)();
    expect(fn () => $this->enrollment->decide($this->admin, $second, ['decision' => 'approved', 'reason' => 'Replacement approval']))->toThrow(ValidationException::class);
    $this->enrollment->decide($this->admin, $first, ['decision' => 'revoked', 'reason' => 'Lost device reported']);
    $this->enrollment->decide($this->admin, $second, ['decision' => 'approved', 'reason' => 'Replacement approved after revocation']);
    expect($first->fresh()->status())->toBe('revoked')->and($second->fresh()->status())->toBe('approved');
    Notification::assertSentTo($this->staff, DeviceDecisionNotification::class);
});

it('allows an employee to revoke their own lost device but denies other employees', function (): void {
    $credential = ($this->approved)();
    $other = User::factory()->create();
    expect(fn () => $this->enrollment->decide($other, $credential, ['decision' => 'revoked', 'reason' => 'Other employee attempt']))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    $this->enrollment->decide($this->staff, $credential, ['decision' => 'revoked', 'reason' => 'My approved device is lost']);
    expect($credential->fresh()->status())->toBe('revoked');
});

it('keeps production capture closed despite flags and forged network headers', function (): void {
    $credential = ($this->approved)();
    config(['hr.attendance_enabled' => true, 'hr.network_mode' => 'simulation', 'hr.gateway_url' => 'https://attendance.example.test']);
    $this->withToken($this->staff->createToken('HR', ['hr:native'])->plainTextToken)->withHeaders(['X-PHC-Gateway' => 'trusted', 'X-Forwarded-For' => '127.0.0.1'])
        ->postJson('https://localhost/api/v1/hr/attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_in'])->assertServiceUnavailable();
    $this->assertDatabaseCount('hr_attendance_events', 0);
    $this->assertDatabaseCount('hr_attendance_challenges', 0);
});

it('consumes a verified challenge and appends server time once with a bounded receipt', function (): void {
    $credential = ($this->approved)();
    ($this->allowTestGateway)();
    $service = app(SecureAttendance::class);
    $challenge = $service->challenge($this->staff, $this->employee, ['credential_id' => $credential->id, 'action' => 'check_in', 'assignment_id' => $this->assignment->id], $this->request);
    expect($challenge->expires_at->timestamp - now('UTC')->timestamp)->toBe(60);
    $data = ['challenge_id' => $challenge->id, 'signature' => ($this->sign)($challenge->payload)];
    $event = $service->submit($this->staff, $this->employee, 'check_in', $data, $this->request);
    expect($event->source)->toBe('signed_native')->and($event->occurred_at)->toBe(now('UTC')->format('Y-m-d H:i:s'));
    expect($service->submit($this->staff, $this->employee, 'check_in', $data, $this->request)->id)->toBe($event->id);
    $this->assertDatabaseCount('hr_attendance_events', 1);
    $this->travel(10)->minutes();
    expect(fn () => $service->submit($this->staff, $this->employee, 'check_in', $data, $this->request))->toThrow(ValidationException::class);
});

it('rejects expired, changed and wrong-action signed attendance challenges', function (): void {
    $credential = ($this->approved)();
    ($this->allowTestGateway)();
    $service = app(SecureAttendance::class);
    $challenge = $service->challenge($this->staff, $this->employee, ['credential_id' => $credential->id, 'action' => 'check_in'], $this->request);
    $data = ['challenge_id' => $challenge->id, 'signature' => ($this->sign)($challenge->payload.'altered')];
    expect(fn () => $service->submit($this->staff, $this->employee, 'check_in', $data, $this->request))->toThrow(ValidationException::class);
    $data['signature'] = ($this->sign)($challenge->payload);
    expect(fn () => $service->submit($this->staff, $this->employee, 'check_out', $data, $this->request))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->travel(60)->seconds();
    expect(fn () => $service->submit($this->staff, $this->employee, 'check_in', $data, $this->request))->toThrow(ValidationException::class);
    expect(AttendanceChallenge::query()->first()->consumed_at)->toBeNull();
    $this->assertDatabaseCount('hr_attendance_events', 0);
});

it('rechecks revocation and account activity when a signed challenge is submitted', function (bool $revoke): void {
    $credential = ($this->approved)();
    ($this->allowTestGateway)();
    $service = app(SecureAttendance::class);
    $challenge = $service->challenge($this->staff, $this->employee, ['credential_id' => $credential->id, 'action' => 'check_in'], $this->request);
    if ($revoke) {
        $this->enrollment->decide($this->admin, $credential, ['decision' => 'revoked', 'reason' => 'Lost device before submission']);
    } else {
        $this->staff->update(['is_active' => false]);
    }
    expect(fn () => $service->submit($this->staff, $this->employee, 'check_in', ['challenge_id' => $challenge->id, 'signature' => ($this->sign)($challenge->payload)], $this->request))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->assertDatabaseCount('hr_attendance_events', 0);
})->with([true, false]);

it('rolls back challenge consumption when the attendance sequence is invalid', function (): void {
    $credential = ($this->approved)();
    ($this->allowTestGateway)();
    $service = app(SecureAttendance::class);
    $challenge = $service->challenge($this->staff, $this->employee, ['credential_id' => $credential->id, 'action' => 'check_in'], $this->request);
    $other = $service->challenge($this->staff, $this->employee, ['credential_id' => $credential->id, 'action' => 'check_in'], $this->request);
    $service->submit($this->staff, $this->employee, 'check_in', ['challenge_id' => $other->id, 'signature' => ($this->sign)($other->payload)], $this->request);
    expect(fn () => $service->submit($this->staff, $this->employee, 'check_in', ['challenge_id' => $challenge->id, 'signature' => ($this->sign)($challenge->payload)], $this->request))->toThrow(ValidationException::class);
    expect($challenge->fresh()->consumed_at)->toBeNull();
});

it('exposes only own history and own devices to native HR tokens', function (): void {
    $credential = ($this->register)();
    $hidden = Employee::factory()->create();
    $this->withToken($this->staff->createToken('HR', ['hr:native'])->plainTextToken)
        ->getJson('https://localhost/api/v1/hr/attendance/history?month=2026-10&employee_id='.$hidden->id)
        ->assertOk()->assertJsonCount(31, 'data')->assertJsonMissingPath('data.0.source_fingerprint')->assertJsonMissingPath('data.0.employee_number');
    $this->getJson('https://localhost/api/v1/hr/devices/status')->assertOk()->assertJsonPath('data.0.credential_id', $credential->id)->assertJsonMissingPath('data.0.public_key');
});

it('limits the device administration page to an explicitly authorized HR scope', function (): void {
    ($this->register)();
    $this->actingAs($this->staff)->get(route('hr.attendance.devices'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('hr.attendance.devices'))->assertOk()->assertSee('PHC attendance device')->assertSee('غير متحققة');
});

it('exposes and signs overnight checkout against the original work date after employment ends', function (): void {
    $credential = ($this->approved)();
    ($this->allowTestGateway)();
    $night = WorkSchedule::factory()->create(['name' => 'Night', 'starts_at' => '20:00:00', 'ends_at' => '04:00:00']);
    $assignment = app(AttendanceSetup::class)->assign($this->admin, ['employee_id' => $this->employee->id, 'work_schedule_id' => $night->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 22:00:00', 'Asia/Hebron'));
    $service = app(SecureAttendance::class);
    $challenge = $service->challenge($this->staff, $this->employee, ['credential_id' => $credential->id, 'action' => 'check_in', 'assignment_id' => $assignment->id], $this->request);
    $service->submit($this->staff, $this->employee, 'check_in', ['challenge_id' => $challenge->id, 'signature' => ($this->sign)($challenge->payload)], $this->request);
    $this->employee->update(['ends_on' => '2026-10-12']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 07:00:00', 'Asia/Hebron'));
    $this->withToken($this->staff->createToken('HR', ['hr:native'])->plainTextToken)
        ->getJson('https://localhost/api/v1/hr/attendance/today')->assertOk()
        ->assertJsonPath('data.date', '2026-10-13')->assertJsonPath('data.capture_state.open_session.work_date', '2026-10-12')
        ->assertJsonPath('data.capture_state.options.0.action', 'check_out');
    $response = $this->postJson('https://localhost/api/v1/hr/attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_out', 'assignment_id' => $assignment->id])->assertCreated();
    $bytes = base64_decode($response->json('data.payload'), true);
    expect(json_decode($bytes, true)['work_date'])->toBe('2026-10-12');
    $this->postJson('https://localhost/api/v1/hr/attendance/check-out', ['challenge_id' => $response->json('data.id'), 'signature' => ($this->sign)($bytes)])
        ->assertOk()->assertJsonPath('data.confirmed', true)->assertJsonPath('data.work_date', '2026-10-12');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 08:10:00', 'Asia/Hebron'));
    $this->postJson('https://localhost/api/v1/hr/attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_in'])->assertUnprocessable();
});

it('requires corrections after stale sessions and never offers a second raw checkin', function (): void {
    $credential = ($this->approved)();
    ($this->allowTestGateway)();
    $service = app(SecureAttendance::class);
    $challenge = $service->challenge($this->staff, $this->employee, ['credential_id' => $credential->id, 'action' => 'check_in'], $this->request);
    $service->submit($this->staff, $this->employee, 'check_in', ['challenge_id' => $challenge->id, 'signature' => ($this->sign)($challenge->payload)], $this->request);
    $this->travel(25)->hours();
    $this->withToken($this->staff->createToken('HR', ['hr:native'])->plainTextToken)
        ->getJson('https://localhost/api/v1/hr/attendance/today')->assertOk()
        ->assertJsonPath('data.capture_state.open_session.checkout_eligible', false)->assertJsonCount(0, 'data.capture_state.options');
    $this->postJson('https://localhost/api/v1/hr/attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_out'])->assertUnprocessable();
    $this->postJson('https://localhost/api/v1/hr/attendance/challenge', ['credential_id' => $credential->id, 'action' => 'check_in'])->assertUnprocessable();
});
