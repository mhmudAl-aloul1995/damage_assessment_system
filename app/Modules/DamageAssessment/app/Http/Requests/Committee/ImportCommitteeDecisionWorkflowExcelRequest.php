<?php

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;

class ImportCommitteeDecisionWorkflowExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('manage committee decision content');
    }

    public function rules(): array
    {
        return [
            'committee_decisions_excel' => ['required', 'array', 'min:1'],
            'committee_decisions_excel.*' => ['file', 'mimes:xlsx', 'max:20480'],
            'clear_existing_committee_decisions' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'committee_decisions_excel.required' => 'ملف الإكسل مطلوب.',
            'committee_decisions_excel.array' => 'يمكن رفع ملف إكسل أو أكثر.',
            'committee_decisions_excel.*.mimes' => 'يجب أن تكون ملفات الإكسل بصيغة xlsx.',
        ];
    }
}
