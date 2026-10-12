<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.setup') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:hr_employees,user_id'],
            'employee_number' => ['required', 'string', 'max:50', 'unique:hr_employees,employee_number'],
            'display_name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'الحساب غير موجود.',
            'user_id.unique' => 'الحساب مرتبط بموظف بالفعل.',
            'employee_number.unique' => 'رقم الموظف مستخدم بالفعل.',
            'employee_number.required' => 'رقم الموظف مطلوب.',
            'display_name.required' => 'اسم الموظف مطلوب.',
            'starts_on.required' => 'تاريخ بدء العمل مطلوب.',
            'ends_on.after_or_equal' => 'نهاية العمل يجب أن تأتي بعد البداية.',
        ];
    }
}
