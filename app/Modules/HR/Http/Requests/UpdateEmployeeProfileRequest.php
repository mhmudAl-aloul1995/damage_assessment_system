<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageProfile', $this->route('employee')) ?? false;
    }

    public function rules(): array
    {
        $privateBlocked = \Illuminate\Validation\Rule::prohibitedIf(! $this->user()->can('hr.employees.sensitive'));

        return ['display_name' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'],
            'work_email' => ['nullable', 'email', 'max:255'], 'work_phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => [$privateBlocked, 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'emergency_contact' => [$privateBlocked, 'nullable', 'array:name,relationship,phone'],
            'emergency_contact.name' => ['nullable', 'string', 'max:150'], 'emergency_contact.relationship' => ['nullable', 'string', 'max:80'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:50']];
    }

    public function messages(): array
    {
        return ['display_name.required' => 'اسم الموظف مطلوب.', 'birth_date.prohibited' => 'البيانات الشخصية تتطلب صلاحية مستقلة.', 'work_email.email' => 'البريد الوظيفي غير صالح.'];
    }
}
