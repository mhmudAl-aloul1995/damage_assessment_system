<?php

namespace App\Modules\Heks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeksScoringWeightRequest extends FormRequest
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
            'survey_phase' => ['required', 'in:phase_1,phase_2'],
            'category' => ['nullable', 'string', 'max:255'],
            'indicator' => ['nullable', 'string'],
            'question_key' => ['nullable', 'string', 'max:255'],
            'option_value' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric'],
            'option_score' => ['nullable', 'numeric'],
        ];
    }
}
