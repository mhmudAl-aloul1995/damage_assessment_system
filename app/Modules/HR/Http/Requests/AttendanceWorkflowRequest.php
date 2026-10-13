<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Validation\Rule;

class AttendanceWorkflowRequest extends AttendanceReportRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'state' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])]];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'state.in' => 'حالة المراجعة غير صالحة.'];
    }
}
