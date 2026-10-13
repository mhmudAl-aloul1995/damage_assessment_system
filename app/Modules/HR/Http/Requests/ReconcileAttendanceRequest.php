<?php

namespace App\Modules\HR\Http\Requests;

use App\Modules\HR\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReconcileAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('hr.attendance.reconcile') ?? false) && $this->user()->can('viewAny', Employee::class);
    }

    public function rules(): array
    {
        return ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'employee_id' => ['nullable', 'integer', 'exists:hr_employees,id']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 30
                || $this->input('to') > CarbonImmutable::now(config('hr.timezone'))->toDateString()) {
                $validator->errors()->add('to', 'حدد فترة لا تتجاوز 31 يوماً وتنتهي اليوم أو قبله.');
            }
        }];
    }

    public function messages(): array
    {
        return ['to.after_or_equal' => 'نهاية التسوية يجب ألا تسبق بدايتها.', 'from.required' => 'بداية الفترة مطلوبة.', 'to.required' => 'نهاية الفترة مطلوبة.'];
    }
}
