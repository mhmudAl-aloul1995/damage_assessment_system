<?php

namespace App\Modules\Heks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeksSurveyValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('Database Officer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:255'],
            'field_key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
        ];
    }
}
