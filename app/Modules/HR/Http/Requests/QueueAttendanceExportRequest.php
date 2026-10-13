<?php

namespace App\Modules\HR\Http\Requests;

class QueueAttendanceExportRequest extends AttendanceReportRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->can('hr.attendance.export');
    }

    public function rules(): array
    {
        return [...parent::rules(), 'idempotency_key' => ['required', 'uuid']];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'idempotency_key.required' => 'معرف طلب التقرير مطلوب.'];
    }
}
