<?php

namespace App\Modules\HR\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('hr.attendance.*') && is_string($this->input('user_email')) && $this->filled('user_email') && ! $this->filled('user_id') && $this->user()?->can('hr.attendance.setup')) {
            $this->merge(['user_id' => User::query()->where('email', $this->input('user_email'))->value('id')]);
        }
    }

    public function authorize(): bool
    {
        return ($this->user()?->is_active ?? false) && $this->user()->can('hr.attendance.setup');
    }

    public function rules(): array
    {
        return [
            'user_id' => [Rule::requiredIf(fn (): bool => $this->boolean('enable_native_attendance')), 'nullable', 'integer', 'exists:users,id', 'unique:hr_employees,user_id'],
            'user_email' => ['bail', 'nullable', 'string', 'email', 'max:255', 'exists:users,email', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->filled('user_id') && (int) User::query()->where('email', $value)->value('id') !== (int) $this->input('user_id')) {
                    $fail('البريد الإلكتروني لا يطابق حساب المستخدم المحدد.');
                }
            }],
            'enable_native_attendance' => ['sometimes', 'boolean'],
            'supervisor_user_id' => ['nullable', 'integer', 'exists:users,id', 'different:user_id'],
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:hr_employees,employee_number'],
            'display_name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'الحساب غير موجود.',
            'user_id.required' => 'اربط حساب مستخدم لتفعيل الحضور عبر التطبيق.',
            'user_email.exists' => 'لا يوجد حساب مسجل بهذا البريد الإلكتروني.',
            'user_email.email' => 'أدخل بريدًا إلكترونيًا صحيحًا للحساب.',
            'user_id.unique' => 'الحساب مرتبط بموظف بالفعل.',
            'employee_number.unique' => 'رقم الموظف مستخدم بالفعل.',
            'employee_number.required' => 'رقم الموظف مطلوب.',
            'display_name.required' => 'اسم الموظف مطلوب.',
            'starts_on.required' => 'تاريخ بدء العمل مطلوب.',
            'ends_on.after_or_equal' => 'نهاية العمل يجب أن تأتي بعد البداية.',
        ];
    }
}
