<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\AttendanceException;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\DailySummary;
use App\Modules\HR\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AttendanceReconciliation
{
    public function __construct(private AttendanceCalculator $calculator) {}

    public function day(Employee $employee, string $date, ?int $actorId = null): DailySummary
    {
        return Employee::query()->getConnection()->transaction(function () use ($employee, $date, $actorId): DailySummary {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $employee->setRelation('legacyAttendances', $employee->legacyAttendances()->whereDate('date', $date)->lockForUpdate()->get());
            $row = $this->calculator->day($employee, $date);
            $summary = DailySummary::query()->firstOrNew(['employee_id' => $employee->id, 'work_date' => $date]);
            $changed = ! $summary->exists || $summary->payload !== $row || $summary->rules_version !== 'attendance-v3';
            if ($changed) {
                $summary->fill([
                    'status' => $row['status'], 'expected_minutes' => $row['expected_minutes'], 'worked_minutes' => $row['worked_minutes'],
                    'late_minutes' => $row['late_minutes'], 'early_minutes' => $row['early_minutes'], 'overtime_minutes' => $row['overtime_minutes'],
                    'source_fingerprint' => $row['source_fingerprint'], 'rules_version' => 'attendance-v3', 'payload' => $row,
                    'reconciled_at' => now('UTC')->format('Y-m-d H:i:s'),
                ])->save();
                AuditLog::query()->create([
                    'actor_id' => $actorId, 'action' => 'attendance.reconciled', 'subject_type' => 'hr_attendance_daily_summaries',
                    'subject_id' => $summary->id, 'metadata' => ['source_fingerprint' => $row['source_fingerprint']], 'created_at' => now('UTC')->format('Y-m-d H:i:s'),
                ]);
            }
            $kind = in_array($row['status'], ['conflict', 'missing_checkout', 'missing_checkin', 'missing_session', 'absence_candidate'], true) ? $row['status'] : null;
            $summary->exceptions()->where('status', 'open')->when($kind, fn ($query) => $query->where('kind', '!=', $kind))
                ->update(['status' => 'resolved', 'resolved_at' => now('UTC')->format('Y-m-d H:i:s')]);
            if ($kind) {
                AttendanceException::query()->updateOrCreate(['daily_summary_id' => $summary->id, 'kind' => $kind], ['status' => 'open', 'resolved_at' => null]);
            }

            return $summary;
        });
    }

    public function range(Builder $employees, string $from, string $to, ?int $actorId = null): int
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);
        if ($end->lessThan($start) || $start->diffInDays($end) > 30 || $to > CarbonImmutable::now(config('hr.timezone'))->toDateString()) {
            throw ValidationException::withMessages(['to' => 'حدد فترة صحيحة لا تتجاوز 31 يوماً ولا تنتهي في المستقبل.']);
        }
        $count = 0;
        $employees->reorder()->chunkById(100, function ($employees) use ($start, $end, $actorId, &$count): void {
            foreach ($employees as $employee) {
                for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                    $this->day($employee, $date->toDateString(), $actorId);
                    $count++;
                }
            }
        });

        return $count;
    }
}
