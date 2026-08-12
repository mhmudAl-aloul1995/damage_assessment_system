<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Requests;

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
            'objectid_filter_target' => ['nullable', 'string', 'in:building,housing_unit'],
            'objectids_file' => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt', 'required_without:objectids_text'],
            'objectids_text' => ['nullable', 'string', 'required_without:objectids_file'],
        ];
    }

    public function messages(): array
    {
        return [
            'objectids_file.required_without' => __('ui.exports.objectid_import_input_required'),
            'objectids_file.file' => __('ui.exports.objectid_import_file_invalid'),
            'objectids_file.mimes' => __('ui.exports.objectid_import_file_mimes'),
            'objectids_text.required_without' => __('ui.exports.objectid_import_input_required'),
            'objectid_filter_target.in' => __('ui.exports.objectid_import_target_invalid'),
        ];
    }
}
