<?php

namespace App\Modules\HR\Http\Requests;

use App\Modules\HR\Models\EmploymentContract;
use App\Modules\HR\Services\EmployeeOrganizationScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('employee_number')) {
            $id = app(EmployeeOrganizationScope::class)->query($this->user(), 'hr.contracts')->where('employee_number', $this->input('employee_number'))->value('id');
            $this->merge(['employee_id' => $id ?? 0]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('hr.contracts.manage') && $this->user()?->can('viewAny', EmploymentContract::class);
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'integer', 'exists:hr_employees,id'], 'number' => ['required', 'string', 'max:80'], 'primary' => ['required', 'boolean'],
            ...$this->revisionRules()];
    }

    public function revisionRules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'contract_type_id' => ['required', 'integer', 'exists:hr_contract_types,id'],
            'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'document_id' => ['nullable', 'integer', 'exists:hr_employee_documents,id'], 'reason' => ['required', 'string', 'min:5', 'max:5000'],
            'financial_terms' => [Rule::prohibitedIf(! $this->user()->can('hr.contracts.sensitive')), 'nullable', 'array:salary,currency,currency_scale,basis,benefits'],
            'financial_terms.salary' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/D'],
            'financial_terms.currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/D'], 'financial_terms.currency_scale' => ['nullable', 'integer', 'min:0', 'max:4'],
            'financial_terms.basis' => ['nullable', Rule::in(['monthly', 'daily', 'hourly'])], 'financial_terms.benefits' => ['nullable', 'string', 'max:5000']];
    }

    public function messages(): array
    {
        return ['reason.min' => 'وضح سبب المقترح بخمسة أحرف على الأقل.', 'financial_terms.prohibited' => 'البيانات المالية تحتاج صلاحية مستقلة.', 'financial_terms.salary.regex' => 'استخدم قيمة نقدية موجبة دون فواصل آلاف.', 'request_key.uuid' => 'معرف الطلب غير صالح.', 'ends_on.after_or_equal' => 'نهاية العقد تسبق بدايته.'];
    }
}
