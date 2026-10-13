<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('hr.organization.manage') ?? false) && $this->user()->can('manageProfile', $this->route('employee'));
    }

    public function rules(): array
    {
        return ['department_id' => ['required', 'integer', 'exists:hr_departments,id'], 'job_title_id' => ['required', 'integer', 'exists:hr_job_titles,id'],
            'office_location_id' => ['nullable', 'integer', 'exists:hr_office_locations,id'], 'cost_center_id' => ['nullable', 'integer', 'exists:hr_cost_centers,id'],
            'manager_employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'], 'primary' => ['required', 'boolean'],
            'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on']];
    }

    public function messages(): array
    {
        return ['department_id.required' => 'القسم مطلوب.', 'job_title_id.required' => 'المسمى الوظيفي مطلوب.', 'ends_on.after_or_equal' => 'نهاية التكليف تسبق بدايته.'];
    }
}
