<?php

namespace App\Modules\HR\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.exceptions.manage') ?? false;
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'integer', 'exists:hr_employees,id'], 'work_date' => ['required', 'date_format:Y-m-d'],
            'until_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:work_date'],
            'kind' => ['required', Rule::in(['work_schedule', 'leave', 'excused_absence', 'off_day'])],
            'work_schedule_id' => ['nullable', 'required_if:kind,work_schedule', 'prohibited_unless:kind,work_schedule', 'integer', 'exists:hr_work_schedules,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'سبب الاستثناء أو مرجع الموافقة مطلوب.', 'work_schedule_id.required_if' => 'اختر جدول العمل البديل.', 'employee_id.exists' => 'الموظف غير موجود.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->filled('until_date')
                && CarbonImmutable::parse($this->input('work_date'))->diffInDays(CarbonImmutable::parse($this->input('until_date'))) > 30) {
                $validator->errors()->add('until_date', 'فترة الاستثناء لا تتجاوز 31 يوماً.');
            }
        }];
    }
}
