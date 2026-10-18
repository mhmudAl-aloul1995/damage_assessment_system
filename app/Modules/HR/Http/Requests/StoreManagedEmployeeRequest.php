<?php

namespace App\Modules\HR\Http\Requests;

class StoreManagedEmployeeRequest extends StoreEmployeeRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('hr.employees.manage') ?? false) && $this->user()->can('hr.employees.view');
    }

    public function rules(): array
    {
        return [...parent::rules(),
            'employee_number' => ['required', 'string', 'max:50', 'unique:hr_employees,employee_number'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:hr_employees,user_id'],
            'user_email' => ['prohibited'],
            'enable_native_attendance' => ['prohibited'],
        ];
    }
}
