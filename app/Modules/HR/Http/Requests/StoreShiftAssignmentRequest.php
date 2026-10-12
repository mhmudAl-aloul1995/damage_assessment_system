<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.setup') ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'work_schedule_id' => ['required', 'integer', 'exists:hr_work_schedules,id'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'الموظف غير موجود.',
            'work_schedule_id.exists' => 'جدول العمل غير موجود.',
            'starts_on.required' => 'بداية التكليف مطلوبة.',
            'ends_on.required' => 'حدد نهاية التكليف للحفاظ على تاريخ الورديات.',
            'ends_on.after_or_equal' => 'نهاية التكليف يجب أن تأتي بعد البداية.',
        ];
    }
}
