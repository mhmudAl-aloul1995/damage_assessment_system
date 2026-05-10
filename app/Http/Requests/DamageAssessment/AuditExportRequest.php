<?php

namespace App\Http\Requests\DamageAssessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'export_type' => ['required', Rule::in(['buildings', 'buildings_with_units'])],
            'building_columns' => ['required', 'array', 'min:1'],
            'building_columns.*' => ['string', 'max:80'],
            'housing_columns' => ['nullable', 'array'],
            'housing_columns.*' => ['string', 'max:80'],
            'building_name' => ['nullable', 'string', 'max:255'],
            'objectid' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'filter_from_date' => ['nullable', 'date'],
            'filter_to_date' => ['nullable', 'date'],
            'status_from_date' => ['nullable', 'date'],
            'status_to_date' => ['nullable', 'date'],
            'engineer_id' => ['nullable', 'array'],
            'engineer_id.*' => ['nullable', 'integer'],
            'lawyer_id' => ['nullable', 'array'],
            'lawyer_id.*' => ['nullable', 'integer'],
            'eng_status' => ['nullable', 'array'],
            'eng_status.*' => ['nullable', 'string', 'max:255'],
            'legal_status' => ['nullable', 'array'],
            'legal_status.*' => ['nullable', 'string', 'max:255'],
            'final_status' => ['nullable', 'array'],
            'final_status.*' => ['nullable', 'string', 'max:255'],
            'field_engineer' => ['nullable', 'array'],
            'field_engineer.*' => ['nullable', 'string', 'max:255'],
            'damage_status' => ['nullable', 'array'],
            'damage_status.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'export_type.required' => 'يرجى اختيار نوع التصدير.',
            'export_type.in' => 'نوع التصدير غير صحيح.',
            'building_columns.required' => 'يرجى اختيار أعمدة المباني المراد تصديرها.',
            'building_columns.min' => 'يرجى اختيار عمود واحد على الأقل من أعمدة المباني.',
        ];
    }
}
