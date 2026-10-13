<?php

namespace App\Console\Commands;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\AttendanceReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ReconcileHrAttendance extends Command
{
    protected $signature = 'hr:reconcile-attendance {--from= : بداية الفترة YYYY-MM-DD} {--to= : نهاية الفترة YYYY-MM-DD} {--employee= : معرّف هوية الحضور}';

    protected $description = 'تسوية ملخصات الحضور والاستثناءات مع الحفاظ على السجلات الأصلية';

    public function handle(AttendanceReconciliation $reconciliation): int
    {
        $today = CarbonImmutable::now(config('hr.timezone'))->toDateString();
        $data = ['from' => $this->option('from') ?: $today, 'to' => $this->option('to') ?: $today, 'employee' => $this->option('employee')];
        $validator = Validator::make($data, ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'], 'employee' => ['nullable', 'integer', 'exists:hr_employees,id']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        try {
            $count = $reconciliation->range(Employee::query()->when($data['employee'], fn ($query) => $query->whereKey($data['employee'])), $data['from'], $data['to']);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->error($exception->validator->errors()->first());

            return self::FAILURE;
        }
        $this->info('تمت تسوية '.$count.' سجل يومي.');

        return self::SUCCESS;
    }
}
