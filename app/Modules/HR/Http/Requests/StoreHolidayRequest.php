<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.setup') ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'work_schedule_id' => ['nullable', 'integer', 'exists:hr_work_schedules,id']];
    }

    public function messages(): array
    {
        return ['name.required' => 'اسم العطلة مطلوب.', 'ends_on.after_or_equal' => 'نهاية العطلة يجب ألا تسبق بدايتها.', 'work_schedule_id.exists' => 'جدول العمل غير موجود.'];
    }
}
