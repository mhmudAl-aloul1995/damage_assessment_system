<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.organization.manage') ?? false;
    }

    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', 'exists:users,id'], 'department_id' => ['required', 'integer', 'exists:hr_departments,id'],
            'include_descendants' => ['required', 'boolean'], 'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on']];
    }

    public function messages(): array
    {
        return ['user_id.required' => 'الحساب المسؤول عن النطاق مطلوب.', 'department_id.required' => 'القسم مطلوب.'];
    }
}
