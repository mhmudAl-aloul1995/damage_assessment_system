<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAttendanceChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.capture') ?? false;
    }

    public function rules(): array
    {
        return [
            'credential_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['check_in', 'check_out'])],
            'assignment_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['credential_id.required' => 'المفتاح المعتمد مطلوب.', 'action.in' => 'عملية الحضور غير صالحة.'];
    }
}
