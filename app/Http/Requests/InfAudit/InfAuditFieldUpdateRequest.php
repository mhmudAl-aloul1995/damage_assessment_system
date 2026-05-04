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
                ]),
            ],
            'auditable_id' => ['required', 'integer'],
            'field_name' => ['required', 'string'],
            'field_value' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
