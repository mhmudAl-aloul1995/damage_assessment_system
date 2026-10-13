<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAnyProfiles', \App\Modules\HR\Models\Employee::class) ?? false;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'department_id' => ['nullable', 'integer', 'min:1'],
            'archive' => ['nullable', \Illuminate\Validation\Rule::in(['active', 'archived', 'all'])], 'page' => ['nullable', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['q.max' => 'نص البحث طويل.', 'department_id.integer' => 'معرف القسم غير صالح.'];
    }
}
