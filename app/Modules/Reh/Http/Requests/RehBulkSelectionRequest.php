<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RehBulkSelectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Database Officer') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'in:selected,filtered'],
            'filters' => ['required', 'array:kind,q,code_status'],
            'filters.kind' => ['required', 'in:all,duplicate,missing'],
            'filters.code_status' => ['nullable', 'string', 'in:all,available,missing'],
            'filters.q' => ['nullable', 'string', 'max:100'],
            'units' => ['required_if:scope,selected', 'array', 'max:50'],
            'units.*' => ['array:id,revision'],
            'units.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'units.*.revision' => ['required', 'string', 'size:64'],
            'excluded' => ['sometimes', 'array', 'max:50'],
            'excluded.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return ['scope.required' => 'اختر نطاق التحديد.', 'scope.in' => 'نطاق التحديد غير صالح.',
            'filters.required' => 'حدّث الصفحة للحصول على الفلاتر الحالية.', 'filters.kind.required' => 'اختر فلتر المعرّفات.',
            'filters.code_status.in' => 'اختر حالة كود الوحدة: الكل أو متوفر أو غير متوفر.',
            'units.required_if' => 'حدد وحدة واحدة على الأقل.', 'units.max' => 'اختيار الصفحة يقتصر على 50 وحدة. استخدم تحديد كل النتائج للنطاق الكامل.',
            'units.*.id.distinct' => 'تكررت وحدة في التحديد.', 'units.*.revision.size' => 'حدّث الصفحة قبل تأكيد التحديد.'];
    }
}
