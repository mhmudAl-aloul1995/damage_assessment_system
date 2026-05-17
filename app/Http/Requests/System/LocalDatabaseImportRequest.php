<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class LocalDatabaseImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'import_source' => ['required', 'in:upload,local_path'],
            'sql_file' => ['required_if:import_source,upload', 'file', 'extensions:sql,txt'],
            'local_path' => ['required_if:import_source,local_path', 'nullable', 'string'],
            'confirm_database' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'sql_file.required' => 'Please choose a SQL dump file.',
            'sql_file.required_if' => 'Please choose a SQL dump file.',
            'sql_file.file' => 'The uploaded SQL dump is invalid.',
            'sql_file.extensions' => 'The database import file must be a .sql or .txt file.',
            'local_path.required_if' => 'Please enter a local SQL file path.',
            'confirm_database.accepted' => 'Please confirm the target database before importing.',
        ];
    }
}
