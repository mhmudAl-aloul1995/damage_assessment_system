<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\DeviceCredential;
use App\Modules\HR\Models\DeviceDecision;
use App\Modules\HR\Models\DeviceRegistrationRequest;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeDevice;
use App\Modules\HR\Notifications\DeviceDecisionNotification;
use App\Modules\HR\Policies\EmployeePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeviceEnrollment
{
    public function __construct(private AttendanceCryptography $crypto) {}

    public function challenge(User $actor, Employee $employee): DeviceRegistrationRequest
    {
        Gate::forUser($actor)->authorize('hr.devices.register');
        $this->assertOwner($actor, $employee);
        $id = (string) Str::uuid();
        $expires = now('UTC')->addMinutes(5);

        return DeviceRegistrationRequest::query()->create([
            'id' => $id, 'employee_id' => $employee->id, 'requested_by' => $actor->id,
            'payload' => $this->crypto->payload('device_registration', $id, $employee->id, ['user_id' => $actor->id], $expires->timestamp),
            'expires_at' => $expires->format('Y-m-d H:i:s'),
        ]);
    }

    public function register(User $actor, Employee $employee, array $data): DeviceCredential
    {
        Gate::forUser($actor)->authorize('hr.devices.register');
        $this->assertOwner($actor, $employee);

        return Employee::query()->getConnection()->transaction(function () use ($actor, $employee, $data): DeviceCredential {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $request = DeviceRegistrationRequest::query()->lockForUpdate()->findOrFail($data['challenge_id']);
            abort_unless($request->employee_id === $employee->id && $request->requested_by === $actor->id, 403);
            $key = $this->crypto->publicKey($data['public_key']);
            $proof = $this->crypto->signature($request->payload, $data['signature'], $key['pem']);
            $this->crypto->attestation($data['attestation_chain'] ?? [], $key['pem']);
            $fingerprint = hash('sha256', json_encode([$data['device_uuid'], $data['label'], $key['fingerprint'], $data['attestation_chain'] ?? [], $proof], JSON_THROW_ON_ERROR));
            if ($request->consumed_at) {
                if (! hash_equals($request->request_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['challenge_id' => 'تم استهلاك التحدي لطلب مختلف.']);
                }

                return DeviceCredential::query()->with('latestDecision')->findOrFail($request->credential_id);
            }
            if ($request->expires_at->lessThanOrEqualTo(now('UTC'))) {
                throw ValidationException::withMessages(['challenge_id' => 'انتهت صلاحية تحدي التسجيل.']);
            }
            if (EmployeeDevice::query()->where('device_uuid', $data['device_uuid'])->exists() || DeviceCredential::query()->where('key_fingerprint', $key['fingerprint'])->exists()) {
                throw ValidationException::withMessages(['public_key' => 'الجهاز أو المفتاح مسجل مسبقاً؛ لا تعِد استخدام مفتاح موظف آخر.']);
            }
            $device = EmployeeDevice::query()->create([
                'employee_id' => $employee->id, 'device_uuid' => $data['device_uuid'], 'label' => $data['label'], 'requested_by' => $actor->id,
            ]);
            $credential = $device->credential()->create([
                'public_key' => $key['pem'], 'key_fingerprint' => $key['fingerprint'], 'algorithm' => 'ECDSA_P256_SHA256_DER',
                'attestation_chain' => $data['attestation_chain'] ?? [], 'attestation_status' => 'unverified',
            ]);
            $request->update(['credential_id' => $credential->id, 'request_fingerprint' => $fingerprint, 'consumed_at' => now('UTC')->format('Y-m-d H:i:s')]);
            $this->audit($actor, 'device.registered', $credential->id);

            return $credential->load('latestDecision');
        });
    }

    public function decide(User $actor, DeviceCredential $credential, array $data): DeviceDecision
    {
        return Employee::query()->getConnection()->transaction(function () use ($actor, $credential, $data): DeviceDecision {
            $employee = Employee::query()->lockForUpdate()->findOrFail($credential->device->employee_id);
            $credential = DeviceCredential::query()->with(['device', 'latestDecision'])->lockForUpdate()->findOrFail($credential->id);
            $decision = $data['decision'];
            if ($decision === 'revoked' && $employee->user_id === $actor->id) {
                abort_unless($actor->is_active, 403);
            } else {
                Gate::forUser($actor)->authorize($decision === 'revoked' ? 'hr.devices.revoke' : 'hr.devices.approve');
                abort_unless((new EmployeePolicy)->view($actor, $employee) && $employee->user_id !== $actor->id, 403);
            }
            if (! in_array($decision, ['approved', 'rejected', 'revoked'], true)) {
                throw ValidationException::withMessages(['decision' => 'قرار الجهاز غير صالح.']);
            }
            $prior = $credential->latestDecision;
            if ($prior?->decision === $decision && (int) $prior->actor_id === $actor->id && $prior->reason === $data['reason']) {
                return $prior;
            }
            $status = $credential->status();
            if (($decision !== 'revoked' && $status !== 'pending') || $status === 'revoked') {
                throw ValidationException::withMessages(['decision' => 'هذا القرار غير مسموح لحالة الجهاز الحالية.']);
            }
            if ($decision === 'approved') {
                $hasApproved = DeviceCredential::query()->whereHas('device', fn ($query) => $query->where('employee_id', $employee->id))
                    ->whereHas('latestDecision', fn ($query) => $query->where('decision', 'approved'))->exists();
                if ($hasApproved) {
                    throw ValidationException::withMessages(['decision' => 'للموظف جهاز معتمد؛ ألغِ اعتماده قبل الموافقة على البديل.']);
                }
            }
            $result = $credential->decisions()->create(['decision' => $decision, 'actor_id' => $actor->id, 'reason' => $data['reason']]);
            $this->audit($actor, 'device.'.$decision, $credential->id);
            $employee->user?->notify(new DeviceDecisionNotification($credential->id, $decision));

            return $result;
        });
    }

    private function assertOwner(User $actor, Employee $employee): void
    {
        abort_unless($actor->is_active && $employee->user_id === $actor->id && $employee->employedOn(now(config('hr.timezone'))->toDateString()), 403);
    }

    private function audit(User $actor, string $action, int $credentialId): void
    {
        AuditLog::query()->create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => 'hr_device_credentials', 'subject_id' => $credentialId, 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
    }
}
