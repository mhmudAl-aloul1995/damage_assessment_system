<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.setup') ?? false;
    }

    public function rules(): array
    {
        return ['cancelled_from' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return ['cancelled_from.required' => 'حدد بداية إلغاء العطلة.', 'reason.required' => 'سبب إلغاء العطلة مطلوب.'];
    }
}
