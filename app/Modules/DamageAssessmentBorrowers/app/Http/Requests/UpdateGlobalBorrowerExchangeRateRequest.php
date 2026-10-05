<?php

namespace App\Http\Requests\Modules\DamageAssessmentBorrowers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalBorrowerExchangeRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'Database Officer',
            'Project Officer',
            'Project Officer - Borrowers',
            'Area Manager',
            'Team Leader',
            'Team Leader -INF',
            'Auditing Supervisor',
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
            'exchange_rate' => ['required', 'numeric', 'min:0.0001', 'max:9999.9999'],
        ];
    }
}
