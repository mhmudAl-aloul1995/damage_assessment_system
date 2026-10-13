<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'rejected', 'revoked'])], 'reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'سبب القرار مطلوب.', 'decision.in' => 'قرار الجهاز غير صالح.'];
    }
}
