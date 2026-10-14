<?php

namespace App\Modules\DamageAssessment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditEngineerChangeLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'engineer_id' => ['nullable', 'integer', 'min:1'],
            'field_name' => ['nullable', 'string', 'max:255'],
            'record_type' => ['nullable', Rule::in(['building_table', 'housing_table'])],
            'search_value' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'required_with:to_date', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'required_with:from_date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'engineer_id.integer' => 'يرجى اختيار مهندس صحيح.',
            'engineer_id.min' => 'يرجى اختيار مهندس صحيح.',
            'field_name.string' => 'اسم الحقل غير صحيح.',
            'field_name.max' => 'اسم الحقل طويل جداً.',
            'record_type.in' => 'نوع السجل غير صحيح.',
            'search_value.string' => 'قيمة البحث غير صحيحة.',
            'search_value.max' => 'يجب ألا يتجاوز البحث 255 حرفاً.',
            'from_date.required_with' => 'يرجى تحديد بداية فترة التعديل.',
            'from_date.date_format' => 'تاريخ بداية الفترة غير صحيح.',
            'to_date.required_with' => 'يرجى تحديد نهاية فترة التعديل.',
            'to_date.date_format' => 'تاريخ نهاية الفترة غير صحيح.',
            'to_date.after_or_equal' => 'يجب أن يكون تاريخ النهاية مساوياً لتاريخ البداية أو بعده.',
        ];
    }
}
