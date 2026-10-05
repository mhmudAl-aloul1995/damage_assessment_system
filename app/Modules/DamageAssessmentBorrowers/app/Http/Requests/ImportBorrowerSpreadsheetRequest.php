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
            'borrowers_file' => ['nullable', 'required_without:boq_file', 'file', 'mimes:xlsx', 'max:20480'],
            'sheet_name' => ['nullable', 'string', 'max:100'],
            'boq_file' => ['nullable', 'required_without:borrowers_file', 'file', 'mimes:xlsx', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'borrowers_file.required_without' => 'يرجى اختيار ملف Excel للمستفيدين أو ملف أسعار BOQ.',
            'borrowers_file.file' => 'ملف الاستيراد غير صالح.',
            'borrowers_file.mimes' => 'يجب أن يكون ملف الاستيراد بصيغة XLSX.',
            'borrowers_file.max' => 'حجم ملف الاستيراد يجب ألا يتجاوز 20 ميغابايت.',
            'boq_file.required_without' => 'يرجى اختيار ملف Excel للمستفيدين أو ملف أسعار BOQ.',
            'boq_file.file' => 'ملف أسعار BOQ غير صالح.',
            'boq_file.mimes' => 'يجب أن يكون ملف أسعار BOQ بصيغة XLSX.',
            'boq_file.max' => 'حجم ملف أسعار BOQ يجب ألا يتجاوز 20 ميغابايت.',
        ];
    }
}
