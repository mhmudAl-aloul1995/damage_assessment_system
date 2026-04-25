<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class FieldEngineerReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'assignedto' => ['nullable', 'string', 'max:255'],
            'municipalitie' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'building_damage_status' => ['nullable', 'string', 'max:255'],
            'engineer_status' => ['nullable', 'string', 'max:255'],
            'legal_status' => ['nullable', 'string', 'max:255'],
            'final_status' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.date' => __('multilingual.field_engineer_report.validation.date_invalid'),
            'to_date.date' => __('multilingual.field_engineer_report.validation.date_invalid'),
            'to_date.after_or_equal' => __('multilingual.field_engineer_report.validation.to_date_after_or_equal'),
        ];
    }
}
