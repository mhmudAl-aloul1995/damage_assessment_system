<?php

declare(strict_types=1);

namespace App\Http\Requests\InfAudit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InfAuditFieldUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'table_type' => [
                'required',
                'string',
                Rule::in([
                    'public_building_table',
                    'public_building_unit_table',
                    'road_facility_table',
                    'road_facility_item_table',
                    'cso_survey_table',
                    'cso_survey_organization_table',
                    'cso_survey_unit_table',
                ]),
            ],
            'auditable_id' => ['required', 'integer'],
            'field_name' => ['required', 'string'],
            'field_value' => ['nullable', 'string'],
            'expected_value' => ['sometimes', 'nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'table_type.required' => 'يرجى تحديد نوع السجل.',
            'table_type.in' => 'نوع السجل غير صالح.',
            'auditable_id.required' => 'يرجى تحديد السجل.',
            'auditable_id.integer' => 'معرّف السجل غير صالح.',
            'field_name.required' => 'يرجى تحديد الحقل.',
            'field_value.string' => 'قيمة الحقل غير صالحة.',
            'expected_value.string' => 'القيمة السابقة غير صالحة.',
        ];
    }
}
