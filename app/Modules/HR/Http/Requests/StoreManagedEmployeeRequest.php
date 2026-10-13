<?php

namespace App\Modules\HR\Http\Requests;

class StoreManagedEmployeeRequest extends StoreEmployeeRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('hr.employees.manage') ?? false) && $this->user()->can('hr.employees.view');
    }
}
