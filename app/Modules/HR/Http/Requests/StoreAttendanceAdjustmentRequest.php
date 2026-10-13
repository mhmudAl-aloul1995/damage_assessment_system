<?php

namespace App\Modules\HR\Http\Requests;

use App\Modules\HR\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee && ($this->user()?->can('requestCorrection', $employee) ?? false);
    }

    public function rules(): array
    {
        return ['work_date' => ['required', 'date_format:Y-m-d'],
            'requested_status' => ['required', Rule::in(['present', 'absent', 'excused'])],
            'check_in' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'check_out' => ['nullable', 'date_format:Y-m-d\\TH:i', 'required_with:check_in', 'after:check_in'],
            'sessions' => ['nullable', 'array', 'max:20'],
            'sessions.*.assignment_id' => ['required', 'integer', 'distinct', 'exists:hr_shift_assignments,id'],
            'sessions.*.check_in' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'sessions.*.check_out' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'basis_fingerprint' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'uuid']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'اشرح سبب التصحيح.', 'check_out.after' => 'الانصراف يجب أن يأتي بعد الحضور.', 'basis_fingerprint.required' => 'حدّث صفحة التصحيح قبل الإرسال.', 'check_out.required_with' => 'أدخل وقت الانصراف عند تصحيح الأوقات.'];
    }
}
