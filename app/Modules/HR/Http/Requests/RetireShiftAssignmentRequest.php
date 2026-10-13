<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetireShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.setup') ?? false;
    }

    public function rules(): array
    {
        return ['ends_on' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return ['ends_on.required' => 'حدد آخر يوم للتكليف.', 'reason.required' => 'سبب إنهاء التكليف مطلوب.'];
    }
}
