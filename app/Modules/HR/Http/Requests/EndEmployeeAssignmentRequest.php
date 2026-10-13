<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndEmployeeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.organization.manage') ?? false;
    }

    public function rules(): array
    {
        return ['ends_on' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return ['ends_on.required' => 'تاريخ النهاية مطلوب.', 'reason.required' => 'سبب إنهاء الفترة مطلوب.'];
    }
}
