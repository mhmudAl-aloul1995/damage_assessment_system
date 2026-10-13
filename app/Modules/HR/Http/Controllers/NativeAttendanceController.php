<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileLoginRequest;
use App\Models\User;
use App\Modules\HR\Http\Requests\AttendanceReportRequest;
use App\Modules\HR\Http\Requests\CreateAttendanceChallengeRequest;
use App\Modules\HR\Http\Requests\DecideDeviceRequest;
use App\Modules\HR\Http\Requests\RegisterDeviceRequest;
use App\Modules\HR\Http\Requests\SubmitSignedAttendanceRequest;
use App\Modules\HR\Http\Resources\AttendanceChallengeResource;
use App\Modules\HR\Http\Resources\AttendanceDeviceResource;
use App\Modules\HR\Http\Resources\NativeAttendanceResource;
use App\Modules\HR\Models\DeviceCredential;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Modules\HR\Services\AttendanceReport;
use App\Modules\HR\Services\DeviceEnrollment;
use App\Modules\HR\Services\NetworkEligibilityService;
use App\Modules\HR\Services\SecureAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class NativeAttendanceController extends Controller
{
    public function login(MobileLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();
        if (! $user || ! $user->is_active || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $employee = $this->employee($user);
        $expires = now()->addHours(8);
        $token = $user->createToken('PHC HR '.$request->validated('device_name'), ['hr:native'], $expires);

        return response()->json(['access_token' => $token->plainTextToken, 'expires_at' => $expires->toIso8601String(), 'employee' => ['id' => $employee->id, 'user_id' => $user->id, 'name' => $employee->display_name]]);
    }

    public function me(Request $request, NetworkEligibilityService $network): JsonResponse
    {
        $employee = $this->employee($request->user());

        return response()->json(['data' => [
            'id' => $employee->id, 'name' => $employee->display_name, 'employee_number' => $employee->employee_number,
            'capture_enabled' => (bool) config('hr.attendance_enabled') && $network->verified(),
            'blockers' => $network->blockers(),
        ]]);
    }

    public function today(Request $request, AttendanceCalculator $calculator): NativeAttendanceResource
    {
        return new NativeAttendanceResource($calculator->day($this->employee($request->user()), now(config('hr.timezone'))->toDateString()));
    }

    public function history(AttendanceReportRequest $request, AttendanceReport $report): AnonymousResourceCollection
    {
        $employee = $this->employee($request->user());
        $filters = $request->safe()->only(['date', 'month']);
        $employees = $report->query($request->user(), [...$filters, 'employee_id' => $employee->id])->get();

        return NativeAttendanceResource::collection($report->rows($employees, $filters));
    }

    public function registrationChallenge(Request $request, DeviceEnrollment $enrollment): AttendanceChallengeResource
    {
        return new AttendanceChallengeResource($enrollment->challenge($request->user(), $this->employee($request->user())));
    }

    public function register(RegisterDeviceRequest $request, DeviceEnrollment $enrollment): AttendanceDeviceResource
    {
        return new AttendanceDeviceResource($enrollment->register($request->user(), $this->employee($request->user()), $request->validated())->load('device'));
    }

    public function devices(Request $request): AnonymousResourceCollection
    {
        $employee = $this->employee($request->user());

        return AttendanceDeviceResource::collection(DeviceCredential::query()->with(['device', 'latestDecision'])->whereHas('device', fn ($query) => $query->where('employee_id', $employee->id))->latest('id')->get());
    }

    public function revoke(DecideDeviceRequest $request, DeviceCredential $credential, DeviceEnrollment $enrollment): AttendanceDeviceResource
    {
        $employee = $this->employee($request->user());
        abort_unless($credential->device->employee_id === $employee->id && $request->validated('decision') === 'revoked', 403);
        $enrollment->decide($request->user(), $credential, $request->validated());

        return new AttendanceDeviceResource($credential->fresh()->load(['device', 'latestDecision']));
    }

    public function challenge(CreateAttendanceChallengeRequest $request, SecureAttendance $attendance): AttendanceChallengeResource
    {
        return new AttendanceChallengeResource($attendance->challenge($request->user(), $this->employee($request->user()), $request->validated(), $request));
    }

    public function checkIn(SubmitSignedAttendanceRequest $request, SecureAttendance $attendance): JsonResponse
    {
        return $this->record($request, $attendance, 'check_in');
    }

    public function checkOut(SubmitSignedAttendanceRequest $request, SecureAttendance $attendance): JsonResponse
    {
        return $this->record($request, $attendance, 'check_out');
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function record(SubmitSignedAttendanceRequest $request, SecureAttendance $attendance, string $action): JsonResponse
    {
        $event = $attendance->submit($request->user(), $this->employee($request->user()), $action, $request->validated(), $request);

        return response()->json(['data' => ['event_id' => $event->id, 'action' => $event->action, 'work_date' => $event->work_date->toDateString(), 'occurred_at_utc' => $event->occurred_at, 'confirmed' => true]]);
    }

    private function employee(User $user): Employee
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();
        abort_unless($employee && $employee->employedOn(now(config('hr.timezone'))->toDateString()), 403, 'لا يوجد ربط موظف فعال لهذا الحساب.');
        Gate::forUser($user)->authorize('view', $employee);

        return $employee;
    }
}
