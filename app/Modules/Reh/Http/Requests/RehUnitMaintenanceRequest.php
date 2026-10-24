<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RehUnitMaintenanceRequest extends FormRequest
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
            'kind' => ['nullable', 'string', 'in:all,duplicate,available,missing'],
            'code_status' => ['nullable', 'string', 'in:all,available,missing'],
            'pricing_status' => ['nullable', 'string', 'in:all,complete,incomplete'],
            'creator' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'q' => ['nullable', 'string', 'max:100'],
            'building_reference' => ['nullable', 'string', 'max:100'],
            'building_object_id' => ['nullable', 'integer', 'min:0'],
            'unit_reference' => ['nullable', 'string', 'max:100'],
            'unit_object_id' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'history_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['building_reference.max' => 'معرّف حصر المبنى لا يتجاوز 100 حرف.', 'unit_reference.max' => 'معرّف حصر الوحدة لا يتجاوز 100 حرف.',
            'building_object_id.integer' => 'معرّف تأهيل المبنى يجب أن يكون عددًا صحيحًا.', 'building_object_id.min' => 'معرّف تأهيل المبنى لا يقبل قيمة سالبة.',
            'unit_object_id.integer' => 'معرّف تأهيل الوحدة يجب أن يكون عددًا صحيحًا.', 'unit_object_id.min' => 'معرّف تأهيل الوحدة لا يقبل قيمة سالبة.',
            'kind.in' => 'اختر حالة معرّف الحصر: الكل أو متوفر أو غير متوفر أو المكرر فقط.', 'code_status.in' => 'اختر حالة كود الوحدة: الكل أو متوفر أو غير متوفر.', 'pricing_status.in' => 'اختر حالة التسعير: الكل أو مكتمل أو غير مكتمل.', 'creator.max' => 'اسم منشئ السجل لا يتجاوز 255 حرفًا.', 'per_page.in' => 'اختر عدد الصفوف: 25 أو 50 أو 100.', 'q.max' => 'البحث لا يتجاوز 100 حرف.', 'page.min' => 'رقم الصفحة غير صالح.'];
    }
}
