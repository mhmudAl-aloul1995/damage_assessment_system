<?php

namespace App\Http\Requests\Modules\DamageAssessmentBorrowers;

use Illuminate\Foundation\Http\FormRequest;

class ImportBorrowerSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'Field Engineer',
            'Database Officer',
            'Project Officer',
            'Project Officer - Borrowers',
            'Area Manager',
            'Team Leader',
            'Team Leader -INF',
        ]) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'borrowers_file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'sheet_name' => ['nullable', 'string', 'max:100'],
            'boq_file' => ['nullable', 'file', 'mimes:xlsx', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'borrowers_file.required' => 'يرجى اختيار ملف Excel للاستيراد.',
            'borrowers_file.file' => 'ملف الاستيراد غير صالح.',
            'borrowers_file.mimes' => 'يجب أن يكون ملف الاستيراد بصيغة XLSX.',
            'borrowers_file.max' => 'حجم ملف الاستيراد يجب ألا يتجاوز 20 ميغابايت.',
        ];
    }
}
