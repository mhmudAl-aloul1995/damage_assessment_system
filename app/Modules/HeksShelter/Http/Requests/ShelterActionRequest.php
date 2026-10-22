<?php

namespace App\Modules\HeksShelter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShelterActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('heks3.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['engineering', 'social', 'return', 'exclude', 'approve', 'reopen', 'boq', 'payment', 'visit', 'complete'])],
            'note' => ['required', 'string', 'min:3', 'max:5000'],
            'amount' => ['required_if:action,payment', 'nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_on' => ['required_if:action,payment', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['required_if:action,payment', 'nullable', 'string', 'max:100'],
            'visited_on' => ['required_if:action,visit', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'completion' => ['required_if:action,visit', 'nullable', 'numeric', 'between:0,100'],
            'lines' => ['required_if:action,boq', 'nullable', 'array', 'min:1', 'max:200'],
            'lines.*' => ['array:description,unit,quantity,unit_price'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['required', 'string', 'max:30'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'هذا الحقل مطلوب.', 'required_if' => 'استكمل بيانات الإجراء.', 'note.min' => 'اكتب سبباً واضحاً للإجراء.', 'in' => 'الإجراء غير صالح.', 'between' => 'نسبة الإنجاز يجب أن تكون بين 0 و100.', 'before_or_equal' => 'لا يمكن تسجيل تاريخ مستقبلي.'];
    }
}
