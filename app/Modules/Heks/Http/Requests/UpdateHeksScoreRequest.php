<?php

namespace App\Modules\Heks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeksScoreRequest extends FormRequest
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
            'grant_amount' => ['nullable', 'numeric'],
            'payment_1' => ['nullable', 'numeric'],
            'payment_2' => ['nullable', 'numeric'],
            'payment_3' => ['nullable', 'numeric'],
            'social_score' => ['nullable', 'numeric'],
            'technical_score' => ['nullable', 'numeric'],
            'total_score' => ['nullable', 'numeric'],
        ];
    }
}
