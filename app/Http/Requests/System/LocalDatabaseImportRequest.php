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
            'sql_file' => ['required', 'file', 'extensions:sql,txt'],
            'confirm_database' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'sql_file.required' => 'Please choose a SQL dump file.',
            'sql_file.file' => 'The uploaded SQL dump is invalid.',
            'sql_file.extensions' => 'The database import file must be a .sql or .txt file.',
            'confirm_database.accepted' => 'Please confirm the target database before importing.',
        ];
    }
}
