<?php

namespace App\Modules\Heks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeksFollowUpRequest extends FormRequest
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
            'visit_number' => ['nullable', 'string', 'max:255'],
            'visit_date' => ['nullable', 'date'],
            'engineer_name' => ['nullable', 'string', 'max:255'],
            'working_condition' => ['nullable', 'string', 'max:255'],
            'other_condition' => ['nullable', 'string'],
            'completed_amount_ils' => ['nullable', 'numeric'],
            'completion_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'engineer_recommendations' => ['nullable', 'string'],
            'boq_filename' => ['nullable', 'string', 'max:255'],
            'boq_url' => ['nullable', 'string'],
        ];
    }
}
