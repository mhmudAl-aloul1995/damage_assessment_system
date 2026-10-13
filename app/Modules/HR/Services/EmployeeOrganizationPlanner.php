<?php

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class EmployeeOrganizationPlanner
{
    public function validate(Employee $employee, array $data): void
    {
        $start = $data['starts_on'];
        $end = $data['ends_on'] ?? null;
        if ($end !== null && $end < $start) {
            throw ValidationException::withMessages(['ends_on' => 'نهاية التكليف تسبق بدايته.']);
        }
        if (! $employee->employedOn($start) || ($end !== null && ! $employee->employedOn($end)) || ($employee->ends_on !== null && $end === null)) {
            throw ValidationException::withMessages(['starts_on' => 'يجب أن يقع التكليف ضمن فترة العمل، وأن تنتهي حدوده قبل نهاية العمل المحددة.']);
        }
        if (($data['manager_employee_id'] ?? null) === $employee->id) {
            throw ValidationException::withMessages(['manager_employee_id' => 'لا يمكن أن يكون الموظف مسؤولاً عن نفسه.']);
        }
        $assignments = EmployeeAssignment::query()->where('primary', true)->with('latestEnding')->get();
        if (! ($data['primary'] ?? true)) {
            return;
        }
        foreach ($assignments->where('employee_id', $employee->id) as $assignment) {
            $otherEnd = $assignment->effectiveEndsOn();
            if (($end === null || $assignment->starts_on->toDateString() <= $end) && ($otherEnd === null || $otherEnd >= $start)) {
                throw ValidationException::withMessages(['starts_on' => 'يتداخل التكليف الأساسي مع تكليف آخر؛ أنهِ التكليف السابق بتاريخ مناسب أولاً.']);
            }
        }
        $points = [$start];
        foreach ($assignments as $assignment) {
            $points[] = $assignment->starts_on->toDateString();
            if ($assignment->effectiveEndsOn() !== null) {
                $points[] = CarbonImmutable::parse($assignment->effectiveEndsOn())->addDay()->toDateString();
            }
        }
        foreach (array_unique($points) as $date) {
            if ($date < $start || ($end !== null && $date > $end)) {
                continue;
            }
            $graph = [];
            foreach ($assignments as $assignment) {
                if ($assignment->covers($date)) {
                    $graph[$assignment->employee_id] = $assignment->manager_employee_id;
                }
            }
            $graph[$employee->id] = isset($data['manager_employee_id']) ? (int) $data['manager_employee_id'] : null;
            $next = $graph[$employee->id];
            $visited = [$employee->id => true];
            while ($next !== null) {
                if (isset($visited[$next])) {
                    throw ValidationException::withMessages(['manager_employee_id' => 'يسبب التكليف دورة في التسلسل الإداري خلال فترته.']);
                }
                $visited[$next] = true;
                $next = $graph[$next] ?? null;
            }
        }
    }
}
