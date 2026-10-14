<?php

namespace App\Modules\HR\Http\Requests;

use App\Modules\HR\Models\EmploymentContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmploymentContract::class) ?? false;
    }

    public function rules(): array
    {
        return ['employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'], 'contract_type_id' => ['nullable', 'integer', 'exists:hr_contract_types,id'], 'status' => ['nullable', Rule::in(['active', 'scheduled', 'expired', 'terminated', 'pending', 'rejected'])], 'date' => ['nullable', 'date_format:Y-m-d'], 'expires_before' => ['nullable', 'date_format:Y-m-d'], 'page' => ['nullable', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['status.in' => 'حالة العقد غير صالحة.', 'date.date_format' => 'التاريخ يجب أن يكون YYYY-MM-DD.'];
    }
}
