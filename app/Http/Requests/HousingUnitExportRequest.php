<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HousingUnitExportRequest extends FormRequest
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
            'format' => ['nullable', Rule::in(['xlsx', 'XLSX', 'pdf', 'csv', 'CSV'])],
            'housing_columns' => ['nullable', 'array'],
            'housing_columns.*' => ['nullable', 'string'],
            'filters' => ['nullable', 'array'],
            'globalid' => ['nullable', 'string'],
            'parentglobalid' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'صيغة التصدير غير صحيحة.',
        ];
    }
}
