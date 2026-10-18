<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ($this->user()?->is_active ?? false) && $this->user()->can('hr.attendance.setup');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:hr_attendance_projects,code'],
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return ['code.required' => 'أدخل رمز المشروع.', 'code.unique' => 'رمز المشروع مستخدم بالفعل.',
            'name.required' => 'أدخل اسم المشروع.', 'starts_on.required' => 'أدخل تاريخ بداية المشروع.',
            'starts_on.date_format' => 'اختر تاريخ بداية صحيحًا.', 'ends_on.after_or_equal' => 'نهاية المشروع يجب ألا تسبق بدايته.'];
    }
}
