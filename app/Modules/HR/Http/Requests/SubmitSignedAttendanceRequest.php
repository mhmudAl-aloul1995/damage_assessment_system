<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSignedAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.capture') ?? false;
    }

    public function rules(): array
    {
        return ['challenge_id' => ['required', 'uuid'], 'signature' => ['required', 'string', 'max:256']];
    }

    public function messages(): array
    {
        return ['challenge_id.required' => 'تحدي الحضور مطلوب.', 'signature.required' => 'التوقيع مطلوب.'];
    }
}
