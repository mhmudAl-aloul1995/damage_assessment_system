<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AdjustmentDecision;
use App\Modules\HR\Models\AttendanceAdjustment;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Notifications\AttendanceDecisionNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceCorrection
{
    public function __construct(private AttendanceCalculator $calculator) {}

    public function submit(User $actor, Employee $employee, array $data): AttendanceAdjustment
    {
        if (! in_array($data['requested_status'], ['present', 'absent', 'excused'], true)) {
            throw ValidationException::withMessages(['requested_status' => 'حالة التصحيح غير صالحة.']);
        }

        return Employee::query()->getConnection()->transaction(function () use ($actor, $employee, $data): AttendanceAdjustment {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            Gate::forUser($actor)->authorize('requestCorrection', $employee);
            $sessions = $this->sessions($employee, $data['work_date'], $data['sessions'] ?? []);
            $prior = AttendanceAdjustment::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($prior) {
                if ($prior->requested_by !== $actor->id || $prior->employee_id !== $employee->id
                    || $prior->work_date->toDateString() !== $data['work_date'] || $prior->requested_status !== $data['requested_status']
                    || $prior->reason !== $data['reason'] || $prior->basis_fingerprint !== $data['basis_fingerprint'] || ($prior->sessions ?? []) !== $sessions
                    || $prior->check_in_at !== (empty($data['check_in']) ? null : CarbonImmutable::parse($data['check_in'], $prior->timezone)->utc()->format('Y-m-d H:i:s'))
                    || $prior->check_out_at !== (empty($data['check_out']) ? null : CarbonImmutable::parse($data['check_out'], $prior->timezone)->utc()->format('Y-m-d H:i:s'))) {
                    throw ValidationException::withMessages(['idempotency_key' => 'معرّف الطلب مستخدم لطلب مختلف.']);
                }

                return $prior;
            }
            $date = $data['work_date'];
            if (! $employee->employedOn($date) || $date > CarbonImmutable::now(config('hr.timezone'))->toDateString()) {
                throw ValidationException::withMessages(['work_date' => 'التاريخ يجب أن يكون ضمن فترة العمل وألا يكون مستقبلياً.']);
            }
            $row = $this->calculator->day($employee, $date);
            if (! hash_equals($row['source_fingerprint'], $data['basis_fingerprint'])) {
                throw ValidationException::withMessages(['basis_fingerprint' => 'تغيّرت بيانات الحضور؛ حدّث الصفحة وأعد الطلب.']);
            }
            if ($employee->adjustments()->where('work_date', $date)->doesntHave('decision')->exists()) {
                throw ValidationException::withMessages(['work_date' => 'يوجد طلب تصحيح قيد المراجعة لهذا اليوم.']);
            }
            $checkIn = empty($data['check_in']) ? null : CarbonImmutable::parse($data['check_in'], $row['timezone'])->utc();
            $checkOut = empty($data['check_out']) ? null : CarbonImmutable::parse($data['check_out'], $row['timezone'])->utc();
            if ((! empty($sessions) && ($checkIn || $checkOut)) || (count($row['sessions']) > 1 && ($checkIn || $checkOut))) {
                throw ValidationException::withMessages(['sessions' => 'لليوم متعدد الورديات، أدخل أوقات كل وردية منفصلة.']);
            }
            if (($checkIn && $checkIn->setTimezone($row['timezone'])->toDateString() !== $date)
                || ($checkOut && (! $checkIn || $checkOut->lessThanOrEqualTo($checkIn) || $checkIn->diffInHours($checkOut) > 24))
                || ($data['requested_status'] !== 'present' && ($checkIn || $checkOut || ! empty($sessions)))
                || ($checkIn?->isFuture() || $checkOut?->isFuture())) {
                throw ValidationException::withMessages(['check_in' => 'أوقات الحضور والانصراف غير متوافقة مع اليوم والحالة، أو تقع في المستقبل.']);
            }
            $adjustment = $employee->adjustments()->create([
                'work_date' => $date, 'requested_status' => $data['requested_status'],
                'check_in_at' => $checkIn?->format('Y-m-d H:i:s'), 'check_out_at' => $checkOut?->format('Y-m-d H:i:s'),
                'timezone' => $row['timezone'], 'reason' => $data['reason'], 'basis_fingerprint' => $row['source_fingerprint'],
                'idempotency_key' => $data['idempotency_key'], 'requested_by' => $actor->id,
                'sessions' => $sessions,
            ]);
            $this->audit($actor, 'attendance.correction_requested', $adjustment->id);

            return $adjustment;
        });
    }

    public function decide(User $actor, AttendanceAdjustment $adjustment, array $data): AdjustmentDecision
    {
        if (! in_array($data['decision'], ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'قرار المراجعة غير صالح.']);
        }

        return Employee::query()->getConnection()->transaction(function () use ($actor, $adjustment, $data): AdjustmentDecision {
            $employee = Employee::query()->lockForUpdate()->findOrFail($adjustment->employee_id);
            $adjustment = AttendanceAdjustment::query()->with('employee')->lockForUpdate()->findOrFail($adjustment->id);
            Gate::forUser($actor)->authorize('decide', $adjustment);
            $prior = $adjustment->decision;
            if ($prior) {
                if ($prior->decision !== $data['decision'] || (int) $prior->reviewed_by !== $actor->id || $prior->note !== $data['note']) {
                    throw ValidationException::withMessages(['decision' => 'تم اتخاذ قرار نهائي لهذا الطلب.']);
                }

                return $prior;
            }
            if ($data['decision'] === 'approved') {
                $current = $this->calculator->day($employee, $adjustment->work_date->toDateString());
                if (! hash_equals($adjustment->basis_fingerprint, $current['source_fingerprint'])) {
                    throw ValidationException::withMessages(['decision' => 'تغيّرت مصادر الحضور بعد تقديم الطلب؛ ارفضه واطلب تصحيحاً جديداً.']);
                }
            }
            $decision = AdjustmentDecision::query()->create([
                'adjustment_id' => $adjustment->id, 'decision' => $data['decision'],
                'reviewed_by' => $actor->id, 'note' => $data['note'], 'reviewed_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
            $this->audit($actor, 'attendance.correction_'.$data['decision'], $adjustment->id);
            app(AttendanceReconciliation::class)->day($employee, $adjustment->work_date->toDateString(), $actor->id);
            $adjustment->requester?->notify(new AttendanceDecisionNotification($adjustment->id, $data['decision']));

            return $decision;
        });
    }

    /** @return array<int, array{assignment_id: int, check_in_at: string, check_out_at: string}> */
    private function sessions(Employee $employee, string $date, array $submitted): array
    {
        $assignments = $employee->assignments()->with(['schedule', 'retirements'])->get()->keyBy('id');
        $sessions = [];
        foreach ($submitted as $item) {
            if (empty($item['check_in']) && empty($item['check_out'])) {
                continue;
            }
            $assignment = $assignments->get($item['assignment_id']);
            if (! $assignment || ! $assignment->covers($date) || empty($item['check_in']) || empty($item['check_out'])) {
                throw ValidationException::withMessages(['sessions' => 'اختر وردية تخص الموظف لهذا اليوم وأدخل وقتي الحضور والانصراف.']);
            }
            $checkIn = CarbonImmutable::parse($item['check_in'], $assignment->schedule->timezone)->utc();
            $checkOut = CarbonImmutable::parse($item['check_out'], $assignment->schedule->timezone)->utc();
            if ($checkIn->setTimezone($assignment->schedule->timezone)->toDateString() !== $date
                || $checkOut->lessThanOrEqualTo($checkIn) || $checkIn->diffInHours($checkOut) > 24 || $checkIn->isFuture() || $checkOut->isFuture()) {
                throw ValidationException::withMessages(['sessions' => 'أوقات إحدى الورديات غير صالحة أو تقع في المستقبل.']);
            }
            if (isset($sessions[$assignment->id])) {
                throw ValidationException::withMessages(['sessions' => 'لا يمكن تكرار الوردية في التصحيح.']);
            }
            $sessions[$assignment->id] = ['assignment_id' => $assignment->id, 'check_in_at' => $checkIn->format('Y-m-d H:i:s'), 'check_out_at' => $checkOut->format('Y-m-d H:i:s')];
        }
        $ordered = collect($sessions)->sortBy('check_in_at')->values();
        foreach ($ordered as $index => $item) {
            if ($index > 0 && $item['check_in_at'] < $ordered[$index - 1]['check_out_at']) {
                throw ValidationException::withMessages(['sessions' => 'أوقات الحضور المصححة لا يمكن أن تتداخل.']);
            }
        }
        ksort($sessions);

        return array_values($sessions);
    }

    private function audit(User $actor, string $action, int $id): void
    {
        AuditLog::query()->create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => 'hr_attendance_adjustments', 'subject_id' => $id, 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
    }
}
