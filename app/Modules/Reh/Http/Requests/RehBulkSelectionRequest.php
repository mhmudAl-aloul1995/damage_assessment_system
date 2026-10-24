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
            'filters' => ['required', 'array:kind,q,code_status,creator,pricing_status,building_reference,building_object_id,unit_reference,unit_object_id'],
            'filters.kind' => ['required', 'in:all,duplicate,available,missing'],
            'filters.code_status' => ['nullable', 'string', 'in:all,available,missing'],
            'filters.pricing_status' => ['nullable', 'string', 'in:all,complete,incomplete'],
            'filters.q' => ['nullable', 'string', 'max:100'],
            'filters.building_reference' => ['nullable', 'string', 'max:100'],
            'filters.building_object_id' => ['nullable', 'integer', 'min:0'],
            'filters.unit_reference' => ['nullable', 'string', 'max:100'],
            'filters.unit_object_id' => ['nullable', 'integer', 'min:0'],
            'filters.creator' => ['nullable', 'string', 'max:255'],
            'units' => ['required_if:scope,selected', 'array', 'max:100'],
            'units.*' => ['array:id,revision'],
            'units.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'units.*.revision' => ['required', 'string', 'size:64'],
            'excluded' => ['sometimes', 'array', 'max:100'],
            'excluded.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return ['scope.required' => 'اختر نطاق التحديد.', 'scope.in' => 'نطاق التحديد غير صالح.',
            'filters.required' => 'حدّث الصفحة للحصول على الفلاتر الحالية.', 'filters.kind.required' => 'اختر فلتر المعرّفات.',
            'filters.code_status.in' => 'اختر حالة كود الوحدة: الكل أو متوفر أو غير متوفر.',
            'filters.pricing_status.in' => 'اختر حالة التسعير: الكل أو مكتمل أو غير مكتمل.',
            'filters.building_reference.max' => 'معرّف حصر المبنى لا يتجاوز 100 حرف.', 'filters.unit_reference.max' => 'معرّف حصر الوحدة لا يتجاوز 100 حرف.',
            'filters.building_object_id.integer' => 'معرّف تأهيل المبنى يجب أن يكون عددًا صحيحًا.', 'filters.building_object_id.min' => 'معرّف تأهيل المبنى لا يقبل قيمة سالبة.',
            'filters.unit_object_id.integer' => 'معرّف تأهيل الوحدة يجب أن يكون عددًا صحيحًا.', 'filters.unit_object_id.min' => 'معرّف تأهيل الوحدة لا يقبل قيمة سالبة.',
            'filters.creator.max' => 'اسم منشئ السجل لا يتجاوز 255 حرفًا.',
            'units.required_if' => 'حدد وحدة واحدة على الأقل.', 'units.max' => 'اختيار الصفحة يقتصر على 100 وحدة. استخدم تحديد كل النتائج للنطاق الكامل.',
            'units.*.id.distinct' => 'تكررت وحدة في التحديد.', 'units.*.revision.size' => 'حدّث الصفحة قبل تأكيد التحديد.'];
    }
}
