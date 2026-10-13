<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmploymentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('hr.employees.sensitive') ?? false) && $this->user()->can('manageProfile', $this->route('employee'));
    }

    public function rules(): array
    {
        return ['occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'note' => ['required', 'string', 'min:5', 'max:5000']];
    }

    public function messages(): array
    {
        return ['note.required' => 'الملاحظة مطلوبة.', 'occurred_on.before_or_equal' => 'تاريخ الملاحظة لا يكون مستقبلياً.'];
    }
}
