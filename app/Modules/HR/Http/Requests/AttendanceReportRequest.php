<?php

namespace App\Modules\HR\Http\Requests;

use App\Modules\HR\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('viewAny', Employee::class) ?? false)
            && (! $this->routeIs('hr.attendance.export') || $this->user()->can('hr.attendance.export'));
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m', 'prohibits:date'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'اختر تاريخاً صحيحاً.',
            'month.date_format' => 'اختر شهراً صحيحاً.',
            'month.prohibits' => 'اختر اليوم أو الشهر، وليس كليهما.',
            'employee_id.integer' => 'رقم الموظف غير صالح.',
            'search.string' => 'أدخل اسم الموظف أو رقمه كنص.',
            'search.max' => 'يجب ألا يتجاوز البحث 100 حرف.',
        ];
    }
}
