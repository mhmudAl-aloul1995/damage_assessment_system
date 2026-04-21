<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommitteeDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'decision_type' => ['required', 'string', Rule::in([
                'accepted',
                'rejected',
                'needs_completion',
                'needs_review',
                'recount',
                'full_demolition',
                'partial_demolition',
                'other',
            ])],
            'decision_text' => ['required', 'string'],
            'action_text' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'decision_date' => ['required', 'date'],
            'status' => ['nullable', 'string', Rule::in([
                'draft',
                'pending_signatures',
                'approved',
                'rejected',
                'completed',
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_type.required' => 'نوع القرار مطلوب.',
            'decision_text.required' => 'نص القرار مطلوب.',
            'decision_date.required' => 'تاريخ القرار مطلوب.',
        ];
    }
}
