<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AttendanceChallenge;
use App\Modules\HR\Models\AttendanceEvent;
use App\Modules\HR\Models\DeviceCredential;
use App\Modules\HR\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecureAttendance
{
    public function __construct(
        private AttendanceCryptography $crypto,
        private AttendanceGatewayService $gateway,
        private AttendanceRecorder $recorder,
    ) {}

    public function challenge(User $actor, Employee $employee, array $data, Request $request): AttendanceChallenge
    {
        $this->gateway->assertCaptureAllowed($request);
        Gate::forUser($actor)->authorize('hr.attendance.capture');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $employee, $data): AttendanceChallenge {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $credential = $this->credential($actor, $employee, $data['credential_id']);
            if (! in_array($data['action'], ['check_in', 'check_out'], true)) {
                throw ValidationException::withMessages(['action' => 'عملية الحضور غير صالحة.']);
            }
            $option = $this->recorder->eligibleOption($employee, $data['action'], $data['assignment_id'] ?? null);
            $assignmentId = $option['assignment_id'];
            $id = (string) Str::uuid();
            $expires = now('UTC')->addSeconds(60);

            return AttendanceChallenge::query()->create([
                'id' => $id, 'employee_id' => $employee->id, 'credential_id' => $credential->id,
                'action' => $data['action'], 'shift_assignment_id' => $assignmentId,
                'payload' => $this->crypto->payload('attendance', $id, $employee->id, [
                    'credential_id' => $credential->id, 'action' => $data['action'], 'assignment_id' => $assignmentId, 'work_date' => $option['work_date'],
                ], $expires->timestamp),
                'expires_at' => $expires->format('Y-m-d H:i:s'),
            ]);
        });
    }

    public function submit(User $actor, Employee $employee, string $action, array $data, Request $request): AttendanceEvent
    {
        $this->gateway->assertCaptureAllowed($request);
        Gate::forUser($actor)->authorize('hr.attendance.capture');

        return Employee::query()->getConnection()->transaction(function () use ($actor, $employee, $action, $data, $request): AttendanceEvent {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $challenge = AttendanceChallenge::query()->findOrFail($data['challenge_id']);
            $credential = $this->credential($actor, $employee, $challenge->credential_id);
            $challenge = AttendanceChallenge::query()->lockForUpdate()->findOrFail($challenge->id);
            abort_unless($challenge->employee_id === $employee->id && $challenge->action === $action, 403);
            $signature = $this->crypto->signature($challenge->payload, $data['signature'], $credential->public_key);
            if ($challenge->consumed_at) {
                if (! hash_equals($challenge->signature_fingerprint, $signature) || $challenge->consumed_at->addMinutes(10)->lessThanOrEqualTo(now('UTC'))) {
                    throw ValidationException::withMessages(['challenge_id' => 'التحدي مستهلك أو انتهت مهلة إعادة الطلب.']);
                }

                return AttendanceEvent::query()->findOrFail($challenge->event_id);
            }
            if ($challenge->expires_at->lessThanOrEqualTo(now('UTC'))) {
                throw ValidationException::withMessages(['challenge_id' => 'انتهت صلاحية تحدي الحضور.']);
            }
            $payload = json_decode($challenge->payload, true, flags: JSON_THROW_ON_ERROR);
            $event = $this->recorder->recordSigned($employee, $action, $challenge->id, $challenge->shift_assignment_id, $request, $payload['work_date']);
            $challenge->update([
                'event_id' => $event->id, 'signature_fingerprint' => $signature,
                'consumed_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);

            return $event;
        });
    }

    private function credential(User $actor, Employee $employee, int $id): DeviceCredential
    {
        $actor = User::query()->findOrFail($actor->id);
        abort_unless($actor->is_active && $employee->user_id === $actor->id, 403);
        $credential = DeviceCredential::query()->with(['device', 'latestDecision'])->lockForUpdate()->findOrFail($id);
        abort_unless($credential->device->employee_id === $employee->id && $credential->status() === 'approved', 403);

        return $credential;
    }
}
