<?php

declare(strict_types=1);

namespace App\Http\Requests\DamageAssessment;

use Illuminate\Foundation\Http\FormRequest;

class ObjectIdImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'objectids_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ];
    }

    public function messages(): array
    {
        return [
            'objectids_file.required' => __('ui.exports.objectid_import_file_required'),
            'objectids_file.file' => __('ui.exports.objectid_import_file_invalid'),
            'objectids_file.mimes' => __('ui.exports.objectid_import_file_mimes'),
        ];
    }
}
