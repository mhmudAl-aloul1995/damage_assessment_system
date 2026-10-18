<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'project_id' => [Rule::requiredIf(fn (): bool => $this->routeIs('hr.attendance.projects.assignments.store')), 'nullable', 'integer', 'exists:hr_attendance_projects,id'],
            'enable_native_attendance' => ['sometimes', 'boolean'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'الموظف غير موجود.',
            'work_schedule_id.exists' => 'جدول العمل غير موجود.',
            'project_id.exists' => 'المشروع غير موجود.',
            'project_id.required' => 'اختر المشروع لهذا التكليف.',
            'starts_on.required' => 'بداية التكليف مطلوبة.',
            'ends_on.required' => 'حدد نهاية التكليف للحفاظ على تاريخ الورديات.',
            'ends_on.after_or_equal' => 'نهاية التكليف يجب أن تأتي بعد البداية.',
        ];
    }
}
