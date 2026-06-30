<?php

namespace App\Modules\Heks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeksLabelRequest extends FormRequest
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
            'label_key' => ['required', 'string', 'max:255'],
            'label_value' => ['nullable', 'string'],
            'version' => ['nullable', 'string', 'max:255'],
        ];
    }
}
